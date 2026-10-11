// Thick, theme-aware connection lines for the WebGL scenes. Plain `THREE.Line` is always 1 px wide (the GPU ignores `linewidth`), which is why the planet's
// connective lines read as hairlines. These use three's fat-line classes (screen-space quads), so the width is real, in CSS pixels.
//
//   light surface: deep teal lattice + dark amber arcs (strong contrast on a pale hero)
//   dark surface : the light mint scent colour (+ a warm light gold arc), glowing against navy
import * as THREE from 'three';
import { LineSegments2 } from 'three/examples/jsm/lines/LineSegments2.js';
import { LineSegmentsGeometry } from 'three/examples/jsm/lines/LineSegmentsGeometry.js';
import { Line2 } from 'three/examples/jsm/lines/Line2.js';
import { LineGeometry } from 'three/examples/jsm/lines/LineGeometry.js';
import { LineMaterial } from 'three/examples/jsm/lines/LineMaterial.js';

const PALETTE = {
    light: { lattice: 0x0b5c5c, latticeOpacity: 0.78, arc: 0x9a6a00, arcMin: 0.65, arcMax: 1 },
    dark: { lattice: 0x5eead4, latticeOpacity: 0.9, arc: 0xffd54f, arcMin: 0.55, arcMax: 1 },
};

/** Is the page in dark mode right now? (`.dark` on <html>, which the theme toggle flips.) */
export const isDarkPage = () => document.documentElement.classList.contains('dark');

/** Calls `cb(dark)` whenever the page theme flips. Returns a disposer. */
export function watchTheme(cb) {
    const mo = new MutationObserver(() => cb(isDarkPage()));
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    const onEvt = (e) => cb(e?.detail?.dark ?? isDarkPage());
    window.addEventListener('theme-changed', onEvt);
    return () => { mo.disconnect(); window.removeEventListener('theme-changed', onEvt); };
}

function material(color, opacity, width) {
    return new LineMaterial({ color, linewidth: width, transparent: true, opacity, depthWrite: false });
}

/** The planet's lattice as thick segments. `edges` is a THREE.EdgesGeometry. */
export function fatLattice(edges, { dark, width = 1.8 }) {
    const p = PALETTE[dark ? 'dark' : 'light'];
    const geo = new LineSegmentsGeometry().fromEdgesGeometry(edges);
    const obj = new LineSegments2(geo, material(p.lattice, p.latticeOpacity, width));
    obj.userData.kind = 'lattice';
    return obj;
}

/** One connection arc along `points` (Vector3[]), thick. */
export function fatArc(points, { dark, width = 2.8 }) {
    const p = PALETTE[dark ? 'dark' : 'light'];
    const geo = new LineGeometry();
    geo.setPositions(points.flatMap((v) => [v.x, v.y, v.z]));
    const obj = new Line2(geo, material(p.arc, p.arcMax, width));
    obj.userData.kind = 'arc';
    return obj;
}

/** Recolour a fat line for the page theme. */
export function recolour(obj, dark) {
    const p = PALETTE[dark ? 'dark' : 'light'];
    const m = obj.material;
    if (obj.userData.kind === 'lattice') { m.color.setHex(p.lattice); m.opacity = p.latticeOpacity; } else { m.color.setHex(p.arc); }
}

/** Arc pulse opacity for time `t`, within the palette's range. */
export function arcPulse(dark, t, phase) {
    const p = PALETTE[dark ? 'dark' : 'light'];
    return p.arcMin + (p.arcMax - p.arcMin) * (0.5 + 0.5 * Math.sin(t * 1.6 + phase));
}

const _size = new THREE.Vector2();
/** Fat lines need the canvas size every frame the size may have changed. */
export function syncResolution(renderer, objs) {
    renderer.getSize(_size);
    objs.forEach((o) => o.material.resolution.set(_size.x, _size.y));
}
