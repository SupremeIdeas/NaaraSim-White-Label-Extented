<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The marketing brand brief the copy populator "trains" from — filled once by the
 * admin and reused for every generation, so all copy speaks in one voice. Brand
 * name defaults to the white-label word (BrandSettings::word()), tying the two
 * features together: rebrand the business, and the AI writes as that business.
 */
class MarketingBrief
{
    public const KEYS = [
        'marketing.brief.brand', 'marketing.brief.one_liner', 'marketing.brief.audience',
        'marketing.brief.tone', 'marketing.brief.keywords',
    ];

    /** @return array{brand:string, one_liner:string, audience:string, tone:string, keywords:string} */
    public static function get(): array
    {
        return [
            'brand' => (string) (Setting::getValue('marketing.brief.brand') ?: BrandSettings::word()),
            'one_liner' => (string) Setting::getValue('marketing.brief.one_liner', ''),
            'audience' => (string) Setting::getValue('marketing.brief.audience', ''),
            'tone' => (string) Setting::getValue('marketing.brief.tone', ''),
            'keywords' => (string) Setting::getValue('marketing.brief.keywords', ''),
        ];
    }

    /** @param array<string,string> $data */
    public static function save(array $data): void
    {
        Setting::setValue('marketing.brief.brand', trim($data['brand'] ?? ''), 'marketing');
        Setting::setValue('marketing.brief.one_liner', trim($data['one_liner'] ?? ''), 'marketing');
        Setting::setValue('marketing.brief.audience', trim($data['audience'] ?? ''), 'marketing');
        Setting::setValue('marketing.brief.tone', trim($data['tone'] ?? ''), 'marketing');
        Setting::setValue('marketing.brief.keywords', trim($data['keywords'] ?? ''), 'marketing');
    }

    /** A compact prose brief for the AI system prompt. */
    public static function forPrompt(): string
    {
        $b = self::get();
        $lines = ["Brand name: {$b['brand']}"];
        if ($b['one_liner'] !== '') {
            $lines[] = "What it does: {$b['one_liner']}";
        }
        if ($b['audience'] !== '') {
            $lines[] = "Audience: {$b['audience']}";
        }
        if ($b['tone'] !== '') {
            $lines[] = "Voice / tone: {$b['tone']}";
        }
        if ($b['keywords'] !== '') {
            $lines[] = "Themes / keywords to lean on: {$b['keywords']}";
        }

        return implode("\n", $lines);
    }
}
