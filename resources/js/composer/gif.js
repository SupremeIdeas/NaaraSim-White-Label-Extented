// Client-side animated-GIF builder (3-3-2 palette + LZW). Used by the "Images -> GIF" and "Video clip -> GIF" makers; nothing leaves the device.
function lzw(px) {
    const out = [];
    let bits = 0; let nb = 0; let cs = 9; let next = 258;
    const dict = new Map();
    const emit = (code, n) => {
        bits |= code << nb; nb += n;
        while (nb >= 8) { out.push(bits & 255); bits >>= 8; nb -= 8; }
    };
    const grow = () => { next++; if (next > (1 << cs) && cs < 12) cs++; };
    emit(256, cs);
    let w = px[0];
    for (let i = 1; i < px.length; i++) {
        const k = w * 256 + px[i];
        const v = dict.get(k);
        if (v !== undefined) { w = v; continue; }
        emit(w, cs);
        if (next < 4096) { dict.set(k, next); grow(); } else { emit(256, cs); dict.clear(); next = 258; cs = 9; }
        w = px[i];
    }
    emit(w, cs); grow(); emit(257, cs);
    if (nb) out.push(bits & 255);
    return out;
}

/** frames: array of RGBA Uint8ClampedArray (w*h*4); delay in 1/100 s. */
export function encodeGif(frames, w, h, delay) {
    const o = [];
    const p = (...a) => a.forEach((x) => o.push(x));
    const w16 = (n) => p(n & 255, n >> 8);
    p(71, 73, 70, 56, 57, 97); w16(w); w16(h); p(0xF7, 0, 0);
    for (let i = 0; i < 256; i++) p(Math.round((i >> 5) * 255 / 7), Math.round(((i >> 2) & 7) * 255 / 7), Math.round((i & 3) * 255 / 3));
    p(33, 255, 11, ...'NETSCAPE2.0'.split('').map((c) => c.charCodeAt(0)), 3, 1, 0, 0, 0);
    for (const f of frames) {
        p(33, 249, 4, 4); w16(delay); p(0, 0, 44, 0, 0, 0, 0); w16(w); w16(h); p(0, 8);
        const px = new Uint8Array(w * h);
        for (let i = 0; i < w * h; i++) { const j = i * 4; px[i] = ((f[j] >> 5) << 5) | ((f[j + 1] >> 5) << 2) | (f[j + 2] >> 6); }
        const d = lzw(px);
        for (let i = 0; i < d.length; i += 255) { const c = d.slice(i, i + 255); p(c.length); c.forEach((b) => o.push(b)); }
        p(0);
    }
    p(59);
    return new Uint8Array(o);
}

/** Draw a source (image/video) cover-fitted into W x H on white and return its RGBA pixels. */
export function grabFrame(src, sw, sh, W, H) {
    const c = document.createElement('canvas'); c.width = W; c.height = H;
    const x = c.getContext('2d');
    x.fillStyle = '#fff'; x.fillRect(0, 0, W, H);
    const s = Math.max(W / sw, H / sh);
    x.drawImage(src, (W - sw * s) / 2, (H - sh * s) / 2, sw * s, sh * s);
    return x.getImageData(0, 0, W, H).data;
}

export const loadImage = (file) => new Promise((resolve, reject) => {
    const i = new Image();
    i.onload = () => resolve(i);
    i.onerror = reject;
    i.src = URL.createObjectURL(file);
});
