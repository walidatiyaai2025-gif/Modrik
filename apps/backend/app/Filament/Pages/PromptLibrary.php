<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\QuestionBankPromptSeed;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class PromptLibrary extends Page
{
    protected string $view = 'filament.pages.prompt-library';

    protected static ?string $slug = 'prompt-library';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مكتبة الـPrompt',
            'fr' => 'Bibliothèque de prompts',
            default => 'Prompt Library',
        };
    }

    public static function getNavigationSort(): int
    {
        return 25;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'كتالوج مقروء فقط للـPrompts المعتمدة لإعداد Question Bank يدويًا خارج وقت التشغيل، بدون أي اعتماد على API مدفوع.',
            'fr' => 'Catalogue en lecture seule des prompts approuvés pour préparer manuellement la Question Bank hors runtime, sans dépendance à une API d’IA payante.',
            default => 'Read-only catalogue of approved prompts for manual Question Bank preparation outside runtime, with no paid-AI API dependency.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en', 'fr'], true)) {
            return;
        }

        session()->put('admin_locale', $locale);
        App::setLocale($locale);
    }

    /** @return array<int, array<string, mixed>> */
    public function prompts(): array
    {
        return [app(QuestionBankPromptSeed::class)->entry()];
    }
}
