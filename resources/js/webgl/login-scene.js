// NaaraSim — premium login WebGL scene.
//
// A brand-tuned adaptation of the "procedural low-poly planet" idea: a faceted
// teal world wrapped in a gold connectivity lattice, orbited by glowing nodes —
// literally "Stay Connected. No Borders." It is bundled through Vite (never a
// CDN, so it's CSP-safe) and dynamic-imported ONLY on auth pages, so Three.js
// never touches the main bundle. The caller guards prefers-reduced-motion; this
// module handles WebGL support, DPR capping, resize, pointer parallax, and full
// teardown so nothing leaks when the SPA navigates away.

import * as THREE from 'three';

const TEAL = 0x0a6e6e;
const TEAL_LIGHT = 0x2dd4bf;
const GOLD = 0xd4a017;

export function mountLoginScene(canvas) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
    } catch (e) {
        return () => {}; // no WebGL — the CSS orb fallback stays visible
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
    camera.position.set(0, 0, 6.2);

    const world = new THREE.Group();
    scene.add(world);

    // 1) The faceted planet — low-poly icosahedron, teal, flat-shaded.
    const planetGeo = new THREE.IcosahedronGeometry(1.7, 2);
    const planet = new THREE.Mesh(
        planetGeo,
        new THREE.MeshStandardMaterial({ color: TEAL, flatShading: true, roughness: 0.55, metalness: 0.2 }),
    );
    world.add(planet);

    // 2) The gold connectivity lattice over the planet.
    const lattice = new THREE.LineSegments(
        new THREE.EdgesGeometry(new THREE.IcosahedronGeometry(1.74, 2)),
        new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.35 }),
    );
    world.add(lattice);

    // 3) Orbiting connectivity nodes (glowing points on an inclined ring).
    const NODES = 220;
    const positions = new Float32Array(NODES * 3);
    for (let i = 0; i < NODES; i++) {
        const a = Math.random() * Math.PI * 2;
        const r = 2.5 + Math.random() * 1.6;
        const y = (Math.random() - 0.5) * 1.4;
        positions[i * 3] = Math.cos(a) * r;
        positions[i * 3 + 1] = y;
        positions[i * 3 + 2] = Math.sin(a) * r;
    }
    const nodeGeo = new THREE.BufferGeometry();
    nodeGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const nodes = new THREE.Points(
        nodeGeo,
        new THREE.PointsMaterial({ color: TEAL_LIGHT, size: 0.05, transparent: true, opacity: 0.9, depthWrite: false }),
    );
    nodes.rotation.z = 0.35;
    scene.add(nodes);

    // Lighting — cool key, warm gold rim.
    scene.add(new THREE.AmbientLight(0x0d1b2a, 1.1));
    const key = new THREE.DirectionalLight(TEAL_LIGHT, 2.2);
    key.position.set(-3, 2, 4);
    scene.add(key);
    const rim = new THREE.PointLight(GOLD, 8, 20);
    rim.position.set(4, -1, 2);
    scene.add(rim);

    // Pointer parallax (gentle).
    const target = { x: 0, y: 0 };
    const onPointer = (e) => {
        const t = e.touches ? e.touches[0] : e;
        target.x = (t.clientX / window.innerWidth - 0.5) * 0.5;
        target.y = (t.clientY / window.innerHeight - 0.5) * 0.5;
    };
    window.addEventListener('pointermove', onPointer, { passive: true });

    function resize() {
        const w = canvas.clientWidth || canvas.parentElement.clientWidth;
        const h = canvas.clientHeight || canvas.parentElement.clientHeight;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(canvas.parentElement || canvas);
    resize();

    // Render loop — paused when the tab is hidden.
    let raf = 0;
    let running = true;
    const clock = new THREE.Clock();
    function frame() {
        if (!running) return;
        const t = clock.getElapsedTime();
        world.rotation.y = t * 0.12;
        world.rotation.x = Math.sin(t * 0.2) * 0.12;
        nodes.rotation.y = -t * 0.06;
        // ease the group toward the pointer parallax
        world.rotation.y += (target.x - world.rotation.y % (Math.PI * 2)) * 0;
        camera.position.x += (target.x * 1.2 - camera.position.x) * 0.04;
        camera.position.y += (-target.y * 1.2 - camera.position.y) * 0.04;
        camera.lookAt(0, 0, 0);
        renderer.render(scene, camera);
        raf = requestAnimationFrame(frame);
    }
    const onVisibility = () => {
        running = !document.hidden;
        if (running) { clock.start(); frame(); }
    };
    document.addEventListener('visibilitychange', onVisibility);
    frame();

    // Teardown — dispose everything so nothing leaks on navigation.
    return function destroy() {
        running = false;
        cancelAnimationFrame(raf);
        window.removeEventListener('pointermove', onPointer);
        document.removeEventListener('visibilitychange', onVisibility);
        ro.disconnect();
        planetGeo.dispose();
        planet.material.dispose();
        lattice.geometry.dispose();
        lattice.material.dispose();
        nodeGeo.dispose();
        nodes.material.dispose();
        renderer.dispose();
    };
}
