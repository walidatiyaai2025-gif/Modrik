<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\SmtpProviderPoolService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Throwable;
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
    public array $lastTestResult = [];

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
            'ar' => 'أضف عدة مزودين للبريد الصادر، اختبر الإعدادات قبل الحفظ، وشاهد سبب الفشل الآمن مباشرة.',
            'fr' => 'Ajoutez plusieurs fournisseurs sortants, testez la configuration avant enregistrement et obtenez un diagnostic sûr en cas d’échec.',
            default => 'Manage outbound providers, test the current settings before saving, and get a safe diagnostic reason when delivery fails.',
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

        $this->resetValidation();
        $this->lastTestResult = [];
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
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        /** @var array{form: array<string, mixed>} $validated */
        $validated = $this->validate($this->saveRules(), $this->validationMessages());
        $data = $validated['form'];

        try {
            app(SmtpProviderPoolService::class)->save($this->actor(), [
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
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title($this->translate('SMTP settings were not saved', 'لم يتم حفظ إعدادات البريد', 'Les paramètres SMTP n’ont pas été enregistrés'))
                ->body($this->translate('The server rejected the save. Check the highlighted fields and Runtime Inspector, then try again.', 'رفض الخادم عملية الحفظ. راجع الحقول المعلّمة وRuntime Inspector ثم أعد المحاولة.', 'Le serveur a refusé l’enregistrement. Vérifiez les champs signalés et Runtime Inspector.'))
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($this->editingId === null ? $this->translate('SMTP provider added', 'تمت إضافة مزود SMTP', 'Fournisseur SMTP ajouté') : $this->translate('SMTP provider updated', 'تم تحديث مزود SMTP', 'Fournisseur SMTP mis à jour'))
            ->body($this->translate('The configuration was saved successfully.', 'تم حفظ الإعدادات بنجاح.', 'La configuration a été enregistrée.'))
            ->send();

        $this->editingId = null;
        $this->resetValidation();
        $this->resetForm();
    }

    public function testCurrent(): void
    {
        /** @var array{form: array<string, mixed>, testRecipient: string} $validated */
        $validated = $this->validate($this->testCurrentRules(), $this->validationMessages());
        $data = $validated['form'];

        $result = app(SmtpProviderPoolService::class)->testConfiguration([
            'host' => (string) $data['host'],
            'port' => (int) $data['port'],
            'scheme' => $data['security'] === 'smtps' ? 'smtps' : null,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'password' => isset($data['password']) && $data['password'] !== '' ? (string) $data['password'] : null,
            'from_address' => (string) $data['from_address'],
            'from_name' => (string) $data['from_name'],
        ], (string) $validated['testRecipient'], $this->editingId);

        $this->showTestResult($result);
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
        /** @var array{testRecipient: string} $data */
        $data = $this->validate([
            'testRecipient' => ['required', 'email:rfc', 'max:255'],
        ], $this->validationMessages());

        $result = app(SmtpProviderPoolService::class)->testProvider(
            $this->actor(),
            $providerId,
            (string) $data['testRecipient'],
            'Operator requested an SMTP delivery test from the Admin provider pool.',
        );

        $this->showTestResult($result);
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

    /** @return array<string, array<int, string>> */
    private function saveRules(): array
    {
        return [
            'form.name' => ['required', 'string', 'min:2', 'max:80'],
            'form.host' => ['required', 'string', 'min:1', 'max:255'],
            'form.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'form.security' => ['required', 'in:starttls,smtps'],
            'form.username' => ['nullable', 'string', 'max:255'],
            'form.password' => [$this->editingId === null ? 'required' : 'nullable', 'string', 'max:512'],
            'form.from_address' => ['required', 'email:rfc', 'max:255'],
            'form.from_name' => ['required', 'string', 'min:1', 'max:100'],
            'form.is_enabled' => ['boolean'],
            'form.reason' => ['required', 'string', 'min:8', 'max:500'],
        ];
    }

    /** @return array<string, array<int, string>> */
    private function testCurrentRules(): array
    {
        return [
            'form.host' => ['required', 'string', 'min:1', 'max:255'],
            'form.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'form.security' => ['required', 'in:starttls,smtps'],
            'form.username' => ['nullable', 'string', 'max:255'],
            'form.password' => [$this->editingId === null ? 'required' : 'nullable', 'string', 'max:512'],
            'form.from_address' => ['required', 'email:rfc', 'max:255'],
            'form.from_name' => ['required', 'string', 'min:1', 'max:100'],
            'testRecipient' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'form.reason.required' => $this->translate('A change reason is required.', 'سبب التغيير مطلوب.', 'Le motif de modification est obligatoire.'),
            'form.reason.min' => $this->translate('The change reason must be at least 8 characters.', 'سبب التغيير يجب أن يكون 8 أحرف على الأقل.', 'Le motif doit contenir au moins 8 caractères.'),
            'form.password.required' => $this->translate('A password is required for a new SMTP provider.', 'كلمة المرور مطلوبة عند إضافة مزود جديد.', 'Un mot de passe est requis pour un nouveau fournisseur.'),
            'form.host.required' => $this->translate('SMTP host is required.', 'خادم SMTP مطلوب.', 'L’hôte SMTP est obligatoire.'),
            'form.from_address.email' => $this->translate('Enter a valid sender email address.', 'أدخل بريد مرسل صحيحًا.', 'Saisissez une adresse expéditeur valide.'),
            'testRecipient.required' => $this->translate('A test recipient is required.', 'بريد الاختبار مطلوب.', 'Un destinataire de test est requis.'),
            'testRecipient.email' => $this->translate('Enter a valid test recipient.', 'أدخل بريد اختبار صحيحًا.', 'Saisissez un destinataire de test valide.'),
        ];
    }

    /** @param array{ok: bool, code: string, message?: string, detail?: ?string} $result */
    private function showTestResult(array $result): void
    {
        $this->lastTestResult = $result;
        $body = (string) ($result['message'] ?? $result['code']);
        if (is_string($result['detail'] ?? null) && $result['detail'] !== '') {
            $body .= ' — '.$result['detail'];
        }

        Notification::make()
            ->title($result['ok'] ? $this->translate('Test email sent', 'تم إرسال رسالة الاختبار', 'E-mail de test envoyé') : $this->translate('SMTP test failed', 'فشل اختبار SMTP', 'Échec du test SMTP'))
            ->body($body)
            ->color($result['ok'] ? 'success' : 'danger')
            ->send();
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
        $this->lastTestResult = [];
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
