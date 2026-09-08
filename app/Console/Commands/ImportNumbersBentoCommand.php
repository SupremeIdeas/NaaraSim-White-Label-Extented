<?php

namespace App\Console\Commands;

use App\Models\NumbersBentoCard;
use App\Models\PageSection;
use App\Services\Builder\PageBuilderService;
use Illuminate\Console\Command;

/**
 * Reconcile the older Numbers-specific bento cards into the universal Section
 * Builder (Section Builder §6). Folds every NumbersBentoCard row into a single
 * `bento` section on the `numbers` builder page, so the bento-grid section type
 * becomes the one system those cards are instances of. Idempotent — re-running
 * replaces the imported section, never duplicating it. The legacy model + admin
 * page keep working until the team switches the Numbers page over to the builder.
 */
class ImportNumbersBentoCommand extends Command
{
    protected $signature = 'builder:import-numbers-bento {--publish : Publish the numbers page after import}';

    protected $description = 'Import legacy NumbersBentoCard rows into a Section Builder bento section';

    public function handle(PageBuilderService $builder): int
    {
        $cards = NumbersBentoCard::orderBy('sort_order')->orderBy('id')->get();
        if ($cards->isEmpty()) {
            $this->warn('No NumbersBentoCard rows to import.');

            return self::SUCCESS;
        }

        $mapped = $cards->map(fn (NumbersBentoCard $c) => [
            'image' => $c->image_path ?: '',
            'icon' => $c->icon_path ?: 'signal',
            'title' => $c->title,
            'body' => $c->subtitle,
            'badge' => $c->badge_label ?: '',
            'cta_label' => '',
            'cta_target' => '',
        ])->all();

        // Replace any previously-imported bento section on the numbers page.
        PageSection::where('page_key', 'numbers')->where('type', 'bento')->delete();

        $section = $builder->addSection('numbers', 'bento');
        $builder->updateConfig($section, array_merge($section->config, [
            'heading' => 'What you can do with a number',
            'layout' => 'rhythm',
            'cards' => $mapped,
        ]));

        $this->info("Imported {$cards->count()} card(s) into a bento section on the 'numbers' page.");

        if ($this->option('publish')) {
            $builder->publish('numbers');
            $this->info("Published the 'numbers' builder page.");
        } else {
            $this->line("Run with --publish, or publish from Admin → Page builder, to make it live.");
        }

        return self::SUCCESS;
    }
}
