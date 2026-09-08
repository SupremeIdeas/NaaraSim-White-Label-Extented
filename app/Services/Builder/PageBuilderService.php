<?php

namespace App\Services\Builder;

use App\Models\PageSection;
use App\Models\PageSectionVersion;
use App\Models\User;
use App\Support\Auditor;
use App\Support\PageSections;
use App\Support\SectionLibrary;
use Illuminate\Support\Facades\DB;

/**
 * The Section Builder's write side (Section Builder prompt §2–3). Owns the draft
 * section rows and the publish/version/rollback lifecycle so the Livewire admin
 * component stays a thin UI. Draft edits never touch the live site; only
 * publish() writes a snapshot the public renderer reads.
 */
class PageBuilderService
{
    /** Curated starter pages the builder can target, plus any already-built keys. */
    public const STARTER_PAGES = [
        'home' => 'Homepage',
        'about' => 'About Us',
        'how-it-works' => 'How It Works',
        'contact' => 'Contact',
    ];

    /** Append a new section of $type to a page's draft with its type defaults. */
    public function addSection(string $page, string $type): PageSection
    {
        abort_unless(SectionLibrary::has($type), 422, 'Unknown section type.');

        $next = (int) PageSection::where('page_key', $page)->max('sort_order') + 1;

        $section = PageSection::create([
            'page_key' => $page,
            'type' => $type,
            'config' => SectionLibrary::defaultsFor($type),
            'sort_order' => $next,
            'is_active' => true,
        ]);

        Auditor::log('builder.section_added', PageSection::class, $section->id, ['page' => $page, 'type' => $type]);

        return $section;
    }

    /** Persist a section's validated config blob. */
    public function updateConfig(PageSection $section, array $config): void
    {
        $section->update(['config' => $config]);
        Auditor::log('builder.section_updated', PageSection::class, $section->id, ['page' => $section->page_key, 'type' => $section->type]);
    }

    public function toggle(PageSection $section): void
    {
        $section->update(['is_active' => ! $section->is_active]);
    }

    public function remove(PageSection $section): void
    {
        $page = $section->page_key;
        $section->delete();
        Auditor::log('builder.section_removed', null, null, ['page' => $page, 'type' => $section->type]);
    }

    /**
     * Reorder a page's sections to match the given id sequence. Ids not on the
     * page are ignored, so a stale client array can't reorder another page.
     *
     * @param  array<int>  $orderedIds
     */
    public function reorder(string $page, array $orderedIds): void
    {
        $onPage = PageSection::where('page_key', $page)->pluck('id')->all();
        $i = 0;
        foreach ($orderedIds as $id) {
            if (in_array((int) $id, $onPage, true)) {
                PageSection::where('id', $id)->update(['sort_order' => $i++]);
            }
        }
    }

    /** Move a section one slot up/down (keyboard/no-JS friendly reorder). */
    public function move(PageSection $section, string $direction): void
    {
        $sections = PageSection::where('page_key', $section->page_key)
            ->orderBy('sort_order')->orderBy('id')->get();

        $ids = $sections->pluck('id')->all();
        $pos = array_search($section->id, $ids, true);
        if ($pos === false) {
            return;
        }
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($swap < 0 || $swap >= count($ids)) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        $this->reorder($section->page_key, $ids);
    }

    /**
     * Snapshot the current draft as a new LIVE version. The public renderer reads
     * whichever version is is_live, so publishing is what makes edits visible.
     */
    public function publish(string $page, ?User $user = null, ?string $label = null): PageSectionVersion
    {
        return DB::transaction(function () use ($page, $user, $label) {
            $snapshot = PageSection::where('page_key', $page)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn (PageSection $s) => [
                    'type' => $s->type,
                    'config' => (array) $s->config,
                    'sort_order' => $s->sort_order,
                    'is_active' => $s->is_active,
                ])->all();

            PageSectionVersion::where('page_key', $page)->where('is_live', true)->update(['is_live' => false]);

            $version = PageSectionVersion::create([
                'page_key' => $page,
                'snapshot' => $snapshot,
                'label' => $label,
                'is_live' => true,
                'published_by' => $user?->id,
            ]);

            PageSections::flush($page);
            Auditor::log('builder.published', PageSectionVersion::class, $version->id, ['page' => $page, 'sections' => count($snapshot)]);

            return $version;
        });
    }

    /**
     * Restore a past published version back into the draft rows ("reset to
     * previous settings by time") and re-publish it live, keeping the full
     * history intact.
     */
    public function rollback(PageSectionVersion $version, ?User $user = null): PageSectionVersion
    {
        return DB::transaction(function () use ($version, $user) {
            $page = $version->page_key;

            PageSection::where('page_key', $page)->delete();
            $i = 0;
            foreach ((array) $version->snapshot as $s) {
                if (! SectionLibrary::has($s['type'] ?? '')) {
                    continue;
                }
                PageSection::create([
                    'page_key' => $page,
                    'type' => $s['type'],
                    'config' => (array) ($s['config'] ?? []),
                    'sort_order' => $s['sort_order'] ?? $i,
                    'is_active' => $s['is_active'] ?? true,
                ]);
                $i++;
            }

            Auditor::log('builder.rolled_back', PageSectionVersion::class, $version->id, ['page' => $page, 'to_version' => $version->id]);

            return $this->publish($page, $user, 'Rolled back to #'.$version->id);
        });
    }

    /** All page keys the builder knows about (starters + anything already built). */
    public function pages(): array
    {
        $built = PageSection::query()->distinct()->pluck('page_key')
            ->merge(PageSectionVersion::query()->distinct()->pluck('page_key'));

        $pages = self::STARTER_PAGES;
        foreach ($built as $key) {
            $pages[$key] ??= ucwords(str_replace('-', ' ', $key));
        }

        return $pages;
    }
}
