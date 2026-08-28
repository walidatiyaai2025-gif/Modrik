<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-smtp-provider-pool">
        <x-admin.operational-banner
            severity="info"
            :title="$locale === 'ar' ? 'إعدادات البريد واختبار SMTP' : ($locale === 'fr' ? 'Configuration et test SMTP' : 'SMTP configuration and testing')"
            :message="$locale === 'ar' ? 'احفظ مزودي البريد أو اختبر الإعدادات الحالية قبل الحفظ. عند الفشل سيظهر سبب آمن ومحدد بدون كشف كلمة المرور.' : ($locale === 'fr' ? 'Enregistrez les fournisseurs ou testez les paramètres actuels avant sauvegarde. Les erreurs sont diagnostiquées sans exposer le mot de passe.' : 'Save providers or test the current values before saving. Failures show a specific safe diagnostic without exposing the password.')"
        />

        @if ($errors->any())
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800" data-testid="smtp-validation-summary">
                <div class="font-semibold">{{ $locale === 'ar' ? 'لم يتم تنفيذ العملية. راجع الحقول التالية:' : ($locale === 'fr' ? 'Opération non exécutée. Vérifiez les champs suivants :' : 'The operation was not completed. Check the highlighted fields:') }}</div>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($lastTestResult !== [])
            <div class="rounded-xl border p-4 text-sm {{ ($lastTestResult['ok'] ?? false) ? 'border-success-200 bg-success-50 text-success-800' : 'border-danger-200 bg-danger-50 text-danger-800' }}" data-testid="smtp-test-result">
                <div class="flex flex-wrap items-center gap-2">
                    <strong>{{ ($lastTestResult['ok'] ?? false) ? ($locale === 'ar' ? 'اختبار ناجح' : 'Test passed') : ($locale === 'ar' ? 'فشل الاختبار' : 'Test failed') }}</strong>
                    <span class="modrik-code" dir="ltr">{{ $lastTestResult['code'] ?? 'UNKNOWN' }}</span>
                </div>
                <p class="mt-2">{{ $lastTestResult['message'] ?? '' }}</p>
                @if (! empty($lastTestResult['detail']))
                    <p class="modrik-code mt-2 break-words text-xs" dir="ltr">{{ $lastTestResult['detail'] }}</p>
                @endif
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(360px,1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-5">
                    <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'مزودو SMTP' : ($locale === 'fr' ? 'Fournisseurs SMTP' : 'SMTP providers') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'المزودون المفعّلون فقط يدخلون مجموعة الإرسال.' : ($locale === 'fr' ? 'Seuls les fournisseurs actifs participent à la livraison.' : 'Only enabled providers participate in delivery.') }}</p>
                </div>

                <div class="border-b border-gray-100 p-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">{{ $locale === 'ar' ? 'بريد اختبار' : ($locale === 'fr' ? 'E-mail de test' : 'Test recipient') }}</span>
                            <input wire:model="testRecipient" type="email" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />
                            @error('testRecipient') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">{{ $locale === 'ar' ? 'سبب التفعيل / التعطيل' : ($locale === 'fr' ? 'Motif activer / désactiver' : 'Enable / disable audit reason') }}</span>
                            <input wire:model="actionReason" type="text" class="fi-input mt-1 block w-full rounded-lg border-gray-300" />
                            @error('actionReason') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                        </label>
                    </div>
                </div>

                @if ($providers === [])
                    <div class="p-6 text-sm text-gray-500">{{ $locale === 'ar' ? 'لا يوجد مزودون بعد. سيستمر النظام في استخدام إعدادات البريد من .env إلى أن تضيف مزودًا مفعّلًا.' : ($locale === 'fr' ? 'Aucun fournisseur. Le système continue d’utiliser la configuration .env jusqu’à l’ajout d’un fournisseur actif.' : 'No managed providers yet. The existing .env mail configuration remains the fallback until an enabled provider is added.') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المزود' : 'Provider' }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'الخادم' : 'Server' }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المرسل' : 'From' }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                                    <th class="px-4 py-3 text-end">{{ $locale === 'ar' ? 'إجراء' : 'Action' }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($providers as $provider)
                                    <tr class="align-top">
                                        <td class="px-4 py-4">
                                            <div class="font-semibold text-gray-950">{{ $provider['name'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500" dir="ltr">{{ $provider['username'] ?: '—' }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $provider['password_set'] ? ($locale === 'ar' ? 'كلمة المرور: محفوظة' : 'Password: Set') : ($locale === 'ar' ? 'كلمة المرور: غير محفوظة' : 'Password: Not set') }}</div>
                                        </td>
                                        <td class="px-4 py-4" dir="ltr">
                                            <div>{{ $provider['host'] }}:{{ $provider['port'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $provider['scheme'] === 'smtps' ? 'SMTPS' : 'STARTTLS / auto TLS' }}</div>
                                        </td>
                                        <td class="px-4 py-4" dir="ltr"><div>{{ $provider['from_address'] }}</div><div class="mt-1 text-xs text-gray-500">{{ $provider['from_name'] }}</div></td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge :color="$provider['is_enabled'] ? 'success' : 'gray'">{{ $provider['is_enabled'] ? ($locale === 'ar' ? 'مفعّل' : 'Enabled') : ($locale === 'ar' ? 'معطّل' : 'Disabled') }}</x-filament::badge>
                                                @if ($provider['last_test_status'])<x-filament::badge :color="$provider['last_test_status'] === 'success' ? 'success' : 'danger'">{{ $provider['last_test_status'] === 'success' ? ($locale === 'ar' ? 'الاختبار ناجح' : 'Test OK') : ($locale === 'ar' ? 'الاختبار فشل' : 'Test failed') }}</x-filament::badge>@endif
                                            </div>
                                            @if ($provider['last_error_code'])<div class="modrik-code mt-2 text-xs text-danger-600" dir="ltr">{{ $provider['last_error_code'] }}</div>@endif
                                        </td>
                                        <td class="px-4 py-4 text-end">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <x-filament::button size="sm" color="gray" wire:click="edit('{{ $provider['id'] }}')">{{ $locale === 'ar' ? 'تعديل' : 'Edit' }}</x-filament::button>
                                                <x-filament::button size="sm" color="gray" wire:click="test('{{ $provider['id'] }}')" wire:loading.attr="disabled" wire:target="test('{{ $provider['id'] }}')">{{ $locale === 'ar' ? 'اختبار' : 'Test' }}</x-filament::button>
                                                <x-filament::button size="sm" :color="$provider['is_enabled'] ? 'danger' : 'success'" wire:click="toggle('{{ $provider['id'] }}')" wire:confirm="{{ $provider['is_enabled'] ? ($locale === 'ar' ? 'تعطيل هذا المزود؟' : 'Disable this provider?') : ($locale === 'ar' ? 'تفعيل هذا المزود؟' : 'Enable this provider?') }}">{{ $provider['is_enabled'] ? ($locale === 'ar' ? 'تعطيل' : 'Disable') : ($locale === 'ar' ? 'تفعيل' : 'Enable') }}</x-filament::button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-950">{{ $editingId ? ($locale === 'ar' ? 'تعديل مزود' : 'Edit provider') : ($locale === 'ar' ? 'إضافة مزود' : 'Add provider') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'على منفذ 587 اختر STARTTLS. على منفذ 465 اختر SMTPS. سبب التغيير مطلوب للحفظ فقط.' : ($locale === 'fr' ? 'Utilisez STARTTLS pour 587 et SMTPS pour 465. Le motif est requis uniquement pour enregistrer.' : 'Use STARTTLS for port 587 and SMTPS for port 465. The audit reason is required only when saving.') }}</p>

                <div class="mt-5 space-y-4">
                    <label class="block"><span class="text-sm font-medium">{{ $locale === 'ar' ? 'اسم المزود' : 'Provider name' }}</span><input wire:model="form.name" type="text" class="fi-input mt-1 block w-full rounded-lg border-gray-300" />@error('form.name')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <label class="block"><span class="text-sm font-medium">Host</span><input wire:model="form.host" type="text" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />@error('form.host')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                        <label class="block"><span class="text-sm font-medium">Port</span><input wire:model="form.port" type="number" min="1" max="65535" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />@error('form.port')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    </div>
                    <label class="block"><span class="text-sm font-medium">{{ $locale === 'ar' ? 'الأمان' : 'Security' }}</span><select wire:model="form.security" class="fi-select-input mt-1 block w-full rounded-lg border-gray-300"><option value="starttls">STARTTLS / auto TLS — 587</option><option value="smtps">SMTPS — 465</option></select>@error('form.security')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm font-medium">Username</span><input wire:model="form.username" type="text" autocomplete="off" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />@error('form.username')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    <label class="block">
                        <span class="text-sm font-medium">Password</span>
                        <input wire:model="form.password" type="password" autocomplete="new-password" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />
                        <p class="mt-1 text-xs text-gray-500">{{ $editingId ? ($locale === 'ar' ? 'اتركها فارغة للاحتفاظ بكلمة المرور الحالية؛ يمكن للاختبار استخدام السر المحفوظ.' : 'Leave blank to keep and test with the saved password.') : ($locale === 'ar' ? 'تُشفّر عند الحفظ ولن تُعرض مرة أخرى.' : 'Encrypted on save and never displayed again.') }}</p>
                        @error('form.password')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block"><span class="text-sm font-medium">From address</span><input wire:model="form.from_address" type="email" class="fi-input mt-1 block w-full rounded-lg border-gray-300" dir="ltr" />@error('form.from_address')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    <label class="block"><span class="text-sm font-medium">From name</span><input wire:model="form.from_name" type="text" class="fi-input mt-1 block w-full rounded-lg border-gray-300" />@error('form.from_name')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror</label>
                    <label class="flex items-center gap-3"><input wire:model="form.is_enabled" type="checkbox" class="fi-checkbox-input rounded border-gray-300" /><span class="text-sm font-medium">{{ $locale === 'ar' ? 'مفعّل ضمن مجموعة الإرسال' : 'Enabled in delivery pool' }}</span></label>
                    <label class="block">
                        <span class="text-sm font-medium">{{ $locale === 'ar' ? 'سبب التغيير' : 'Audit reason' }}</span>
                        <textarea wire:model="form.reason" rows="3" class="fi-input mt-1 block w-full rounded-lg border-gray-300"></textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ $locale === 'ar' ? 'مطلوب عند الحفظ: 8 أحرف على الأقل.' : 'Required on save: at least 8 characters.' }}</p>
                        @error('form.reason')<p class="mt-1 text-xs font-medium text-danger-600">{{ $message }}</p>@enderror
                    </label>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    @if ($editingId)<x-filament::button color="gray" wire:click="cancelEdit">{{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}</x-filament::button>@endif
                    <x-filament::button color="gray" wire:click="testCurrent" wire:loading.attr="disabled" wire:target="testCurrent" data-testid="smtp-test-current">{{ $locale === 'ar' ? 'اختبار الإعدادات الحالية' : ($locale === 'fr' ? 'Tester les paramètres actuels' : 'Test current settings') }}</x-filament::button>
                    <x-filament::button wire:click="save" wire:loading.attr="disabled" wire:target="save" data-testid="smtp-save">{{ $editingId ? ($locale === 'ar' ? 'حفظ التعديل' : 'Save changes') : ($locale === 'ar' ? 'إضافة المزود' : 'Add provider') }}</x-filament::button>
                </div>
            </section>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'سجل التغييرات' : ($locale === 'fr' ? 'Historique des modifications' : 'Configuration audit') }}</h2>
            @if ($audits === [])
                <p class="mt-3 text-sm text-gray-500">{{ $locale === 'ar' ? 'لا توجد أحداث بعد.' : 'No audit events yet.' }}</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'الإجراء' : 'Action' }}</th><th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'المشغل' : 'Actor' }}</th><th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'السبب' : 'Reason' }}</th><th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'الوقت' : 'Time' }}</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($audits as $audit)
                                <tr><td class="px-3 py-2 modrik-code" dir="ltr">{{ $audit['action'] }}</td><td class="px-3 py-2" dir="ltr">{{ $audit['actor'] ?: '—' }}</td><td class="px-3 py-2">{{ $audit['reason'] }}</td><td class="px-3 py-2 modrik-code" dir="ltr">{{ $audit['occurred_at'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
