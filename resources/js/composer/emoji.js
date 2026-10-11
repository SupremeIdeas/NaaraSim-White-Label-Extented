// Emoji data: the ONE platform dataset (resources/js/data/emojis.json) and the ONE recents key shared with the legacy picker.
import { ls } from './store.js';

const RECENT_KEY = 'naara-recent-emojis';
let cache = null;

export async function loadEmoji() {
    if (!cache) {
        const mod = await import('../data/emojis.json');
        cache = mod.default ?? mod;
    }
    return cache;
}

export const recents = () => {
    const r = ls.get(RECENT_KEY, []);
    return Array.isArray(r) ? r : [];
};

export function pushRecent(e) {
    ls.set(RECENT_KEY, [e, ...recents().filter((x) => x !== e)].slice(0, 24));
}
