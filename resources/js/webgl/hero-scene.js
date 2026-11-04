// NaaraSim — marketing page WebGL heroes.
//
// One lazy module for every page hero, dispatched by a `variant` so Three.js is
// downloaded once and SHARED (Vite folds it into the same chunk the login scene
// uses). Bundled via Vite — never a CDN — so it stays CSP-safe. Each mounter
// returns a destroy() that disposes everything, and every scene installs its own
// IntersectionObserver so it stops rendering the moment it scrolls off-screen
// (one hero per page, and it idles when you're not looking at it). The caller
// guards prefers-reduced-motion; the CSS fallback stays put if WebGL is absent.

import * as THREE from 'three';
import { fatLattice, fatArc, recolour, arcPulse, syncResolution, isDarkPage, watchTheme } from './lines.js';

const TEAL = 0x3a404a;
const TEAL_LIGHT = 0xc4cbd6;
const GOLD = 0x94a0b0;

/** Shared bootstrap: renderer + rAF loop that pauses off-screen and when hidden. */
function createStage(canvas) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
    } catch (e) {
        return null; // no WebGL — the CSS fallback stays visible
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);

    function resize() {
        const el = canvas.parentElement || canvas;
        const w = canvas.clientWidth || el.clientWidth;
        const h = canvas.clientHeight || el.clientHeight;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(canvas.parentElement || canvas);
    resize();

    let raf = 0;
    let onScreen = true;
    let update = () => {};
    const clock = new THREE.Clock();
    function loop() {
        raf = 0;
        if (document.hidden || !onScreen) return;
        update(clock.getElapsedTime(), clock.getDelta());
        renderer.render(scene, camera);
        raf = requestAnimationFrame(loop);
    }
    function kick() {
        if (!raf && !document.hidden && onScreen) { clock.getDelta(); raf = requestAnimationFrame(loop); }
    }
    const io = new IntersectionObserver((entries) => {
        onScreen = entries[0].isIntersecting;
        onScreen ? kick() : (cancelAnimationFrame(raf), raf = 0);
    }, { threshold: 0.01 });
    io.observe(canvas);
    const onVisibility = () => (document.hidden ? (cancelAnimationFrame(raf), raf = 0) : kick());
    document.addEventListener('visibilitychange', onVisibility);

    return {
        scene, camera, renderer,
        setUpdate(fn) { update = fn; kick(); },
        teardown(disposeScene) {
            cancelAnimationFrame(raf);
            io.disconnect();
            ro.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            disposeScene();
            renderer.dispose();
        },
    };
}

/** Gentle pointer parallax shared by the scenes. */
function pointerParallax(strength = 0.5) {
    const target = { x: 0, y: 0 };
    const onPointer = (e) => {
        const t = e.touches ? e.touches[0] : e;
        target.x = (t.clientX / window.innerWidth - 0.5) * strength;
        target.y = (t.clientY / window.innerHeight - 0.5) * strength;
    };
    window.addEventListener('pointermove', onPointer, { passive: true });
    return { target, dispose: () => window.removeEventListener('pointermove', onPointer) };
}

// ── Variant: liquid morphology (about hero) ─────────────────────────────────
// A slow, continuously morphing teal "liquid" orb with a gold fresnel rim —
// organic brand motion for the story page. Displacement is a cheap sum of sine
// fields (no noise lib, no custom shader) whose phases drift over time, so the
// form keeps evolving through an endless slideshow of shapes. Vertex work is
// bounded (one moderate-detail icosahedron) and the whole thing idles when it
// scrolls off-screen.
function mountLiquid(canvas) {
    const stage = createStage(canvas);
    if (!stage) return () => {};
    const { scene, camera } = stage;
    camera.position.set(0, 0, 5.4);

    const blob = new THREE.Group();
    scene.add(blob);

    const geo = new THREE.IcosahedronGeometry(1.6, 4);
    const base = geo.attributes.position.array.slice(); // rest positions
    const pos = geo.attributes.position;
    const v = new THREE.Vector3();
    const mesh = new THREE.Mesh(
        geo,
        new THREE.MeshStandardMaterial({ color: TEAL, roughness: 0.18, metalness: 0.6 }),
    );
    blob.add(mesh);

    // A gold wireframe shell a hair larger, morphed in lock-step for a subtle
    // connectivity lattice over the liquid.
    const shellGeo = new THREE.IcosahedronGeometry(1.63, 4);
    const shellBase = shellGeo.attributes.position.array.slice();
    const shell = new THREE.Mesh(
        shellGeo,
        new THREE.MeshBasicMaterial({ color: GOLD, wireframe: true, transparent: true, opacity: 0.12 }),
    );
    blob.add(shell);

    scene.add(new THREE.AmbientLight(0x0a0b0d, 1.0));
    const key = new THREE.DirectionalLight(TEAL_LIGHT, 2.6);
    key.position.set(-3, 2, 4);
    scene.add(key);
    const rim = new THREE.PointLight(GOLD, 10, 18);
    rim.position.set(3.5, -1.5, 2.5);
    scene.add(rim);

    const { target, dispose: disposePointer } = pointerParallax(0.35);

    const morph = (array, source, t, amp) => {
        for (let i = 0; i < source.length; i += 3) {
            const bx = source[i], by = source[i + 1], bz = source[i + 2];
            // Sum of drifting sine fields over the vertex direction → organic swell.
            const d = 1 + amp * (
                0.35 * Math.sin(bx * 1.8 + t * 0.7) +
                0.30 * Math.sin(by * 2.2 - t * 0.9) +
                0.28 * Math.sin(bz * 2.0 + t * 0.6) +
                0.22 * Math.sin((bx + by + bz) * 1.5 + t * 1.1)
            );
            array[i] = bx * d;
            array[i + 1] = by * d;
            array[i + 2] = bz * d;
        }
    };

    stage.setUpdate((t) => {
        morph(pos.array, base, t, 0.16);
        pos.needsUpdate = true;
        geo.computeVertexNormals();
        morph(shellGeo.attributes.position.array, shellBase, t, 0.16);
        shellGeo.attributes.position.needsUpdate = true;

        blob.rotation.y = t * 0.12;
        blob.rotation.x = Math.sin(t * 0.15) * 0.15;
        camera.position.x += (target.x * 1.1 - camera.position.x) * 0.04;
        camera.position.y += (-target.y * 1.1 - camera.position.y) * 0.04;
        camera.lookAt(0, 0, 0);
    });

    return () => stage.teardown(() => {
        disposePointer();
        geo.dispose();
        mesh.material.dispose();
        shellGeo.dispose();
        shell.material.dispose();
    });
}

// ── Variant: water particles (footer) ───────────────────────────────────────
// A rippling field of teal points receding into the navy footer — a calm "cross
// the water, no borders" motif. A point grid whose height is a sum of travelling
// sine waves; scene fog melts distant points into the footer so it reads as a
// horizon. A handful of brighter gold points drift on the surface like signal
// buoys. Renders only while the footer is on-screen.
function mountWater(canvas) {
    const stage = createStage(canvas);
    if (!stage) return () => {};
    const { scene, camera } = stage;
    camera.position.set(0, 2.4, 6);
    camera.lookAt(0, 0, -4);
    scene.fog = new THREE.FogExp2(0x0a0b0d, 0.11);

    const COLS = 110, ROWS = 60, GAP = 0.34;
    const count = COLS * ROWS;
    const positions = new Float32Array(count * 3);
    const gold = new Float32Array(count); // 1 for the scattered buoys
    let g = 0;
    for (let iz = 0; iz < ROWS; iz++) {
        for (let ix = 0; ix < COLS; ix++) {
            const i = iz * COLS + ix;
            positions[i * 3] = (ix - COLS / 2) * GAP;
            positions[i * 3 + 1] = 0;
            positions[i * 3 + 2] = -iz * GAP;
            if (Math.random() < 0.015) { gold[i] = 1; g++; }
        }
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    const teal = new THREE.Points(geo, new THREE.PointsMaterial({
        color: TEAL_LIGHT, size: 0.05, transparent: true, opacity: 0.7, depthWrite: false, fog: true,
    }));
    scene.add(teal);

    // Gold buoys as a second, brighter overlay sharing the same geometry — we
    // just draw a sparse points cloud with its own positions.
    const goldPos = new Float32Array(g * 3);
    let gi = 0;
    for (let i = 0; i < count; i++) {
        if (gold[i]) { goldPos[gi * 3] = positions[i * 3]; goldPos[gi * 3 + 1] = 0; goldPos[gi * 3 + 2] = positions[i * 3 + 2]; gi++; }
    }
    const goldGeo = new THREE.BufferGeometry();
    goldGeo.setAttribute('position', new THREE.BufferAttribute(goldPos, 3));
    const goldPts = new THREE.Points(goldGeo, new THREE.PointsMaterial({
        color: GOLD, size: 0.1, transparent: true, opacity: 0.9, depthWrite: false, fog: true,
    }));
    scene.add(goldPts);

    const wave = (x, z, t) => (
        0.18 * Math.sin(x * 0.6 + t * 1.1) +
        0.14 * Math.sin(z * 0.8 + t * 0.9) +
        0.10 * Math.sin((x + z) * 0.5 - t * 1.3)
    );

    stage.setUpdate((t) => {
        const arr = geo.attributes.position.array;
        for (let i = 0; i < count; i++) {
            arr[i * 3 + 1] = wave(arr[i * 3], arr[i * 3 + 2], t);
        }
        geo.attributes.position.needsUpdate = true;
        const garr = goldGeo.attributes.position.array;
        for (let i = 0; i < g; i++) {
            garr[i * 3 + 1] = wave(garr[i * 3], garr[i * 3 + 2], t) + 0.06;
        }
        goldGeo.attributes.position.needsUpdate = true;
    });

    return () => stage.teardown(() => {
        geo.dispose();
        teal.material.dispose();
        goldGeo.dispose();
        goldPts.material.dispose();
    });
}

// ── Variant: connected city (how-it-works hero) ─────────────────────────────
// A low-poly teal skyline you drift over — "land in the city, connect
// instantly." Buildings are one InstancedMesh (cheap), lit by a teal key and a
// gold rim, with gold "uplink" points climbing from rooftops like signal. Fog
// sinks the far blocks into the page bg for depth. Idles when off-screen.
function mountCity(canvas) {
    const stage = createStage(canvas);
    if (!stage) return () => {};
    const { scene, camera } = stage;
    camera.position.set(0, 5.5, 11);
    camera.lookAt(0, 1.2, 0);
    scene.fog = new THREE.FogExp2(0x0a0b0d, 0.055);

    const GRID = 11, GAP = 1.5;
    const count = GRID * GRID;
    const geo = new THREE.BoxGeometry(0.9, 1, 0.9);
    const mat = new THREE.MeshStandardMaterial({ color: TEAL, roughness: 0.45, metalness: 0.35, flatShading: true });
    const city = new THREE.InstancedMesh(geo, mat, count);
    const dummy = new THREE.Object3D();
    const heights = [];
    let n = 0;
    for (let gx = 0; gx < GRID; gx++) {
        for (let gz = 0; gz < GRID; gz++) {
            const h = 0.6 + Math.random() * Math.random() * 6.5; // skewed → a few towers
            heights.push(h);
            dummy.position.set((gx - GRID / 2) * GAP + (Math.random() - 0.5) * 0.3, h / 2, (gz - GRID / 2) * GAP + (Math.random() - 0.5) * 0.3);
            dummy.scale.set(1, h, 1);
            dummy.updateMatrix();
            city.setMatrixAt(n++, dummy.matrix);
        }
    }
    scene.add(city);

    // Ground plane (dark) so buildings read as a skyline, not floating boxes.
    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(60, 60),
        new THREE.MeshStandardMaterial({ color: 0x0b0c0e, roughness: 1 }),
    );
    ground.rotation.x = -Math.PI / 2;
    scene.add(ground);

    // Gold "uplink" points that climb from random rooftops.
    const UPLINKS = 40;
    const upPos = new Float32Array(UPLINKS * 3);
    const upBase = [];
    for (let i = 0; i < UPLINKS; i++) {
        const idx = Math.floor(Math.random() * count);
        const gx = Math.floor(idx / GRID), gz = idx % GRID;
        const x = (gx - GRID / 2) * GAP, z = (gz - GRID / 2) * GAP;
        upBase.push({ x, z, top: heights[idx], speed: 0.6 + Math.random() * 1.2, phase: Math.random() });
        upPos[i * 3] = x; upPos[i * 3 + 1] = heights[idx]; upPos[i * 3 + 2] = z;
    }
    const upGeo = new THREE.BufferGeometry();
    upGeo.setAttribute('position', new THREE.BufferAttribute(upPos, 3));
    const uplinks = new THREE.Points(upGeo, new THREE.PointsMaterial({
        color: GOLD, size: 0.16, transparent: true, opacity: 0.95, depthWrite: false, fog: true,
    }));
    scene.add(uplinks);

    scene.add(new THREE.AmbientLight(0x0a0b0d, 1.2));
    const key = new THREE.DirectionalLight(TEAL_LIGHT, 2.2);
    key.position.set(-4, 6, 5);
    scene.add(key);
    const rim = new THREE.PointLight(GOLD, 14, 30);
    rim.position.set(6, 4, -2);
    scene.add(rim);

    const { target, dispose: disposePointer } = pointerParallax(0.4);

    stage.setUpdate((t) => {
        const arr = upGeo.attributes.position.array;
        for (let i = 0; i < UPLINKS; i++) {
            const u = upBase[i];
            const rise = ((t * u.speed + u.phase) % 1); // 0..1 loop
            arr[i * 3 + 1] = u.top + rise * 3.2;
        }
        upGeo.attributes.position.needsUpdate = true;
        uplinks.material.opacity = 0.6 + 0.35 * (0.5 + 0.5 * Math.sin(t * 3));

        // Slow drift over the skyline + gentle pointer parallax.
        camera.position.x = Math.sin(t * 0.08) * 2.2 + target.x * 2;
        camera.position.z = 11 + Math.cos(t * 0.06) * 1.2;
        camera.position.y = 5.5 - target.y * 1.2;
        camera.lookAt(0, 1.2, 0);
    });

    return () => stage.teardown(() => {
        disposePointer();
        geo.dispose();
        mat.dispose();
        ground.geometry.dispose();
        ground.material.dispose();
        upGeo.dispose();
        uplinks.material.dispose();
    });
}

/** The home hero: the lite globe, loaded as its own small chunk. Returns a destroy() straight away, even while the chunk is still loading. */
function mountGlobeLiteLazy(canvas) {
    let stop = () => {};
    let dead = false;
    import('./globe-lite.js').then((m) => { if (!dead) stop = m.mountGlobeLite(canvas, { mode: 'hero' }); }).catch(() => {});
    return () => { dead = true; stop(); };
}

const VARIANTS = { planet: mountGlobeLiteLazy, liquid: mountLiquid, water: mountWater, city: mountCity };

export function mountHeroScene(canvas, variant) {
    const mounter = VARIANTS[variant];
    return mounter ? mounter(canvas) : () => {};
}
