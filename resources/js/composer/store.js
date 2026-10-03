// Tiny persistence helpers: localStorage (drafts, small prefs) and IndexedDB (offline outbox, saved GIFs, which hold Blobs/Files).
export const ls = {
    get(k, d = null) {
        try {
            const v = JSON.parse(localStorage.getItem(k));
            return v == null ? d : v;
        } catch { return d; }
    },
    set(k, v) {
        try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch { return false; }
    },
    del(k) { try { localStorage.removeItem(k); } catch { /* storage blocked */ } },
};

let dbp = null;
function db() {
    if (!dbp) {
        dbp = new Promise((resolve) => {
            try {
                const q = indexedDB.open('naara-composer', 1);
                q.onupgradeneeded = () => q.result.createObjectStore('kv');
                q.onsuccess = () => resolve(q.result);
                q.onerror = () => resolve(null);
            } catch { resolve(null); }
        });
    }
    return dbp;
}

export const kv = {
    async get(key) {
        const d = await db();
        if (!d) return undefined;
        return new Promise((r) => {
            try {
                const g = d.transaction('kv').objectStore('kv').get(key);
                g.onsuccess = () => r(g.result);
                g.onerror = () => r(undefined);
            } catch { r(undefined); }
        });
    },
    async set(key, value) {
        const d = await db();
        if (!d) return false;
        return new Promise((r) => {
            try {
                const t = d.transaction('kv', 'readwrite');
                t.objectStore('kv').put(value, key);
                t.oncomplete = () => r(true);
                t.onerror = () => r(false);
                t.onabort = () => r(false);
            } catch { r(false); }
        });
    },
};
