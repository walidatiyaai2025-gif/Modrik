<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\SystemFactoryResetService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

final class SystemFactoryReset extends Page
{
    protected string $view = 'filament.pages.system-factory-reset';

    protected static ?string $slug = 'system-factory-reset';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GovernanceSettings;

    public string $confirmationPhrase = '';

    public string $confirmationEmail = '';

    public string $currentPassword = '';

    public bool $acknowledgePermanentDeletion = false;

    /** @var array<string, mixed> */
    public array $lastResult = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (string) $user->role === 'admin'
            && (string) $user->account_status === 'active'
            && $user->deleted_at === null;
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'إعادة تهيئة النظام',
            'fr' => 'Réinitialisation du système',
            default => 'System Factory Reset',
        };
    }

    public static function getNavigationSort(): int
    {
        return 95;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'ابدأ MODRIK من جديد بمسح كل بيانات التطبيق والمحتوى والتعلّم مع الاحتفاظ بحساب المدير الحالي وإعدادات التثبيت الحرجة.',
            'Start MODRIK again by deleting all application, content and learning data while preserving the current Admin account and critical installation configuration.',
            'Repartez de zéro en supprimant les données applicatives, de contenu et d’apprentissage tout en conservant l’administrateur courant et la configuration critique.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, int> */
    public function preview(): array
    {
        return app(SystemFactoryResetService::class)->preview($this->operator());
    }

    public function requiresPassword(): bool
    {
        return (bool) $this->operator()->password_enabled;
    }

    public function factoryReset(SystemFactoryResetService $reset): void
    {
        $actor = $this->operator();

        $rules = [
            'confirmationPhrase' => ['required', 'in:RESET MODRIK'],
            'confirmationEmail' => ['required', 'email'],
            'acknowledgePermanentDeletion' => ['accepted'],
        ];

        if ((bool) $actor->password_enabled) {
            $rules['currentPassword'] = ['required', 'string'];
        }

        $this->validate($rules);

        if (! hash_equals(mb_strtolower(trim((string) $actor->email)), mb_strtolower(trim($this->confirmationEmail)))) {
            $this->addError('confirmationEmail', $this->text(
                'اكتب بريد حساب المدير الحالي بالضبط.',
                'Enter the current Admin account email exactly.',
                'Saisissez exactement l’adresse e-mail de l’administrateur actuel.',
            ));

            return;
        }

        if ((bool) $actor->password_enabled && ! Hash::check($this->currentPassword, (string) $actor->password)) {
            $this->addError('currentPassword', $this->text(
                'كلمة المرور الحالية غير صحيحة.',
                'The current password is incorrect.',
                'Le mot de passe actuel est incorrect.',
            ));

            return;
        }

        $this->lastResult = $reset->execute($actor);

        $this->reset([
            'confirmationPhrase',
            'confirmationEmail',
            'currentPassword',
            'acknowledgePermanentDeletion',
        ]);

        Notification::make()
            ->success()
            ->title($this->text('تمت إعادة تهيئة MODRIK', 'MODRIK factory reset completed', 'Réinitialisation de MODRIK terminée'))
            ->body($this->text(
                'تم مسح بيانات المحتوى والتعلّم والحسابات الأخرى. يمكنك الآن إنشاء المسارات ورفع المحتوى من البداية.',
                'Content, learning data, and other accounts were removed. You can now create academic tracks and content again from scratch.',
                'Le contenu, les données d’apprentissage et les autres comptes ont été supprimés. Vous pouvez repartir de zéro.',
            ))
            ->persistent()
            ->send();
    }

    private function operator(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::canAccess(), 403);

        return $user;
    }

    private function text(string $ar, string $en, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
