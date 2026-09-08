<?php

namespace App\Support;

/**
 * The single source of service data behind the shared ServicePicker modal
 * (Numbers V6 §0). Returns a normalised, name-sorted list of pickable services
 * so the picker view stays a dumb, reusable component.
 *
 * `[ ['slug' => 'whatsapp', 'name' => 'WhatsApp'], ... ]`
 */
class ServicePickerSources
{
    /**
     * Every OTP/rental service, slug + friendly name + whether it has a real
     * logo. Sorted so services WITH an icon come first (owner request), then
     * alphabetically within each group — icon-less services follow.
     *
     * @return array<int, array{slug: string, name: string, has_icon: bool}>
     */
    public static function options(): array
    {
        $rows = [];
        foreach (NumberCatalogue::services() as $slug => $label) {
            $rows[] = ['slug' => $slug, 'name' => $label, 'has_icon' => ServiceIcons::hasIcon($slug)];
        }

        // Icons first (has_icon DESC), then name ASC.
        usort($rows, fn ($a, $b) => ($b['has_icon'] <=> $a['has_icon']) ?: strcmp($a['name'], $b['name']));

        return $rows;
    }
}
