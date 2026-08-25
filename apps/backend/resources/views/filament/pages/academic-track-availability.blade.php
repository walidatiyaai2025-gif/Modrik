<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($rows = $this->rows())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-academic-track-availability">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <p class="text-sm text-gray-600">
                    {{ $locale === 'ar'
                        ? 'تتحكم هذه الصفحة فيما يظهر للطلاب فقط. سحب المسار لا يحذف أي سجل طالب أو منهج سابق.'
                        : ($locale === 'fr'
                            ? 'Cette page contrôle uniquement la visibilité apprenant. Retirer un parcours ne supprime aucun historique apprenant ou curriculum.'
                            : 'This page controls learner visibility only. Retiring a track never deletes existing learner or curriculum history.') }}
                </p>
            </div>

            @if ($rows === [])
                <x-admin.empty-state
                    :title="$locale === 'ar' ? 'لا توجد مسارات' : ($locale === 'fr' ? 'Aucun parcours' : 'No academic tracks')"
                    :message="$locale === 'ar' ? 'أنشئ المسار أولًا من الكتالوج الأكاديمي.' : ($locale === 'fr' ? 'Créez d’abord un parcours dans le catalogue académique.' : 'Create a track in Academic Catalogue first.')"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المسار' : ($locale === 'fr' ? 'Parcours' : 'Track') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'السنة' : ($locale === 'fr' ? 'Année' : 'Year') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'حالة الإتاحة' : ($locale === 'fr' ? 'Disponibilité' : 'Availability') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'السجل' : ($locale === 'fr' ? 'Historique' : 'History') }}</th>
                                <th class="px-4 py-3 text-end">{{ $locale === 'ar' ? 'إجراء' : ($locale === 'fr' ? 'Action' : 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-950">{{ $row['title'] }}</td>
                                    <td class="px-4 py-3">{{ $row['year'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$row['state'] === 'published' ? 'success' : ($row['state'] === 'retired' ? 'danger' : 'gray')">
                                            {{ $this->stateLabel($row['state']) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($row['has_history'])
                                            <x-filament::badge color="warning">
                                                {{ $locale === 'ar' ? 'له سجل محفوظ' : ($locale === 'fr' ? 'Historique conservé' : 'History preserved') }}
                                            </x-filament::badge>
                                        @else
                                            <span class="text-gray-500">{{ $locale === 'ar' ? 'لا يوجد' : ($locale === 'fr' ? 'Aucun' : 'None') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if ($row['state'] === 'draft')
                                                <x-filament::button size="sm" wire:click="begin('{{ $row['id'] }}', 'published')">
                                                    {{ $locale === 'ar' ? 'نشر' : ($locale === 'fr' ? 'Publier' : 'Publish') }}
                                                </x-filament::button>
                                            @elseif ($row['state'] === 'published')
                                                <x-filament::button size="sm" color="danger" wire:click="begin('{{ $row['id'] }}', 'retired')">
                                                    {{ $locale === 'ar' ? 'سحب' : ($locale === 'fr' ? 'Retirer' : 'Retire') }}
                                                </x-filament::button>
                                            @else
                                                <span class="text-xs text-gray-500">{{ $locale === 'ar' ? 'مسحوب' : ($locale === 'fr' ? 'Retiré' : 'Retired') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($trackId && $targetState)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="lifecycle-confirmation-heading">
                <h2 id="lifecycle-confirmation-heading" class="text-lg font-semibold text-gray-950">
                    {{ $targetState === 'published'
                        ? ($locale === 'ar' ? 'تأكيد نشر المسار' : ($locale === 'fr' ? 'Confirmer la publication' : 'Confirm track publication'))
                        : ($locale === 'ar' ? 'تأكيد سحب المسار' : ($locale === 'fr' ? 'Confirmer le retrait' : 'Confirm track retirement')) }}
                </h2>

                <div class="mt-4 space-y-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'سبب التغيير' : ($locale === 'fr' ? 'Motif du changement' : 'Reason for change') }}</span>
                        <textarea wire:model="reason" rows="3" class="fi-input block w-full rounded-lg border-gray-300" maxlength="500"></textarea>
                        @error('reason') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </label>

                    @if ($targetState === 'retired')
                        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <input type="checkbox" wire:model="confirmHistoricalRetirement" class="mt-1 rounded border-gray-300" />
                            <span class="text-sm leading-6 text-gray-700">
                                {{ $locale === 'ar'
                                    ? 'أؤكد أن سحب المسار يمنع الاختيار الجديد فقط، مع بقاء كل سياقات الطلاب والمنهج التاريخية محفوظة.'
                                    : ($locale === 'fr'
                                        ? 'Je confirme que le retrait bloque uniquement les nouvelles sélections et conserve tout l’historique apprenant et curriculum.'
                                        : 'I confirm retirement blocks new selection only and preserves all existing learner and curriculum history.') }}
                            </span>
                        </label>
                        @error('confirmHistoricalRetirement') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button wire:click="apply" :color="$targetState === 'retired' ? 'danger' : 'primary'">
                            {{ $locale === 'ar' ? 'تطبيق التغيير' : ($locale === 'fr' ? 'Appliquer' : 'Apply change') }}
                        </x-filament::button>
                        <x-filament::button wire:click="cancel" color="gray">
                            {{ $locale === 'ar' ? 'إلغاء' : ($locale === 'fr' ? 'Annuler' : 'Cancel') }}
                        </x-filament::button>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
