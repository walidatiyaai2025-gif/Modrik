<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\SmtpProviderPoolService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class SmtpProviderPool extends Page
{
    protected string $view = 'filament.pages.smtp-provider-pool';

    protected static ?string $slug = 'smtp-providers';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Engagement;

    public ?string $editingId = null;

    public string $testRecipient = '';

    public string $actionReason = '';

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '',
        'host' => '',
        'port' => 587,
        'security' => 'starttls',
        'username' => '',
        'password' => '',
        'from_address' => '',
        'from_name' => 'MODRIK',
        'is_enabled' => true,
        'reason' => '',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مزودو البريد SMTP',
            'fr' => 'Fournisseurs SMTP',
            default => 'SMTP Providers',
        };
    }

    public static function getNavigationSort(): int
    {
        return 20;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'أضف عدة مزودين للبريد الصادر. يختار MODRIK مزودًا عشوائيًا لكل رسالة وينتقل تلقائيًا إلى مزود آخر عند فشل الإرسال.',
            'fr' => 'Ajoutez plusieurs fournisseurs sortants. MODRIK randomise leur ordre par message et bascule automatiquement en cas d’échec.',
            default => 'Manage multiple outbound providers. MODRIK randomizes provider order per message and automatically fails over when delivery fails.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if ($user instanceof User) {
            $this->testRecipient = (string) $user->email;
        }
    }

    public function edit(string $providerId): void
    {
        $provider = app(SmtpProviderPoolService::class)->safeProviderById($providerId);
        if ($provider === null) {
            return;
        }

        $this->editingId = $providerId;
        $this->form = [
            'name' => $provider['name'],
            'host' => $provider['host'],
            'port' => $provider['port'],
            'security' => $provider['scheme'] === 'smtps' ? 'smtps' : 'starttls',
            'username' => $provider['username'] ?? '',
            'password' => '',
            'from_address' => $provider['from_address'],
            'from_name' => $provider['from_name'],
            'is_enabled' => $provider['is_enabled'],
            'reason' => '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'host' => ['required', 'string', 'min:1', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'security' => ['required', 'in:starttls,smtps'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => [$this->editingId === null ? 'required' : 'nullable', 'string', 'max:512'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'min:1', 'max:100'],
            'is_enabled' => ['boolean'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ];
        $data = Validator::make($this->form, $rules)->validate();
        $actor = $this->actor();

        app(SmtpProviderPoolService::class)->save($actor, [
            'name' => (string) $data['name'],
            'host' => (string) $data['host'],
            'port' => (int) $data['port'],
            'scheme' => $data['security'] === 'smtps' ? 'smtps' : null,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'password' => isset($data['password']) && $data['password'] !== '' ? (string) $data['password'] : null,
            'from_address' => (string) $data['from_address'],
            'from_name' => (string) $data['from_name'],
            'is_enabled' => (bool) $data['is_enabled'],
            'reason' => (string) $data['reason'],
        ], $this->editingId);

        Notification::make()
            ->success()
            ->title($this->editingId === null ? $this->translate('SMTP provider added', 'تمت إضافة مزود SMTP', 'Fournisseur SMTP ajouté') : $this->translate('SMTP provider updated', 'تم تحديث مزود SMTP', 'Fournisseur SMTP mis à jour'))
            ->send();

        $this->editingId = null;
        $this->resetForm();
    }

    public function toggle(string $providerId): void
    {
        $reason = trim($this->actionReason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'actionReason' => $this->translate('Enter an audit reason between 8 and 500 characters.', 'أدخل سببًا للتدقيق بين 8 و500 حرف.', 'Saisissez un motif d’audit entre 8 et 500 caractères.'),
            ]);
        }

        $provider = app(SmtpProviderPoolService::class)->safeProviderById($providerId);
        if ($provider === null) {
            return;
        }

        $enabled = ! (bool) $provider['is_enabled'];
        app(SmtpProviderPoolService::class)->setEnabled($this->actor(), $providerId, $enabled, $reason);
        $this->actionReason = '';

        Notification::make()
            ->success()
            ->title($enabled ? $this->translate('Provider enabled', 'تم تفعيل المزود', 'Fournisseur activé') : $this->translate('Provider disabled', 'تم تعطيل المزود', 'Fournisseur désactivé'))
            ->send();
    }

    public function test(string $providerId): void
    {
        $data = Validator::make([
            'recipient' => $this->testRecipient,
        ], [
            'recipient' => ['required', 'email:rfc', 'max:255'],
        ])->validate();

        $result = app(SmtpProviderPoolService::class)->testProvider(
            $this->actor(),
            $providerId,
            (string) $data['recipient'],
            'Operator requested an SMTP delivery test from the Admin provider pool.',
        );

        Notification::make()
            ->title($result['ok'] ? $this->translate('Test email sent', 'تم إرسال رسالة الاختبار', 'E-mail de test envoyé') : $this->translate('SMTP test failed', 'فشل اختبار SMTP', 'Échec du test SMTP'))
            ->body($result['ok'] ? null : $this->translate('Safe error code: ', 'رمز الخطأ الآمن: ', 'Code d’erreur sûr : ').$result['code'])
            ->color($result['ok'] ? 'success' : 'danger')
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(SmtpProviderPoolService::class);

        return [
            'providers' => $service->providers(),
            'audits' => $service->audits(),
        ];
    }

    private function actor(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'host' => '',
            'port' => 587,
            'security' => 'starttls',
            'username' => '',
            'password' => '',
            'from_address' => '',
            'from_name' => 'MODRIK',
            'is_enabled' => true,
            'reason' => '',
        ];
    }

    private function translate(string $en, string $ar, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
