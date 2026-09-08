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
            },
        },
    },
    plugins: [],
};
