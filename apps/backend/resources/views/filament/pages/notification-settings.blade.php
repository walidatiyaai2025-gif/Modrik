<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-notification-settings">
        <x-admin.operational-banner
            :severity="$status['enabled'] ? 'success' : 'warning'"
            :title="$locale === 'ar' ? 'حالة الإشعارات' : ($locale === 'fr' ? 'État des notifications' : 'Notification status')"
            :message="$locale === 'ar' ? 'قنوات التحقق والاسترداد البريدية منفذة. مركز إشعارات الطالب العام ما زال يحتاج إغلاق نطاق منفصل ولا يتم ادعاء جاهزيته هنا.' : ($locale === 'fr' ? 'Les canaux e-mail de vérification et récupération sont disponibles. Le centre de notifications étudiant général reste à auditer séparément.' : 'Verification and recovery email channels are implemented. The general Student Notification Center still requires separate scope closure and is not represented as complete here.')"
        >
            <div class="modrik-code mt-2 text-xs" dir="ltr">APP_ENV={{ $environment }}</div>
        </x-admin.operational-banner>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'الإشعارات العامة' : ($locale === 'fr' ? 'Notifications globales' : 'Global notifications')"
                :value="$status['enabled'] ? ($locale === 'ar' ? 'مفعلة' : ($locale === 'fr' ? 'Activées' : 'Enabled')) : ($locale === 'ar' ? 'متوقفة' : ($locale === 'fr' ? 'Désactivées' : 'Disabled'))"
                :detail="$locale === 'ar' ? 'مفتاح تشغيل بالإصدار والتدقيق' : ($locale === 'fr' ? 'Commutateur versionné et audité' : 'Versioned, audited switch')"
            />
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'التحقق بالبريد' : ($locale === 'fr' ? 'Vérification e-mail' : 'Email verification')"
                value="{{ $status['email_verification_channel'] }}"
                :detail="$locale === 'ar' ? 'قناة منفذة' : ($locale === 'fr' ? 'Canal implémenté' : 'Implemented channel')"
            />
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'استرداد كلمة المرور' : ($locale === 'fr' ? 'Récupération du mot de passe' : 'Password recovery')"
                value="{{ $status['password_recovery_channel'] }}"
                :detail="$locale === 'ar' ? 'قناة منفذة' : ($locale === 'fr' ? 'Canal implémenté' : 'Implemented channel')"
            />
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'إشعارات Push' : 'Push notifications'"
                value="{{ $status['push_channel'] }}"
                :detail="$locale === 'ar' ? 'مرتبطة بحالة FCM' : ($locale === 'fr' ? 'Liées à l’état FCM' : 'Bound to FCM status')"
            />
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="quiet-hours-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="quiet-hours-heading" class="text-lg font-semibold text-gray-950">
                        {{ $locale === 'ar' ? 'ساعات الهدوء' : ($locale === 'fr' ? 'Heures calmes' : 'Quiet hours') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $locale === 'ar' ? 'سياسة تشغيلية محفوظة في System Settings Registry.' : ($locale === 'fr' ? 'Politique opérationnelle conservée dans le registre des paramètres.' : 'Operational policy stored in the System Settings Registry.') }}
                    </p>
                </div>
                <x-filament::badge :color="$status['quiet_hours_enabled'] ? 'warning' : 'gray'">
                    {{ $status['quiet_hours_enabled'] ? ($locale === 'ar' ? 'مفعلة' : ($locale === 'fr' ? 'Activées' : 'Enabled')) : ($locale === 'ar' ? 'غير مفعلة' : ($locale === 'fr' ? 'Désactivées' : 'Disabled')) }}
                </x-filament::badge>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Start</div>
                    <div class="modrik-code mt-2 text-lg font-semibold" dir="ltr">{{ $status['quiet_hours_start'] }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">End</div>
                    <div class="modrik-code mt-2 text-lg font-semibold" dir="ltr">{{ $status['quiet_hours_end'] }}</div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <x-filament::button tag="a" href="/admin/system-settings">
                {{ $locale === 'ar' ? 'إدارة الإعدادات والإصدارات' : ($locale === 'fr' ? 'Gérer les paramètres et versions' : 'Manage settings & versions') }}
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
