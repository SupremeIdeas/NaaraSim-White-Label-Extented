import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // NaaraSim UI rule (CLAUDE.md): class-based dark mode, toggled on <html>.
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Body copy (Module 26).
                sans: ['Didact Gothic', 'Figtree', ...defaultTheme.fontFamily.sans],
                // Titles & headings — the Supreme Ideas Agency custom font.
                display: ['Supreme Display', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Brand palette (blueprint Section 2.1 / 4.1). Driven by CSS variables
            // so the admin can recolour the whole platform at runtime with NO
            // rebuild (Module 26 — defaults live in resources/css/app.css and an
            // admin override <style> is injected in the layout head). The
            // channel-triple form keeps Tailwind's /opacity utilities working.
            colors: {
                primary: {
                    DEFAULT: 'rgb(var(--brand-primary) / <alpha-value>)', // Deep Teal
                    dark: 'rgb(var(--brand-primary-dark) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--brand-accent) / <alpha-value>)', // Warm Gold
                    dark: 'rgb(var(--brand-accent-dark) / <alpha-value>)', // text/icon-safe on light surfaces
                },
                navy: 'rgb(var(--brand-navy) / <alpha-value>)',       // Midnight Navy
                action: 'rgb(var(--brand-action) / <alpha-value>)',   // Coral Red
                success: '#16A34A',
                warning: '#D97706',
                danger: '#DC2626',
                // Skin-system tokens (Prompt 20 §2.5): mode-flipping, so views write bg-nx-surface and never `dark:` for surfaces.
                nx: {
                    canvas: 'rgb(var(--nx-canvas) / <alpha-value>)',
                    canvas2: 'rgb(var(--nx-canvas-2) / <alpha-value>)',
                    surface: 'rgb(var(--nx-surface) / <alpha-value>)',
                    surface2: 'rgb(var(--nx-surface-2) / <alpha-value>)',
                    surface3: 'rgb(var(--nx-surface-3) / <alpha-value>)',
                    line: 'rgb(var(--nx-line) / <alpha-value>)',
                    lineStrong: 'rgb(var(--nx-line-strong) / <alpha-value>)',
                    text: 'rgb(var(--nx-text) / <alpha-value>)',
                    text2: 'rgb(var(--nx-text-2) / <alpha-value>)',
                    text3: 'rgb(var(--nx-text-3) / <alpha-value>)',
                    teal: 'rgb(var(--nx-teal) / <alpha-value>)',
                    tealInk: 'rgb(var(--nx-teal-ink) / <alpha-value>)',
                    gold: 'rgb(var(--nx-gold) / <alpha-value>)',
                    goldInk: 'rgb(var(--nx-gold-ink) / <alpha-value>)',
                    onGold: 'rgb(var(--nx-on-gold) / <alpha-value>)',
                    ok: 'rgb(var(--nx-ok) / <alpha-value>)',
                    warn: 'rgb(var(--nx-warn) / <alpha-value>)',
                    bad: 'rgb(var(--nx-bad) / <alpha-value>)',
                },
            },
            boxShadow: {
                'nx-card': 'var(--nx-e1)',
                'nx-sheet': 'var(--nx-e2)',
            },
            borderRadius: {
                'nx-card': 'calc(var(--nx-r-card) * var(--nx-rs))',
                'nx-sheet': 'calc(var(--nx-r-sheet) * var(--nx-rs))',
                'nx-row': 'calc(var(--nx-r-row) * var(--nx-rs))',
            },
        },
    },
    plugins: [],
};
