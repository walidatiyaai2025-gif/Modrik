<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($groups = $this->groupedDefinitions())
    @php($history = $this->selectedHistory())
    @php($confirmRestore = $locale === 'ar' ? 'تأكيد الاستعادة؟ لن يُمسح التاريخ؛ سيتم إنشاء إصدار جديد من القيمة السابقة.' : ($locale === 'fr' ? 'Confirmer la restauration ? L’historique ne sera pas réécrit ; une nouvelle version sera créée.' : 'Confirm restore? History will not be rewritten; a new version will be created from the prior value.'))

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-system-settings">
        <x-admin.operational-banner
            severity="info"
            :title="$locale === 'ar' ? 'نطاق البيئة ثابت للقراءة' : ($locale === 'fr' ? 'Périmètre d’environnement en lecture seule' : 'Read-only environment scope')"
            :message="$locale === 'ar' ? 'هذه الصفحة تدير إعدادات تشغيلية غير سرية فقط. الأسرار ومفاتيح OAuth/Firebase لا تُحفظ هنا.' : ($locale === 'fr' ? 'Cette page gère uniquement des paramètres opérationnels non secrets. Les secrets OAuth/Firebase ne sont jamais stockés ici.' : 'This page manages non-secret operational settings only. OAuth/Firebase secrets are never stored here.')"
        >
            <div class="modrik-code mt-2 text-xs" dir="ltr">APP_ENV={{ $environment }}</div>
        </x-admin.operational-banner>

        @foreach ($groups as $group => $definitions)
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="settings-group-{{ $group }}">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 id="settings-group-{{ $group }}" class="text-lg font-semibold text-gray-950">{{ $this->groupLabel($group) }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ $locale === 'ar' ? 'كل تغيير يحتاج سببًا وتأكيدًا، ويُرفض تلقائيًا إذا أصبحت النسخة المعروضة قديمة.' : ($locale === 'fr' ? 'Chaque modification exige un motif et une confirmation, et est rejetée si la version affichée est devenue obsolète.' : 'Every change requires a reason and confirmation and is rejected if the displayed version has become stale.') }}
                    </p>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($definitions as $key => $definition)
                        @php($stateKey = $this->stateKey($key))
                        <div class="grid min-w-0 gap-4 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,1fr)]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-950">{{ $this->settingLabel($key) }}</h3>
                                    <x-filament::badge color="gray">v{{ $versions[$stateKey] ?? 0 }}</x-filament::badge>
                                    <x-filament::badge color="info">{{ $definition['type'] }}</x-filament::badge>
                                </div>
                                <div class="modrik-code mt-2 break-all text-xs text-gray-500" dir="ltr">{{ $key }}</div>
                                <button type="button" wire:click="selectHistory('{{ $key }}')" class="mt-3 text-sm font-medium text-primary-600 underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                                    {{ $locale === 'ar' ? 'عرض سجل الإصدارات' : ($locale === 'fr' ? 'Voir l’historique des versions' : 'View version history') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                @if ($definition['type'] === 'boolean')
                                    <label class="flex min-h-11 items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <input wire:model="values.{{ $stateKey }}" type="checkbox" />
                                        <span>{{ $locale === 'ar' ? 'مفعّل' : ($locale === 'fr' ? 'Activé' : 'Enabled') }}</span>
                                    </label>
                                @else
                                    <label class="block">
                                        <span class="sr-only">{{ $this->settingLabel($key) }}</span>
                                        <input wire:model="values.{{ $stateKey }}" type="text" class="block w-full rounded-lg border-gray-300 text-sm" @if(str_contains($key, 'hours.')) dir="ltr" @endif />
                                    </label>
                                @endif

                                <label class="block">
                                    <span class="text-xs font-medium text-gray-700">{{ $locale === 'ar' ? 'سبب التغيير' : ($locale === 'fr' ? 'Motif du changement' : 'Change reason') }}</span>
                                    <input wire:model="reasons.{{ $stateKey }}" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" placeholder="{{ $locale === 'ar' ? 'سبب واضح للتدقيق...' : ($locale === 'fr' ? 'Motif clair pour l’audit...' : 'Clear audit reason...' ) }}" />
                                </label>
                                @error('reasons.'.$stateKey)<span class="block text-xs text-danger-600" role="alert">{{ $message }}</span>@enderror
                                @error('values.'.$stateKey)<span class="block text-xs text-danger-600" role="alert">{{ $message }}</span>@enderror

                                @if ($pendingSaveKey === $key)
                                    <div class="rounded-xl border border-warning-200 bg-warning-50 p-3" data-testid="system-setting-save-confirmation">
                                        <p class="text-sm font-semibold text-gray-950">
                                            {{ $locale === 'ar' ? 'تأكيد حفظ هذا التغيير؟' : ($locale === 'fr' ? 'Confirmer l’enregistrement de cette modification ?' : 'Confirm saving this change?') }}
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-gray-600">
                                            {{ $locale === 'ar' ? 'سيتم إنشاء إصدار جديد وتسجيل المنفذ والسبب والتوقيت في سجل التدقيق.' : ($locale === 'fr' ? 'Une nouvelle version sera créée et l’acteur, le motif et l’horodatage seront consignés.' : 'A new version will be created and the actor, reason and timestamp will be audit recorded.') }}
                                        </p>
                                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                                            <x-filament::button type="button" size="sm" color="gray" wire:click="cancelSave">
                                                {{ $locale === 'ar' ? 'إلغاء' : ($locale === 'fr' ? 'Annuler' : 'Cancel') }}
                                            </x-filament::button>
                                            <x-filament::button type="button" size="sm" wire:click="confirmSave" wire:loading.attr="disabled">
                                                {{ $locale === 'ar' ? 'تأكيد الحفظ' : ($locale === 'fr' ? 'Confirmer' : 'Confirm save') }}
                                            </x-filament::button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-filament::button type="button" wire:click="requestSave('{{ $key }}')" wire:loading.attr="disabled">
                                            {{ $locale === 'ar' ? 'حفظ إصدار جديد' : ($locale === 'fr' ? 'Enregistrer une nouvelle version' : 'Save new version') }}
                                        </x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="system-settings-history-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="system-settings-history-heading" class="text-lg font-semibold text-gray-950">
                    {{ $locale === 'ar' ? 'سجل الإصدارات والتدقيق' : ($locale === 'fr' ? 'Versions et historique d’audit' : 'Version & audit history') }}
                </h2>
                <p class="modrik-code mt-1 break-all text-xs text-gray-500" dir="ltr">{{ $selectedKey ?: '—' }}</p>
            </div>

            @if ($history === [])
                <x-admin.empty-state
                    :title="$locale === 'ar' ? 'لا يوجد تاريخ محفوظ بعد' : ($locale === 'fr' ? 'Aucun historique enregistré' : 'No persisted history yet')"
                    :message="$locale === 'ar' ? 'القيم الافتراضية لا تنشئ صفًا حتى يتم أول تغيير معتمد.' : ($locale === 'fr' ? 'Les valeurs par défaut ne créent aucune ligne avant la première modification approuvée.' : 'Defaults do not create a database row until the first approved change.')"
                />
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($history as $item)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge :color="$item['action'] === 'restored' ? 'warning' : 'success'">{{ $item['action'] }}</x-filament::badge>
                                    <span class="text-sm font-semibold text-gray-950">v{{ $item['to_version'] }}</span>
                                </div>
                                <p class="mt-2 text-sm text-gray-700">{{ $item['reason'] }}</p>
                                <div class="modrik-code mt-2 text-xs text-gray-500" dir="ltr">{{ $item['occurred_at'] }} · {{ $item['actor_id'] ?: 'system' }}</div>
                            </div>
                            @if (($versions[$this->stateKey($selectedKey)] ?? 0) !== $item['to_version'])
                                <x-filament::button type="button" size="sm" color="gray" wire:click="restoreSelected({{ $item['to_version'] }})" wire:confirm="{{ $confirmRestore }}">
                                    {{ $locale === 'ar' ? 'استعادة كإصدار جديد' : ($locale === 'fr' ? 'Restaurer comme nouvelle version' : 'Restore as new version') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
