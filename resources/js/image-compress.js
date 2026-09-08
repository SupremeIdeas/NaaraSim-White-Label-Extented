// --- Browser-side image compression (BUILD-11 §2) ---------------------------
// Shrinks an image on the user's own device BEFORE Livewire uploads it, so a
// 5MB phone photo never travels as 5MB. This is a bandwidth/UX optimisation
// only — the authoritative ~80KB WebP pass runs server-side (BUILD-11 §3)
// regardless of what the browser produces here.
//
// It applies UNIFORMLY to every upload surface in one place: a document-level
// capture-phase listener intercepts any file <input> before Livewire's own
// change handler runs, compresses the image files, swaps them in, then lets a
// fresh change event reach Livewire. No per-form wiring, no missed surfaces.
//
// Graceful degradation (§2.3): if the platform can't reliably do this (an old
// browser or in-app webview without createImageBitmap / canvas.toBlob /
// DataTransfer), we DON'T intercept at all — Livewire uploads the original and
// the server-side pass is the safety net either way.

const MARK = '__naaraImgCompressed';
const MAX_EDGE = 2000; // longest edge, px (§2.1)
const QUALITY = 0.82;
const COMPRESSIBLE = /^image\/(jpeg|png|webp)$/;

function canCompress() {
    return typeof DataTransfer === 'function'
        && typeof createImageBitmap === 'function'
        && typeof HTMLCanvasElement !== 'undefined'
        && typeof HTMLCanvasElement.prototype.toBlob === 'function';
}

/**
 * Resize (never upscale) to fit MAX_EDGE and re-encode at the SAME mime type,
 * keeping the original filename — so server-side validation and extension logic
 * see the type they expect. Returns the original file untouched on any problem
 * or when the result isn't actually smaller.
 */
async function compressOne(file) {
    if (!COMPRESSIBLE.test(file.type)) {
        return file; // svg / gif / non-image → passthrough
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        return file;
    }

    try {
        const longest = Math.max(bitmap.width, bitmap.height);
        const scale = longest > MAX_EDGE ? MAX_EDGE / longest : 1;
        const w = Math.max(1, Math.round(bitmap.width * scale));
        const h = Math.max(1, Math.round(bitmap.height * scale));

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return file;
        }
        ctx.drawImage(bitmap, 0, 0, w, h);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, file.type, QUALITY));
        if (!blob || blob.size >= file.size) {
            return file; // encode failed or no win → keep original
        }

        return new File([blob], file.name, { type: file.type, lastModified: Date.now() });
    } catch {
        return file;
    } finally {
        if (typeof bitmap.close === 'function') {
            bitmap.close();
        }
    }
}

async function compressInputFiles(input) {
    const originals = Array.from(input.files || []);
    let out = originals;
    try {
        out = await Promise.all(originals.map(compressOne));
    } catch {
        out = originals;
    }

    try {
        const dt = new DataTransfer();
        out.forEach((f) => dt.items.add(f));
        input.files = dt.files;
    } catch {
        // Can't rebuild the FileList — leave the originals in place; the
        // re-dispatched event below still uploads them.
    }

    input[MARK] = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function onChangeCapture(e) {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }
    // Our own re-dispatched event — let it flow to Livewire untouched.
    if (input[MARK]) {
        input[MARK] = false;
        return;
    }
    if (!canCompress()) {
        return; // graceful: don't intercept, Livewire handles the original
    }
    const files = Array.from(input.files || []);
    if (!files.some((f) => COMPRESSIBLE.test(f.type))) {
        return; // nothing here we compress (svg/gif/non-image only)
    }

    // Preempt Livewire's own change handler so it doesn't grab the originals;
    // we'll hand it the compressed files via a fresh change event.
    e.stopImmediatePropagation();
    compressInputFiles(input);
}

export function initImageCompression() {
    if (window.__naaraImgCompressInit) {
        return;
    }
    window.__naaraImgCompressInit = true;
    // Capture phase on the document runs before any element/bubble listener,
    // so it works for inputs added later by Livewire without re-binding.
    document.addEventListener('change', onChangeCapture, true);
}
