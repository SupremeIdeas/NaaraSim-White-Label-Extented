<?php

namespace App\Support;

/**
 * The section-type registry for the universal Section Builder (Section Builder
 * prompt §2). Each type declares its label, icon, blade partial and its config
 * DEFAULTS (the config blob is stored as JSON per section and validated per-type
 * in the admin builder). Keeping the registry in one place means the admin UI,
 * the renderer and the validator all agree on what a type is and what it holds.
 *
 * Foundation increment ships: Hero (the flagship — 5 presets × 3 background
 * modes), Two-column (image + text), and Custom HTML (allowlist-sanitized). The
 * remaining library types (bento, carousel, testimonial, FAQ, logo strip,
 * stacking, video, code) plug into the same registry in later increments.
 */
class SectionLibrary
{
    /**
     * @return array<string, array{label:string, icon:string, blade:string, description:string, defaults:array<string,mixed>}>
     */
    public static function types(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
                'icon' => 'zap',
                'blade' => 'partials.sections.hero',
                'description' => 'Large opening banner — animated gradient, background image, image slideshow or a clean static layout.',
                'defaults' => self::applyHeroPreset(self::heroBase(), 'aurora'),
            ],
            'two_column' => [
                'label' => 'Two-column',
                'icon' => 'list',
                'blade' => 'partials.sections.two-column',
                'description' => 'Image on one side, copy on the other — admin picks which side the image sits.',
                'defaults' => [
                    'eyebrow' => '',
                    'headline' => 'A headline that sells the point',
                    'body' => 'A short supporting paragraph. Keep it tight — one idea, clearly stated.',
                    'image' => '',
                    'image_side' => 'right',      // left | right
                    'cta_label' => '',
                    'cta_target' => '',
                    'bg' => 'transparent',        // transparent | tint | dark
                ],
            ],
            'bento' => [
                'label' => 'Bento grid',
                'icon' => 'grid',
                'blade' => 'partials.sections.bento',
                'description' => 'Varied-weight card grid. Admin picks a rhythm layout; each card = image, title, copy, pill CTA.',
                'defaults' => [
                    'heading' => 'What you get',
                    'subheading' => '',
                    'layout' => 'rhythm',   // rhythm | uniform | featured
                    'cards' => [
                        ['image' => '', 'icon' => 'signal', 'title' => 'eSIM Data Plans', 'body' => 'Local data in 190+ countries.', 'cta_label' => 'Browse plans', 'cta_target' => '', 'badge' => ''],
                        ['image' => '', 'icon' => 'phone', 'title' => BrandSettings::rebrand('Naara Line'), 'body' => 'A real second number, voice + SMS.', 'cta_label' => 'Get a number', 'cta_target' => '', 'badge' => ''],
                        ['image' => '', 'icon' => 'shield', 'title' => BrandSettings::rebrand('Naara Verify'), 'body' => 'Disposable numbers for OTP.', 'cta_label' => 'Verify', 'cta_target' => '', 'badge' => ''],
                    ],
                ],
            ],
            'carousel' => [
                'label' => 'Carousel',
                'icon' => 'grid',
                'blade' => 'partials.sections.carousel',
                'description' => 'Horizontal-scroll card row (recents-style) with a soft ease + swell on the focused card.',
                'defaults' => [
                    'heading' => 'Recent',
                    'see_all_label' => '',
                    'see_all_target' => '',
                    'cards' => [
                        ['image' => '', 'title' => 'Card one', 'subtitle' => 'Category', 'target' => ''],
                        ['image' => '', 'title' => 'Card two', 'subtitle' => 'Category', 'target' => ''],
                        ['image' => '', 'title' => 'Card three', 'subtitle' => 'Category', 'target' => ''],
                    ],
                ],
            ],
            'storytelling' => [
                'label' => 'Storytelling',
                'icon' => 'play',
                'blade' => 'partials.sections.storytelling',
                'description' => 'Apple-style scroll-through: a fixed image with animated copy, auto-advancing slides, progress dots, and an optional deep modal per slide.',
                'defaults' => [
                    'heading' => '',
                    'subheading' => '',
                    'tone' => 'auto',   // auto | on-dark (light text for a dark band)
                    'slides' => [
                        ['image' => '', 'eyebrow' => '', 'title' => 'A first, evocative slide', 'body' => 'One idea, clearly stated — the reader should feel it, not scan it.', 'cta_label' => '', 'cta_target' => '', 'modal_body' => ''],
                        ['image' => '', 'eyebrow' => '', 'title' => 'A second slide', 'body' => 'Another beat in the story. Keep each one tight.', 'cta_label' => '', 'cta_target' => '', 'modal_body' => ''],
                    ],
                ],
            ],
            'faq' => [
                'label' => 'FAQ',
                'icon' => 'help-circle',
                'blade' => 'partials.sections.faq',
                'description' => 'Accordion of question / answer pairs.',
                'defaults' => [
                    'heading' => 'Frequently asked',
                    'style' => 'bordered',   // bordered | plain
                    'items' => [
                        ['q' => 'How fast is activation?', 'a' => 'Most eSIMs activate in under a minute.'],
                        ['q' => 'Do I need to swap my SIM?', 'a' => 'No — an eSIM runs alongside your physical SIM.'],
                    ],
                ],
            ],
            'testimonial' => [
                'label' => 'Testimonial',
                'icon' => 'star',
                'blade' => 'partials.sections.testimonial',
                'description' => 'Customer quotes — name, quote, optional photo + star rating.',
                'defaults' => [
                    'heading' => 'Loved by travellers',
                    'items' => [
                        ['name' => 'Ada N.', 'quote' => 'Landed in Nairobi already online. Magic.', 'photo' => '', 'rating' => 5],
                    ],
                ],
            ],
            'logo_showcase' => [
                'label' => 'Logo showcase',
                'icon' => 'grid',
                'blade' => 'partials.sections.logo-showcase',
                'description' => 'Auto-scrolling strip of partner / press logos.',
                'defaults' => [
                    'heading' => '',
                    'logos' => [],   // [{src, alt}]
                ],
            ],
            'quote' => [
                'label' => 'Premium quote',
                'icon' => 'star',
                'blade' => 'partials.sections.quote',
                'description' => 'A single large pull-quote with a choice of visual treatments.',
                'defaults' => [
                    'quote' => 'No borders. No swaps. Just connection.',
                    'attribution' => '',
                    'treatment' => 'gradient',   // gradient | minimal | dark
                ],
            ],
            'video' => [
                'label' => 'Video embed',
                'icon' => 'signal',
                'blade' => 'partials.sections.video',
                'description' => 'Embed a YouTube / Vimeo video by URL, with an optional caption.',
                'defaults' => [
                    'url' => '',
                    'caption' => '',
                ],
            ],
            'code' => [
                'label' => 'Code snippet',
                'icon' => 'hash',
                'blade' => 'partials.sections.code',
                'description' => 'A syntax-styled code block for docs-style pages.',
                'defaults' => [
                    'caption' => '',
                    'language' => 'bash',
                    'code' => '',
                ],
            ],
            'custom_html' => [
                'label' => 'Custom HTML',
                'icon' => 'hash',
                'blade' => 'partials.sections.custom-html',
                'description' => 'Paste raw HTML for one-off needs. Sanitized against XSS on save (allowlist, not raw-render).',
                'defaults' => [
                    'html' => '',
                    'max_width' => 'container',   // container | full
                ],
            ],
        ];
    }

    /**
     * Repeater metadata for types that hold a list of items (cards / q&a / logos),
     * so the builder can add/remove rows + target image uploads generically.
     *
     * @return array{field:string, template:array, imageKey:?string}|null
     */
    public static function repeaterFor(string $type): ?array
    {
        return match ($type) {
            'bento' => ['field' => 'cards', 'imageKey' => 'image', 'template' => ['image' => '', 'icon' => 'signal', 'title' => 'New card', 'body' => '', 'cta_label' => '', 'cta_target' => '', 'badge' => '']],
            'carousel' => ['field' => 'cards', 'imageKey' => 'image', 'template' => ['image' => '', 'title' => 'New card', 'subtitle' => '', 'target' => '']],
            'storytelling' => ['field' => 'slides', 'imageKey' => 'image', 'template' => ['image' => '', 'eyebrow' => '', 'title' => 'New slide', 'body' => '', 'cta_label' => '', 'cta_target' => '', 'modal_body' => '']],
            'faq' => ['field' => 'items', 'imageKey' => null, 'template' => ['q' => '', 'a' => '']],
            'testimonial' => ['field' => 'items', 'imageKey' => 'photo', 'template' => ['name' => '', 'quote' => '', 'photo' => '', 'rating' => 5]],
            'logo_showcase' => ['field' => 'logos', 'imageKey' => 'src', 'template' => ['src' => '', 'alt' => '']],
            default => null,
        };
    }

    /** The 5 pre-made, reusable 2026-trending hero looks (prompt: "build like 5 premade hero sections"). */
    public static function heroPresets(): array
    {
        return [
            'aurora' => [
                'label' => 'Aurora',
                'description' => 'Animated teal→gold→coral gradient drift, centered copy. The flagship look.',
            ],
            'spotlight' => [
                'label' => 'Spotlight',
                'description' => 'Deep navy with a radial glow behind bold, centered headline text.',
            ],
            'split' => [
                'label' => 'Split',
                'description' => 'Copy on the left, a product image on the right. Great for a feature launch.',
            ],
            'minimal' => [
                'label' => 'Minimal',
                'description' => 'Clean, light, generous whitespace. Left-aligned, understated, fast.',
            ],
            'showcase' => [
                'label' => 'Showcase',
                'description' => 'Full-bleed image slideshow with a bottom scrim and overlaid copy.',
            ],
        ];
    }

    /** The style/mode defaults each preset applies on top of the shared hero base. */
    private static function presetOverrides(string $preset): array
    {
        return match ($preset) {
            'spotlight' => ['mode' => 'animation', 'align' => 'center', 'scheme' => 'dark'],
            'split' => ['mode' => 'image', 'align' => 'left', 'scheme' => 'light'],
            'minimal' => ['mode' => 'static', 'align' => 'left', 'scheme' => 'light'],
            'showcase' => ['mode' => 'images', 'align' => 'left', 'scheme' => 'dark'],
            default => ['mode' => 'animation', 'align' => 'center', 'scheme' => 'brand'], // aurora
        };
    }

    /** Shared hero config skeleton before a preset is layered on. */
    private static function heroBase(): array
    {
        return [
            'preset' => 'aurora',
            'mode' => 'animation',              // animation | image | images | static
            'scheme' => 'brand',                // brand | light | dark
            'align' => 'center',                // left | center
            'eyebrow' => 'Stay Connected. No Borders.',
            'headline' => 'Land Anywhere. Connect Instantly.',
            'subheadline' => 'A local data plan and a real second number in 190+ countries — activated before you even leave home.',
            'cta_primary_label' => 'Get Started',
            'cta_primary_target' => '',
            'cta_secondary_label' => 'See How It Works',
            'cta_secondary_target' => '',
            'images' => [],                     // urls; [0] used as bg for 'image' mode
        ];
    }

    /** Merge a preset's look onto a hero config (used for defaults + when the admin switches preset). */
    public static function applyHeroPreset(array $config, string $preset): array
    {
        $preset = array_key_exists($preset, self::heroPresets()) ? $preset : 'aurora';

        return array_merge($config, self::presetOverrides($preset), ['preset' => $preset]);
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    public static function defaultsFor(string $type): array
    {
        return self::types()[$type]['defaults'] ?? [];
    }

    public static function bladeFor(string $type): ?string
    {
        return self::types()[$type]['blade'] ?? null;
    }

    public static function labelFor(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
