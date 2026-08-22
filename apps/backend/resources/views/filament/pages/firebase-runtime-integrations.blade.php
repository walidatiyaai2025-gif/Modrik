<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-firebase-runtime">
        <x-admin.operational-banner
            :severity="$status['fcm_transport_status'] === 'pending_adapter' ? 'warning' : 'info'"
            :title="$locale === 'ar' ? 'Firebase مساعد وليس مصدر الحقيقة' : ($locale === 'fr' ? 'Firebase est auxiliaire, pas la source de vérité' : 'Firebase is auxiliary, not source of truth')"
            :message="$locale === 'ar' ? 'FCM وRemote Config يمكن تفعيلهما فقط كخدمات مساعدة. Firebase Auth وFirestore وRealtime DB وStorage تظل متوقفة حسب المعمارية الحالية.' : ($locale === 'fr' ? 'FCM et Remote Config peuvent être activés comme services auxiliaires. Firebase Auth, Firestore, Realtime DB et Storage restent désactivés par architecture.' : 'FCM and Remote Config may be enabled only as auxiliary services. Firebase Auth, Firestore, Realtime DB and Storage remain disabled by architecture.')"
        >
            <div class="modrik-code mt-2 text-xs" dir="ltr">APP_ENV={{ $environment }}</div>
        </x-admin.operational-banner>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'project_reference' => 'Project',
                'web_app_reference' => 'Web App',
                'android_app_reference' => 'Android App',
                'ios_app_reference' => 'iOS App',
            ] as $key => $label)
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $label }}</p>
                    <div class="modrik-code mt-3 break-all text-sm font-semibold text-gray-950" dir="ltr">{{ $status[$key] }}</div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="firebase-fcm-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="firebase-fcm-heading" class="text-lg font-semibold text-gray-950">FCM</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'إرسال Push مساعد ولا يجب أن يعطل Core.' : ($locale === 'fr' ? 'Le push est auxiliaire et ne doit jamais bloquer le Core.' : 'Push is auxiliary and must never block Core.') }}</p>
                    </div>
                    <x-filament::badge :color="$status['fcm_enabled'] ? 'success' : 'gray'">{{ $status['fcm_enabled'] ? 'Enabled' : 'Disabled' }}</x-filament::badge>
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-3"><dt class="text-gray-500">Credential reference</dt><dd><x-filament::badge :color="$status['credential_reference_set'] ? 'success' : 'gray'">{{ $status['credential_reference_set'] ? 'Set' : 'Not Set' }}</x-filament::badge></dd></div>
                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-3"><dt class="text-gray-500">Transport</dt><dd><x-filament::badge color="warning">{{ $status['fcm_transport_status'] }}</x-filament::badge></dd></div>
                </dl>

                <form wire:submit="testPush" class="mt-5 space-y-3 rounded-xl bg-gray-50 p-4">
                    <div>
                        <h3 class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'اختبار Push مضبوط' : ($locale === 'fr' ? 'Test Push contrôlé' : 'Controlled Test Push') }}</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'لا يقبل Token خام. فقط مرجع مستخدم/جهاز اختبار مخصص، ولا يتم إرسال شبكة حتى يتوفر Adapter معتمد.' : ($locale === 'fr' ? 'Aucun token brut. Uniquement une référence d’utilisateur/appareil de test ; aucun envoi réseau avant un adaptateur approuvé.' : 'Raw registration tokens are not accepted. Only a designated test user/device reference is allowed, and no network send occurs until an approved adapter exists.') }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[12rem_minmax(0,1fr)]">
                        <select wire:model="targetType" class="rounded-lg border-gray-300 text-sm">
                            <option value="test_user">test_user</option>
                            <option value="test_device">test_device</option>
                        </select>
                        <input wire:model="targetReference" type="text" class="rounded-lg border-gray-300 text-sm" placeholder="TEST-USER-REF" />
                    </div>
                    @error('targetReference')<span class="block text-xs text-danger-600" role="alert">{{ $message }}</span>@enderror
                    @if ($lastTestCode)
                        <div class="modrik-code rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs" dir="ltr">{{ $lastTestCode }}</div>
                    @endif
                    <x-filament::button type="submit" color="gray">{{ $locale === 'ar' ? 'تشغيل فحص الحدود' : ($locale === 'fr' ? 'Exécuter le contrôle' : 'Run boundary check') }}</x-filament::button>
                </form>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="firebase-remote-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="firebase-remote-heading" class="text-lg font-semibold text-gray-950">Remote Config</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'اختياري، وليس بديلًا عن إعدادات Backend الموثقة.' : ($locale === 'fr' ? 'Optionnel, jamais un remplacement des paramètres Backend autoritaires.' : 'Optional and never a replacement for authoritative Backend settings.') }}</p>
                    </div>
                    <x-filament::badge :color="$status['remote_config_enabled'] ? 'success' : 'gray'">{{ $status['remote_config_enabled'] ? 'Enabled' : 'Disabled' }}</x-filament::badge>
                </div>
                <div class="mt-5 rounded-xl bg-gray-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Transport status</div>
                    <div class="modrik-code mt-2 text-sm font-semibold" dir="ltr">{{ $status['remote_config_transport_status'] }}</div>
                </div>
                <div class="mt-5 grid gap-2 text-sm">
                    @foreach (['firebase_auth', 'firestore', 'realtime_database', 'storage'] as $key)
                        <div class="flex justify-between gap-4 border-t border-gray-100 pt-3"><span>{{ str_replace('_', ' ', ucfirst($key)) }}</span><x-filament::badge color="gray">{{ $status[$key] }}</x-filament::badge></div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="flex justify-end"><x-filament::button tag="a" href="/admin/system-settings">{{ $locale === 'ar' ? 'إدارة تفعيل Firebase' : ($locale === 'fr' ? 'Gérer l’activation Firebase' : 'Manage Firebase enablement') }}</x-filament::button></div>
    </div>
</x-filament-panels::page>
