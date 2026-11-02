// Connected globe, lite edition (home hero and login).
// Real Natural Earth land (public domain, 110m) drawn as a field of fine dots on a matte sphere, a few hairline routes with soft comets
// travelling between real cities, a calm rim of light. No flags, no tour, no borders: the quiet cousin of the flagship globe.
// Colours come from the page: graphite and silver by default, and they follow the brand palette (--brand-accent) if the admin changes it.
// Lazy (dynamic import), bundled by Vite (CSP-safe), pauses off-screen and when the tab is hidden, disposes everything on teardown.
import * as THREE from 'three';
import world from './world-lite.json';
import { isDarkPage, watchTheme } from './lines.js';

const R = 2.1;
const CITIES = [
    [6.52, 3.38], [5.6, -0.19], [-1.29, 36.82], [-26.2, 28.05], [30.04, 31.24], [14.69, -17.44], [9.03, 38.74], [33.57, -7.59],
    [51.5, -0.12], [48.85, 2.35], [52.52, 13.4], [41.9, 12.5], [25.2, 55.27], [19.07, 72.87], [1.35, 103.82], [35.68, 139.69],
    [31.2, 121.47], [-33.87, 151.2], [40.71, -74.0], [43.65, -79.38], [34.05, -118.24], [19.43, -99.13], [-23.55, -46.63], [-34.6, -58.38],
];
const PORTS = [
    ['ng', 'Nigeria', 6.52, 3.38], ['gh', 'Ghana', 5.6, -0.19], ['ke', 'Kenya', -1.29, 36.82], ['za', 'South Africa', -26.2, 28.05], ['eg', 'Egypt', 30.04, 31.24],
    ['gb', 'United Kingdom', 51.5, -0.12], ['fr', 'France', 48.85, 2.35], ['ae', 'United Arab Emirates', 25.2, 55.27], ['in', 'India', 19.07, 72.87],
    ['sg', 'Singapore', 1.35, 103.82], ['jp', 'Japan', 35.68, 139.69], ['us', 'United States', 40.71, -74.0], ['br', 'Brazil', -23.55, -46.63], ['au', 'Australia', -33.87, 151.2],
];
const latLon = (lat, lon, r = R) => {
    const phi = ((90 - lat) * Math.PI) / 180, th = ((lon + 180) * Math.PI) / 180;
    return new THREE.Vector3(-r * Math.sin(phi) * Math.cos(th), r * Math.cos(phi), r * Math.sin(phi) * Math.sin(th));
};

function inRing(lon, lat, ring, s) {
    let inside = false;
    for (let i = 0, j = ring.length - 2; i < ring.length; i += 2) {
        const xi = ring[i] / s, yi = ring[i + 1] / s, xj = ring[j] / s, yj = ring[j + 1] / s;
        if ((yi > lat) !== (yj > lat) && lon < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside;
        j = i;
    }
    return inside;
}

/** Fibonacci sphere tested against the real polygons, so the continents are where they truly are. */
function landDots(count) {
    const s = world.s;
    const polys = world.land.map((rings) => {
        let a = 999, b = -999, c = 99, d = -99;
        const o = rings[0];
        for (let i = 0; i < o.length; i += 2) {
            const lo = o[i] / s, la = o[i + 1] / s;
            if (lo < a) a = lo; if (lo > b) b = lo; if (la < c) c = la; if (la > d) d = la;
        }
        return { rings, a, b, c, d };
    });
    const out = [];
    const golden = Math.PI * (3 - Math.sqrt(5));
    for (let i = 0; i < count; i++) {
        const lat = (Math.asin(1 - (i / (count - 1)) * 2) * 180) / Math.PI;
        if (lat < -58) continue;
        const lon = ((((((i * golden) % (Math.PI * 2)) * 180) / Math.PI - 180 + 180) % 360) + 360) % 360 - 180;
        for (const p of polys) {
            if (lon < p.a || lon > p.b || lat < p.c || lat > p.d || !inRing(lon, lat, p.rings[0], s)) continue;
            let hole = false;
            for (let h = 1; h < p.rings.length; h++) if (inRing(lon, lat, p.rings[h], s)) { hole = true; break; }
            if (!hole) { out.push(lat, lon); break; }
        }
    }
    return out;
}

const brandAction = () => {
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--brand-action').trim().split(/[\s,]+/).map(Number);
    return raw.length >= 3 && raw.every((n) => Number.isFinite(n)) ? new THREE.Color(raw[0] / 255, raw[1] / 255, raw[2] / 255) : new THREE.Color(0x3d5afe);
};
const brandAccent = () => {
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--brand-accent').trim().split(/[\s,]+/).map(Number);
    return raw.length >= 3 && raw.every((n) => Number.isFinite(n)) ? new THREE.Color(raw[0] / 255, raw[1] / 255, raw[2] / 255) : new THREE.Color(0x94a0b0);
};

const POINT_VERT = 'attribute float aA; attribute float aS; varying float vA; void main(){ vA = aA; vec4 mv = modelViewMatrix * vec4(position,1.0); gl_PointSize = aS * (320.0 / -mv.z); gl_Position = projectionMatrix * mv; }';
const POINT_FRAG = 'uniform vec3 uC; varying float vA; void main(){ float d = length(gl_PointCoord - 0.5); gl_FragColor = vec4(uC, smoothstep(0.5, 0.0, d) * vA); }';
const RIM_VERT = 'varying vec3 vN; varying vec3 vV; void main(){ vN = normalize(normalMatrix * normal); vec4 mv = modelViewMatrix * vec4(position,1.0); vV = normalize(-mv.xyz); gl_Position = projectionMatrix * mv; }';
const RIM_FRAG = 'uniform vec3 uC; uniform float uK; varying vec3 vN; varying vec3 vV; void main(){ float f = pow(1.0 - max(dot(vN, vV), 0.0), 3.0); gl_FragColor = vec4(uC, f * uK); }';

/** mode: 'hero' follows the page theme; 'login' always renders for the dark panel. */
export function mountGlobeLite(canvas, { mode = 'hero' } = {}) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
    } catch (e) {
        return () => {};
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
    const login = mode === 'login';
    const small = (canvas.clientWidth || window.innerWidth) < 700;
    camera.position.set(0, 0, login ? 6.1 : small ? 6.5 : 5.5);
    const centre = login ? new THREE.Vector3(0, 0.15, 0) : new THREE.Vector3(small ? 0 : 1.2, small ? -1.5 : -0.15, 0);

    const globe = new THREE.Group();
    globe.position.copy(centre);
    scene.add(globe);
    let dark = login ? true : isDarkPage();
    const accent = brandAccent();
    const pop = brandAction();
    const popLight = pop.clone().lerp(new THREE.Color(0xffffff), 0.45);

    const sea = new THREE.MeshBasicMaterial({ color: dark ? 0x0d0f12 : 0xeceef1 });
    const seaMesh = new THREE.Mesh(new THREE.SphereGeometry(R * 0.995, 72, 72), sea);
    globe.add(seaMesh);

    const ll = landDots(small ? 9000 : 17000);
    const pos = new Float32Array((ll.length / 2) * 3);
    for (let i = 0; i < ll.length; i += 2) { const v = latLon(ll[i], ll[i + 1], R * 1.003); pos.set([v.x, v.y, v.z], (i / 2) * 3); }
    const dotsGeo = new THREE.BufferGeometry();
    dotsGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const c = document.createElement('canvas');
    c.width = c.height = 32;
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.beginPath(); g.arc(16, 16, 12, 0, Math.PI * 2); g.fill();
    const dotTex = new THREE.CanvasTexture(c);
    const dots = new THREE.Points(dotsGeo, new THREE.PointsMaterial({ map: dotTex, color: dark ? 0xd6dce6 : 0x2b3038, size: small ? 0.042 : 0.038, transparent: true, opacity: 1, alphaTest: 0.25, depthWrite: false }));
    globe.add(dots);

    const rimMat = new THREE.ShaderMaterial({ vertexShader: RIM_VERT, fragmentShader: RIM_FRAG, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, uniforms: { uC: { value: accent.clone() }, uK: { value: dark ? 1.15 : 0.55 } } });
    globe.add(new THREE.Mesh(new THREE.SphereGeometry(R * 1.06, 48, 48), rimMat));
    const halo = document.createElement('canvas');
    halo.width = halo.height = 256;
    const hg = halo.getContext('2d');
    const gr = hg.createRadialGradient(128, 128, 70, 128, 128, 128);
    gr.addColorStop(0, 'rgba(255,255,255,0.55)'); gr.addColorStop(0.6, 'rgba(255,255,255,0.12)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
    hg.fillStyle = gr; hg.fillRect(0, 0, 256, 256);
    const haloTex = new THREE.CanvasTexture(halo);
    const haloMat = new THREE.SpriteMaterial({ map: haloTex, color: accent, transparent: true, opacity: dark ? 0.5 : 0.35, depthWrite: false, blending: dark ? THREE.AdditiveBlending : THREE.NormalBlending });
    const haloSprite = new THREE.Sprite(haloMat);
    haloSprite.scale.setScalar(R * 3.1);
    haloSprite.position.set(centre.x, centre.y, -1.2);
    scene.add(haloSprite);

    // Ports: a glowing point and a pulse ring per city; a flag chip (HTML, flag-icons) opens over it when a route lands.
    let flagLayer = canvas.parentElement?.parentElement?.querySelector('[data-planet-flags]') || canvas.parentElement?.querySelector('[data-planet-flags]');
    if (!flagLayer && canvas.parentElement) {
        flagLayer = document.createElement('div');
        flagLayer.setAttribute('data-planet-flags', '');
        flagLayer.className = 'pointer-events-none absolute inset-0';
        canvas.parentElement.appendChild(flagLayer);
    }
    const portMat = new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.95 });
    const portGeo = new THREE.SphereGeometry(0.03, 12, 12);
    const ringGeo = new THREE.RingGeometry(0.05, 0.062, 40);
    const ringMat = new THREE.MeshBasicMaterial({ color: pop, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
    const ports = PORTS.map(([cc, country, lat, lon]) => {
        const pos = latLon(lat, lon, R * 1.008);
        const dot = new THREE.Mesh(portGeo, portMat);
        dot.position.copy(pos);
        globe.add(dot);
        const ring = new THREE.Mesh(ringGeo, ringMat.clone());
        ring.position.copy(pos);
        ring.lookAt(pos.clone().multiplyScalar(2));
        globe.add(ring);
        let el = null;
        if (flagLayer) {
            el = document.createElement('span');
            el.className = 'nx-globe-flag';
            el.innerHTML = `<span class="fi fi-${cc}" role="img" aria-label="${country}"></span><i></i><em>${country}</em>`;
            flagLayer.appendChild(el);
        }
        return { cc, lat, lon, pos, dot, ring, el, until: 0, pulse: -9 };
    });
    const portAt = new Map(ports.map((p) => [`${p.lat},${p.lon}`, p]));

    // Routes: a handful of great-circle arcs between real cities, each with a soft comet that loops.
    const routes = [];
    const lineVerts = [];
    const want = small ? 14 : 24;
    let guard = 0;
    while (routes.length < want && guard++ < 400) {
        const pool = Math.random() < 0.6 ? PORTS.map((p) => [p[2], p[3]]) : CITIES;
        const a = pool[Math.floor(Math.random() * pool.length)];
        const b = pool[Math.floor(Math.random() * pool.length)];
        if (a === b) continue;
        const pa = latLon(a[0], a[1], R * 1.004), pb = latLon(b[0], b[1], R * 1.004);
        const chord = pa.distanceTo(pb);
        if (chord < 1.4 || chord > 4.0) continue;
        const mid = pa.clone().add(pb).multiplyScalar(0.5).setLength(R + 0.1 + chord * 0.2);
        const curve = new THREE.QuadraticBezierCurve3(pa, mid, pb);
        const pts = curve.getPoints(40);
        for (let i = 1; i < pts.length; i++) lineVerts.push(pts[i - 1].x, pts[i - 1].y, pts[i - 1].z, pts[i].x, pts[i].y, pts[i].z);
        routes.push({ curve, speed: 0.07 + Math.random() * 0.07, phase: Math.random(), to: portAt.get(`${b[0]},${b[1]}`) || null, landed: -1 });
    }
    const lineGeo = new THREE.BufferGeometry();
    lineGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(lineVerts), 3));
    const lineMat = new THREE.LineBasicMaterial({ color: dark ? popLight : pop, transparent: true, opacity: dark ? 0.34 : 0.4, depthWrite: false });
    globe.add(new THREE.LineSegments(lineGeo, lineMat));

    const TRAIL = 7;
    const N = routes.length * TRAIL;
    const cPos = new Float32Array(N * 3), cA = new Float32Array(N), cS = new Float32Array(N);
    const cGeo = new THREE.BufferGeometry();
    cGeo.setAttribute('position', new THREE.BufferAttribute(cPos, 3));
    cGeo.setAttribute('aA', new THREE.BufferAttribute(cA, 1));
    cGeo.setAttribute('aS', new THREE.BufferAttribute(cS, 1));
    const cMat = new THREE.ShaderMaterial({ vertexShader: POINT_VERT, fragmentShader: POINT_FRAG, transparent: true, depthWrite: false, uniforms: { uC: { value: dark ? popLight.clone() : pop.clone() } } });
    globe.add(new THREE.Points(cGeo, cMat));

    const apply = (isDark) => {
        dark = login ? true : isDark;
        sea.color.setHex(dark ? 0x0d0f12 : 0xeceef1);
        dots.material.color.setHex(dark ? 0xd6dce6 : 0x2b3038);
        rimMat.uniforms.uK.value = dark ? 1.15 : 0.55; haloMat.opacity = dark ? 0.5 : 0.35; haloMat.blending = dark ? THREE.AdditiveBlending : THREE.NormalBlending; haloMat.needsUpdate = true;
        lineMat.opacity = dark ? 0.34 : 0.4; lineMat.color.copy(dark ? popLight : pop);
        cMat.uniforms.uC.value.copy(dark ? popLight : pop);
    };
    const stopTheme = login ? () => {} : watchTheme(apply);

    // Face Africa first, then drift.
    const v0 = latLon(5, 12, 1);
    const base = Math.atan2(-v0.x, v0.z);
    globe.rotation.x = 0.3;

    const target = { x: 0, y: 0 }, cur = { x: 0, y: 0 };
    const onPointer = (e) => {
        const p = e.touches ? e.touches[0] : e;
        target.x = (p.clientX / window.innerWidth - 0.5) * 2;
        target.y = (p.clientY / window.innerHeight - 0.5) * 2;
    };
    window.addEventListener('pointermove', onPointer, { passive: true });

    function resize() {
        const w = canvas.clientWidth, h = canvas.clientHeight;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(canvas.parentElement || canvas);
    resize();

    const tmp = new THREE.Vector3();
    const clock = new THREE.Clock();
    let raf = 0, onScreen = true;
    function frame() {
        raf = 0;
        if (document.hidden || !onScreen) return;
        const t = clock.getElapsedTime();
        cur.x += (target.x - cur.x) * 0.04; cur.y += (target.y - cur.y) * 0.04;
        globe.rotation.y = base + t * 0.05 + cur.x * 0.12;
        globe.rotation.x = 0.3 + cur.y * 0.06;
        routes.forEach((r, i) => {
            const head = (((t * r.speed + r.phase) % 1.6) / 1.6) * 1.35 - 0.05;   // brief rest between runs
            for (let k = 0; k < TRAIL; k++) {
                const u = head - k * 0.018;
                const idx = i * TRAIL + k;
                if (u < 0 || u > 1) { cA[idx] = 0; continue; }
                r.curve.getPoint(u, tmp);
                cPos[idx * 3] = tmp.x; cPos[idx * 3 + 1] = tmp.y; cPos[idx * 3 + 2] = tmp.z;
                const edge = Math.min(u, 1 - u) * 9;
                cA[idx] = Math.min(1, edge) * (1 - k / TRAIL) * 0.95;
                cS[idx] = (k === 0 ? 0.07 : 0.045) * (1 - k / (TRAIL * 1.4));
            }
        });
        const w = canvas.clientWidth, h = canvas.clientHeight;
        routes.forEach((r, i) => {
            const head = (((t * r.speed + r.phase) % 1.6) / 1.6) * 1.35 - 0.05;
            if (head >= 1 && head < 1.04 && r.to && t - r.landed > 1) { r.landed = t; r.to.until = t + 2.8; r.to.pulse = t; }
        });
        ports.forEach((p) => {
            const age = t - p.pulse;
            p.ring.material.opacity = age >= 0 && age < 1.6 ? (1 - age / 1.6) * 0.9 : 0;
            p.ring.scale.setScalar(1 + (age >= 0 && age < 1.6 ? age * 2.4 : 0));
            if (!p.el) return;
            tmp.copy(p.pos).applyMatrix4(globe.matrixWorld);
            const facing = tmp.clone().sub(centre).normalize().dot(camera.position.clone().sub(tmp).normalize());
            const sx = ((tmp.clone().project(camera).x + 1) / 2) * w;
            if (t >= p.until || facing < 0.25 || !w || sx < 70 || sx > w - 70) { p.el.classList.remove('is-on', 'is-focus'); return; }
            tmp.project(camera);
            p.el.style.setProperty('--x', `${((tmp.x + 1) / 2) * w}px`);
            p.el.style.setProperty('--y', `${((1 - tmp.y) / 2) * h}px`);
            p.el.classList.add('is-on');
            p.el.classList.toggle('is-focus', t < p.until - 0.6);
        });
        cGeo.attributes.position.needsUpdate = cGeo.attributes.aA.needsUpdate = cGeo.attributes.aS.needsUpdate = true;
        renderer.render(scene, camera);
        raf = requestAnimationFrame(frame);
    }
    const kick = () => { if (!raf && !document.hidden && onScreen) { clock.getDelta(); raf = requestAnimationFrame(frame); } };
    const io = new IntersectionObserver((e) => { onScreen = e[0].isIntersecting; onScreen ? kick() : (cancelAnimationFrame(raf), raf = 0); }, { threshold: 0.01 });
    io.observe(canvas);
    const onVis = () => (document.hidden ? (cancelAnimationFrame(raf), (raf = 0)) : kick());
    document.addEventListener('visibilitychange', onVis);
    kick();

    return () => {
        cancelAnimationFrame(raf);
        io.disconnect(); ro.disconnect(); stopTheme();
        document.removeEventListener('visibilitychange', onVis);
        window.removeEventListener('pointermove', onPointer);
        [dotsGeo, lineGeo, cGeo, seaMesh.geometry].forEach((x) => x.dispose());
        [dots.material, lineMat, cMat, sea, rimMat].forEach((m) => m.dispose());
        dotTex.dispose(); haloTex.dispose(); haloMat.dispose(); portGeo.dispose(); ringGeo.dispose(); portMat.dispose();
        ports.forEach((p) => { p.ring.material.dispose(); p.el?.remove(); });
        scene.remove(haloSprite);
        renderer.dispose();
    };
}
