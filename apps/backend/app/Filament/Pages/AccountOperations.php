<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\AdminAccountOperationsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use UnitEnum;

final class AccountOperations extends Page
{
    protected string $view = 'filament.pages.account-operations';

    protected static ?string $slug = 'account-operations';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::UsersAccess;

    public string $search = '';

    public string $roleFilter = 'all';

    public string $statusFilter = 'all';

    public string $providerFilter = 'all';

    public string $sessionFilter = 'all';

    public string $selectedUserId = '';

    public string $revokeReason = '';

    public string $verifyReason = '';

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
            'ar' => 'الحسابات والجلسات',
            'fr' => 'Comptes et sessions',
            default => 'Accounts & Sessions',
        };
    }

    public static function getNavigationSort(): int
    {
        return 10;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'عرض آمن للحسابات والأدوار والجلسات مع استرداد أمني مضبوط. لا يتم عرض كلمات المرور أو رموز الجلسات أو أسرار المزودين.',
            'fr' => 'Vue sûre des comptes, rôles et sessions avec récupération de sécurité bornée. Aucun mot de passe, jeton de session ou secret fournisseur n’est exposé.',
            default => 'Safe account, role and session visibility with bounded security recovery. Passwords, session tokens and provider secrets are never exposed.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function selectAccount(string $userId): void
    {
        $this->selectedUserId = $userId;
        $this->revokeReason = '';
        $this->verifyReason = '';
    }

    public function clearSelection(): void
    {
        $this->selectedUserId = '';
        $this->revokeReason = '';
        $this->verifyReason = '';
    }

    public function revokeAllSessions(): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || $this->selectedUserId === '') {
            return;
        }

        $result = app(AdminAccountOperationsService::class)->revokeAllSessions(
            $actor,
            $this->selectedUserId,
            $this->revokeReason,
        );
        $this->revokeReason = '';

        Notification::make()
            ->success()
            ->title(match (App::getLocale()) {
                'ar' => 'تم إلغاء الجلسات بأمان',
                'fr' => 'Sessions révoquées en toute sécurité',
                default => 'Sessions revoked safely',
            })
            ->body(match (App::getLocale()) {
                'ar' => 'تم إلغاء '.(string) $result['revoked_sessions'].' جلسة نشطة.',
                'fr' => (string) $result['revoked_sessions'].' session(s) active(s) révoquée(s).',
                default => (string) $result['revoked_sessions'].' active session(s) revoked.',
            })
            ->send();
    }

    public function verifySelectedEmail(): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || $this->selectedUserId === '') {
            return;
        }

        $result = app(AdminAccountOperationsService::class)->verifyStudentEmail(
            $actor,
            $this->selectedUserId,
            $this->verifyReason,
        );
        $this->verifyReason = '';

        Notification::make()
            ->success()
            ->title(match (App::getLocale()) {
                'ar' => 'تم توثيق بريد الطالب',
                'fr' => 'E-mail étudiant vérifié',
                default => 'Student email verified',
            })
            ->body($result['changed']
                ? match (App::getLocale()) {
                    'ar' => 'تم اعتبار البريد موثّقًا بواسطة مدير النظام، ويمكن للطالب الآن تفعيل المسار والبدء.',
                    'fr' => 'L’e-mail a été vérifié par un administrateur. L’étudiant peut maintenant activer son parcours.',
                    default => 'The email was verified by an administrator. The student can now activate an academic track.',
                }
                : match (App::getLocale()) {
                    'ar' => 'الحساب كان موثّقًا بالفعل؛ لم يتم تغيير شيء.',
                    'fr' => 'Le compte était déjà vérifié ; aucune modification.',
                    default => 'The account was already verified; nothing changed.',
                })
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(AdminAccountOperationsService::class);

        return [
            'accounts' => $service->accounts(
                $this->search,
                $this->roleFilter,
                $this->statusFilter,
                $this->providerFilter,
                $this->sessionFilter,
            ),
            'selectedAccount' => $this->selectedUserId === '' ? null : $service->account($this->selectedUserId),
            'roleMatrix' => $service->roleMatrix(),
            'audits' => $service->audits($this->selectedUserId),
        ];
    }
}
