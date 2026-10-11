<?php

namespace Tests\Feature;

use Tests\TestCase;

class WideTablesScrollOnPhonesTest extends TestCase
{
    public function test_boxes_holding_a_table_scroll_sideways_on_phones(): void
    {
        $css = file_get_contents(base_path('resources/css/nx-layout.css'));
        $this->assertStringContainsString(':root div:has(> table)', $css);
        $this->assertMatchesRegularExpression('/:root div:has\(> table\)[^{]*\{[^}]*overflow-x:\s*auto/s', $css);
    }

    public function test_the_users_promote_menu_is_inline_so_scrolling_never_clips_it(): void
    {
        $v = file_get_contents(resource_path('views/livewire/admin/users.blade.php'));
        $this->assertStringNotContainsString('absolute right-0 top-8', $v);
    }
}
