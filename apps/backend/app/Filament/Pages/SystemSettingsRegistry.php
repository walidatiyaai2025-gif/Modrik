<?php

namespace App\Filament\Pages;

use App\Exceptions\StaleSystemSettingVersion;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\SystemSettingsRegistry as Registry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use UnitEnum;

final class SystemSettingsRegistry extends Page
{
    protected string $view = 'filament.pages.system-settings-registry';

    protected static ?string $slug = 'system-settings';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GovernanceSettings;

    public string $environment = '';

    /** @var array<string, bool|int|string> */
    public array $values = [];

    /** @var array<string, int> */
    public array $versions = [];

    /** @var array<string, string> */
    public array $reasons = [];

    public string $selectedKey = '';

    public string $pendingSaveKey = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (string) $user->role === 'admin';
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'إعدادات النظام',
            'fr' => 'Paramètres système',
            default => 'System Settings',
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
            'ar' => 'إعدادات تشغيلية غير سرية، بإصدارات وتدقيق ومنع الكتابة فوق تعديل أحدث.',
            'fr' => 'Paramètres opérationnels non secrets avec versions, audit et protection contre les modifications obsolètes.',
            default => 'Non-secret operational settings with versioning, audit history and stale-edit protection.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->environment = app()->environment();
        $definitions = app(Registry::class)->definitions();
        $this->selectedKey = (string) array_key_first($definitions);

        foreach (array_keys($definitions) as $key) {
            $this->reloadSetting((string) $key);
        }
    }

    /** @return array<string, array<string, array{type: string, default: bool|int|string, group: string, rollback: bool}>> */
    public function groupedDefinitions(): array
    {
        $groups = [];
        foreach (app(Registry::class)->definitions() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }

        return $groups;
    }

    public function requestSave(string $key): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && (string) $user->role === 'admin', 403);

        $definitions = app(Registry::class)->definitions();
        abort_unless(array_key_exists($key, $definitions), 404);

        $stateKey = $this->stateKey($key);
        $this->resetErrorBag('values.'.$stateKey);
        $this->resetErrorBag('reasons.'.$stateKey);

        $reason = trim((string) ($this->reasons[$stateKey] ?? ''));
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            $this->pendingSaveKey = '';
            $this->selectedKey = $key;
            $this->addError(
                'reasons.'.$stateKey,
                $this->translate(
                    'Enter a change reason between 8 and 500 characters before saving.',
                    'اكتب سببًا للتغيير من 8 إلى 500 حرف قبل الحفظ.',
                    'Saisissez un motif de modification de 8 à 500 caractères avant l’enregistrement.',
                ),
            );

            return;
        }

        $this->selectedKey = $key;
        $this->pendingSaveKey = $key;
    }

    public function cancelSave(): void
    {
        $this->pendingSaveKey = '';
    }

    public function confirmSave(): void
    {
        if ($this->pendingSaveKey === '') {
            return;
        }

        $key = $this->pendingSaveKey;
        $this->pendingSaveKey = '';
        $this->saveSetting($key);
    }

    public function saveSetting(string $key): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && (string) $user->role === 'admin', 403);

        $stateKey = $this->stateKey($key);
        $this->resetErrorBag('values.'.$stateKey);
        $this->resetErrorBag('reasons.'.$stateKey);

        try {
            app(Registry::class)->update(
                $key,
                $this->environment,
                $this->values[$stateKey] ?? '',
                (int) ($this->versions[$stateKey] ?? 0),
                (string) ($this->reasons[$stateKey] ?? ''),
                (string) $user->getAuthIdentifier(),
            );
            $this->reasons[$stateKey] = '';
            $this->selectedKey = $key;
            $this->reloadSetting($key);

            Notification::make()
                ->title($this->translate('Setting saved', 'تم حفظ الإعداد', 'Paramètre enregistré'))
                ->body($this->translate(
                    'The new version was saved and added to the audit history.',
                    'تم حفظ الإصدار الجديد وإضافته إلى سجل التدقيق.',
                    'La nouvelle version a été enregistrée et ajoutée à l’historique d’audit.',
                ))
                ->success()
                ->send();
        } catch (StaleSystemSettingVersion) {
            $this->reloadSetting($key);
            $this->addError(
                'values.'.$stateKey,
                $this->translate(
                    'This setting changed after you loaded the page. The latest value was reloaded; review it before saving again.',
                    'تم تعديل هذا الإعداد بعد فتح الصفحة. تم تحميل أحدث قيمة؛ راجعها قبل الحفظ مرة أخرى.',
                    'Ce paramètre a changé après le chargement de la page. La dernière valeur a été rechargée ; vérifiez-la avant de réenregistrer.',
                ),
            );
        } catch (InvalidArgumentException $exception) {
            $field = str_contains($exception->getMessage(), 'change reason')
                ? 'reasons.'.$stateKey
                : 'values.'.$stateKey;
            $this->addError($field, $exception->getMessage());
        }
    }

    public function selectHistory(string $key): void
    {
        app(Registry::class)->current($key, $this->environment);
        $this->selectedKey = $key;
    }

    public function restoreSelected(int $targetVersion): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && (string) $user->role === 'admin', 403);

        $stateKey = $this->stateKey($this->selectedKey);

        try {
            app(Registry::class)->restore(
                $this->selectedKey,
                $this->environment,
                $targetVersion,
                (int) ($this->versions[$stateKey] ?? 0),
                (string) ($this->reasons[$stateKey] ?? ''),
                (string) $user->getAuthIdentifier(),
            );
            $this->reasons[$stateKey] = '';
            $this->reloadSetting($this->selectedKey);
        } catch (StaleSystemSettingVersion) {
            $this->reloadSetting($this->selectedKey);
            $this->addError('values.'.$stateKey, $this->translate(
                'Restore blocked because a newer version exists. Review the latest state first.',
                'تم منع الاستعادة لوجود إصدار أحدث. راجع الحالة الحالية أولًا.',
                'La restauration a été bloquée car une version plus récente existe. Vérifiez d’abord l’état actuel.',
            ));
        } catch (InvalidArgumentException $exception) {
            $field = str_contains($exception->getMessage(), 'change reason')
                ? 'reasons.'.$stateKey
                : 'values.'.$stateKey;
            $this->addError($field, $exception->getMessage());
        }
    }

    /** @return array<int, array{action: string, from_version: int|null, to_version: int, before: mixed, after: mixed, reason: string, actor_id: string|null, occurred_at: string}> */
    public function selectedHistory(): array
    {
        if ($this->selectedKey === '') {
            return [];
        }

        return app(Registry::class)->history($this->selectedKey, $this->environment);
    }

    public function stateKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function settingLabel(string $key): string
    {
        $labels = [
            'auth.email.enabled' => ['Email/password enabled', 'تفعيل البريد وكلمة المرور', 'E-mail / mot de passe activé'],
            'auth.google.enabled' => ['Google enabled', 'تفعيل Google', 'Google activé'],
            'auth.apple.enabled' => ['Apple enabled', 'تفعيل Apple', 'Apple activé'],
            'notifications.enabled' => ['Notifications enabled', 'تفعيل الإشعارات', 'Notifications activées'],
            'notifications.quiet_hours.enabled' => ['Quiet hours enabled', 'تفعيل ساعات الهدوء', 'Heures calmes activées'],
            'notifications.quiet_hours.start' => ['Quiet hours start', 'بداية ساعات الهدوء', 'Début des heures calmes'],
            'notifications.quiet_hours.end' => ['Quiet hours end', 'نهاية ساعات الهدوء', 'Fin des heures calmes'],
            'firebase.fcm.enabled' => ['FCM enabled', 'تفعيل FCM', 'FCM activé'],
            'firebase.remote_config.enabled' => ['Remote Config enabled', 'تفعيل Remote Config', 'Remote Config activé'],
            'ads.global.enabled' => ['Advertising enabled', 'تفعيل الإعلانات', 'Publicité activée'],
            'ads.test_mode.enabled' => ['Advertising test mode', 'وضع اختبار الإعلانات', 'Mode test publicitaire'],
        ];

        $label = $labels[$key] ?? [$key, $key, $key];

        return match (App::getLocale()) {
            'ar' => $label[1],
            'fr' => $label[2],
            default => $label[0],
        };
    }

    public function groupLabel(string $group): string
    {
        $labels = [
            'auth' => ['Authentication', 'المصادقة', 'Authentification'],
            'notifications' => ['Notifications', 'الإشعارات', 'Notifications'],
            'firebase' => ['Firebase / runtime', 'Firebase / التشغيل', 'Firebase / exécution'],
            'ads' => ['Advertising & safety', 'الإعلانات والأمان', 'Publicité et sécurité'],
        ];
        $label = $labels[$group] ?? [$group, $group, $group];

        return match (App::getLocale()) {
            'ar' => $label[1],
            'fr' => $label[2],
            default => $label[0],
        };
    }

    private function reloadSetting(string $key): void
    {
        $current = app(Registry::class)->current($key, $this->environment);
        $stateKey = $this->stateKey($key);
        $this->values[$stateKey] = $current['value'];
        $this->versions[$stateKey] = $current['version'];
        $this->reasons[$stateKey] ??= '';
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
