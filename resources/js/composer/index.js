// <naara-composer> — Chat Composer Pro as a light-DOM custom element. The heavy module is code-split and only fetched on pages that
// render one. Options come from the `data-config` JSON attribute (server-rendered, translated labels included).
class NaaraComposer extends HTMLElement {
    connectedCallback() {
        if (this._cc || this._loading) return;
        this._loading = true;
        let opts = {};
        try { opts = JSON.parse(this.getAttribute('data-config') || '{}'); } catch { /* use defaults */ }
        import('./composer.js').then(({ Composer }) => {
            this._loading = false;
            if (!this.isConnected) return;
            this._cc = new Composer(this, opts);
            this.dispatchEvent(new CustomEvent('cc:ready', { bubbles: true }));
        });
    }

    disconnectedCallback() { this._cc?.destroy(); this._cc = null; }

    focus() { this._cc?.focus(); }
    reset() { this._cc?.reset(); }
    setDisabled(on) { this._cc?.setDisabled(on); }
    getState() { return this._cc?.getState(); }
}

export function registerComposer() {
    if (!customElements.get('naara-composer')) customElements.define('naara-composer', NaaraComposer);
}
