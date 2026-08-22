<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSidebarContrastTest extends TestCase
{
    public function test_sidebar_navigation_uses_high_contrast_inactive_text_and_icons(): void
    {
        $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-group-label\s*\{[^}]*color:\s*rgb\(255 255 255 \/ \.80\);[^}]*font-weight:\s*750;/s',
            $theme,
        );
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-item-button\s*\{[^}]*color:\s*rgb\(255 255 255 \/ \.94\);/s',
            $theme,
        );
        $this->assertStringContainsString('.fi-sidebar-item-label { color: inherit; font-weight: 600; }', $theme);
        $this->assertMatchesRegularExpression(
            '/\.fi-sidebar-item-icon,\s*\.fi-sidebar-group-icon\s*\{\s*color:\s*rgb\(255 255 255 \/ \.86\);\s*\}/s',
            $theme,
        );
    }

    public function test_sidebar_active_and_hover_states_remain_visually_distinct(): void
    {
        $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('.fi-sidebar-item-button:hover { background: rgb(255 255 255 / .09); color: var(--modrik-white); }', $theme);
        $this->assertStringContainsString('background: rgb(0 191 166 / .18);', $theme);
        $this->assertStringContainsString('box-shadow: inset 3px 0 0 var(--modrik-teal);', $theme);
        $this->assertStringContainsString('box-shadow: inset -3px 0 0 var(--modrik-teal);', $theme);
    }

    public function test_old_faded_sidebar_values_cannot_return(): void
    {
        $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringNotContainsString('color: rgb(255 255 255 / .52);', $theme);
        $this->assertStringNotContainsString('color: rgb(255 255 255 / .76);', $theme);
        $this->assertStringNotContainsString('color: rgb(255 255 255 / .68);', $theme);
    }
}
