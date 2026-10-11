// SVG icons for the composer (stroke icons, currentColor). No emoji glyphs anywhere in the chrome.
const s = (body, extra = '') => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ${extra}>${body}</svg>`;
const f = (body) => `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">${body}</svg>`;

export const ICONS = {
    attach: s('<path d="M21 10.5V17a4 4 0 0 1-4 4H8a5 5 0 0 1-5-5V7a4 4 0 0 1 4-4h6.5A3.5 3.5 0 0 1 17 6.5V15a2.5 2.5 0 0 1-5 0V8"/>'),
    emoji: s('<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="9.5" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="9.5" r="1" fill="currentColor" stroke="none"/>'),
    mic: s('<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 19v3"/>'),
    send: s('<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>', 'stroke-width="2.2"'),
    trash: s('<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12"/>'),
    play: f('<path d="M8 5v14l11-7z"/>'),
    pause: f('<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>'),
    x: s('<path d="M6 6l12 12M18 6 6 18"/>', 'stroke-width="2"'),
    search: s('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
    image: s('<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.6"/><path d="m21 15-5-5L5 21"/>'),
    camera: s('<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>'),
    video: s('<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>'),
    file: s('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>'),
    music: s('<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>'),
    pin: s('<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'),
    user: s('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'),
    chart: s('<path d="M5 21V10M12 21V4M19 21v-7"/>'),
    clock: s('<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/>'),
    check: s('<path d="m5 12.5 4.5 4.5L19 7"/>', 'stroke-width="2.2"'),
    star: s('<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>'),
    pencil: s('<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>'),
    gif: s('<rect x="2.5" y="6" width="19" height="12" rx="3"/><path d="M8.5 10.5H7a1.5 1.5 0 0 0 0 3h1.5V12M12 10v4M15 14v-4h2.5M15 12h2"/>'),
    warn: s('<path d="M12 3 2 20h20z"/><path d="M12 10v5M12 18h.01"/>'),
};
