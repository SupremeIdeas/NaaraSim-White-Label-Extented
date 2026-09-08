// --- In-browser voice-note recording for NaaraCare chat (BUILD-3 §3) ---------
// Replaces the old "mic = native file picker" with real in-page capture via
// getUserMedia + MediaRecorder, then feeds the recorded blob into the SAME
// Livewire `voiceNote` / `sendVoice` pipeline — only the capture method changes,
// not how the note is stored or sent.
//
// The "explain first, then request permission" pattern here is the template for
// any future camera/mic/location request in the app: we show a short branded
// explanation BEFORE calling getUserMedia, so the OS-level prompt never appears
// unexplained. All three permission outcomes are handled explicitly (granted /
// denied / dismissed-or-error) with no dead ends.
//
// Registered as an Alpine data component so the Blade stays declarative and the
// code is bundled via Vite (CSP-safe, no CDN, no inline logic blob).

function pickMimeType() {
    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'];
    if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') {
        return '';
    }
    return candidates.find((t) => MediaRecorder.isTypeSupported(t)) || '';
}

function extForMime(mime) {
    if (mime.includes('mp4')) return 'm4a';
    if (mime.includes('ogg')) return 'ogg';
    return 'webm';
}

export function registerVoiceRecorder() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('voiceRecorder', () => ({
            // idle | priming | requesting | recording | uploading | denied | unsupported | error
            state: 'idle',
            seconds: 0,
            _timer: null,
            _recorder: null,
            _stream: null,
            _chunks: [],
            _mime: '',

            get supported() {
                return !!(navigator.mediaDevices
                    && typeof navigator.mediaDevices.getUserMedia === 'function'
                    && typeof MediaRecorder !== 'undefined');
            },

            get timeLabel() {
                const m = Math.floor(this.seconds / 60);
                const s = this.seconds % 60;
                return `${m}:${String(s).padStart(2, '0')}`;
            },

            // Step 1: tap the mic → show the branded explanation BEFORE any OS
            // prompt. If the browser can't record at all, say so honestly.
            promptMic() {
                if (!this.supported) {
                    this.state = 'unsupported';
                    return;
                }
                this.state = 'priming';
            },

            // Step 2: user accepted the explanation → now the real permission ask.
            async requestMic() {
                this.state = 'requesting';
                try {
                    this._stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.startRecording();
                } catch (e) {
                    // NotAllowedError / SecurityError = the user (or policy) blocked it.
                    if (e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')) {
                        this.state = 'denied';
                    } else {
                        this.state = 'error';
                    }
                }
            },

            startRecording() {
                this._chunks = [];
                this._mime = pickMimeType();
                try {
                    this._recorder = this._mime
                        ? new MediaRecorder(this._stream, { mimeType: this._mime })
                        : new MediaRecorder(this._stream);
                } catch {
                    this.state = 'error';
                    this.releaseStream();
                    return;
                }

                this._recorder.addEventListener('dataavailable', (ev) => {
                    if (ev.data && ev.data.size > 0) this._chunks.push(ev.data);
                });
                this._recorder.addEventListener('stop', () => this.finish());

                this._recorder.start();
                this.state = 'recording';
                this.seconds = 0;
                this._timer = setInterval(() => {
                    this.seconds += 1;
                    // Hard cap at 5 minutes — the server also limits size to 10MB.
                    if (this.seconds >= 300) this.stop();
                }, 1000);
            },

            // Stop → assemble the blob → hand it to Livewire → send.
            stop() {
                if (this.state !== 'recording') return;
                this.clearTimer();
                this.state = 'uploading';
                try {
                    this._recorder.stop(); // triggers 'stop' → finish()
                } catch {
                    this.finish();
                }
            },

            finish() {
                this.releaseStream();
                if (!this._chunks.length) {
                    this.reset();
                    return;
                }
                const mime = this._mime || 'audio/webm';
                const blob = new Blob(this._chunks, { type: mime });
                const file = new File([blob], `voice-note.${extForMime(mime)}`, { type: mime });

                this.$wire.upload(
                    'voiceNote',
                    file,
                    () => { this.$wire.sendVoice(); this.reset(); },
                    () => { this.state = 'error'; },
                );
            },

            // Discard everything without sending.
            cancel() {
                this.clearTimer();
                try { this._recorder && this._recorder.state !== 'inactive' && this._recorder.stop(); } catch { /* noop */ }
                this._chunks = [];
                this.releaseStream();
                this.reset();
            },

            releaseStream() {
                if (this._stream) {
                    this._stream.getTracks().forEach((t) => t.stop());
                    this._stream = null;
                }
            },

            clearTimer() {
                if (this._timer) { clearInterval(this._timer); this._timer = null; }
            },

            reset() {
                this.clearTimer();
                this.state = 'idle';
                this.seconds = 0;
                this._chunks = [];
                this._recorder = null;
            },
        }));
    });
}
