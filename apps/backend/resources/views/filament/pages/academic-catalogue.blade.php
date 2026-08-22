<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
        @if ($sourceRequestId)
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-700 dark:bg-warning-950/30">
                <strong>{{ $locale === 'ar' ? 'تمت تعبئة النطاق من طلب الإعداد' : ($locale === 'fr' ? 'Périmètre prérempli depuis la demande' : 'Scope prefilled from preparation request') }}</strong>
                <div class="mt-1 font-mono text-xs" dir="ltr">{{ $sourceRequestId }}</div>
                <p class="mt-2">{{ $locale === 'ar' ? 'تحقق من أن القيم معتمدة من المالك قبل الحفظ. لن يغيّر هذا محتوى الـZIP أو يتجاوز فحص النطاق.' : ($locale === 'fr' ? 'Vérifiez que ces valeurs sont approuvées avant l’enregistrement. Cette action ne modifie pas le ZIP et ne contourne pas la validation de portée.' : 'Verify these values are owner-approved before saving. This does not edit the ZIP or bypass scope validation.') }}</p>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $locale === 'ar' ? 'المسارات الأكاديمية' : ($locale === 'fr' ? 'Parcours académiques' : 'Academic tracks') }}</h2>
                        <p class="text-sm text-gray-500">{{ $locale === 'ar' ? 'المسارات الحقيقية منفصلة بوضوح عن بيانات الاختبار.' : ($locale === 'fr' ? 'Les parcours réels sont clairement distingués des données de test.' : 'Real owner-approved tracks are clearly distinguished from fixture data.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <input wire:model.live.debounce.300ms="search" type="search" class="fi-input block rounded-lg border-gray-300 text-sm" placeholder="{{ $locale === 'ar' ? 'بحث...' : ($locale === 'fr' ? 'Rechercher...' : 'Search...') }}" />
                        <select wire:model.live="fixtureFilter" class="fi-select-input rounded-lg border-gray-300 text-sm">
                            <option value="all">{{ $locale === 'ar' ? 'الكل' : ($locale === 'fr' ? 'Tous' : 'All') }}</option>
                            <option value="real">{{ $locale === 'ar' ? 'حقيقي/معتمد' : ($locale === 'fr' ? 'Réel/approuvé' : 'Real / approved') }}</option>
                            <option value="fixture">{{ $locale === 'ar' ? 'اختباري' : ($locale === 'fr' ? 'Fixture' : 'Fixture') }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المرجع' : ($locale === 'fr' ? 'Référence' : 'Reference') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'العنوان' : ($locale === 'fr' ? 'Titre' : 'Title') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المجلس / الإصدار' : ($locale === 'fr' ? 'Board / version' : 'Board / version') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'السنة' : ($locale === 'fr' ? 'Année' : 'Year') }}</th>
                                <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'النوع' : ($locale === 'fr' ? 'Type' : 'Type') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($this->rows() as $row)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs" dir="ltr">{{ $row['code'] }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $row['title'][$locale] ?? $row['title']['en'] ?? $row['code'] }}</td>
                                    <td class="px-4 py-3"><div>{{ $row['board_reference'] ?: '—' }}</div><div class="text-xs text-gray-500">{{ $row['syllabus_version'] ?: '—' }}</div></td>
                                    <td class="px-4 py-3">{{ $row['year_level'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $row['is_fixture'] ? 'bg-gray-100 text-gray-700' : 'bg-success-50 text-success-700' }}">
                                            {{ $row['is_fixture'] ? ($locale === 'ar' ? 'اختباري' : 'Fixture') : ($locale === 'ar' ? 'معتمد' : ($locale === 'fr' ? 'Approuvé' : 'Approved')) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($row['locked'])
                                            <span class="text-xs text-gray-500" title="Historical references make this track read-only">{{ $locale === 'ar' ? 'مقفل تاريخيًا' : ($locale === 'fr' ? 'Verrouillé par historique' : 'History locked') }}</span>
                                        @else
                                            <button wire:click="edit('{{ $row['id'] }}')" class="text-primary-600 hover:underline">{{ $locale === 'ar' ? 'تعديل' : ($locale === 'fr' ? 'Modifier' : 'Edit') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">{{ $locale === 'ar' ? 'لا توجد مسارات مطابقة. أضف مسارًا فقط عند توفر قيم معتمدة.' : ($locale === 'fr' ? 'Aucun parcours correspondant. Ajoutez-en un uniquement avec des valeurs approuvées.' : 'No matching tracks. Add one only when owner-approved values are available.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-lg font-semibold">{{ $editingId ? ($locale === 'ar' ? 'تعديل المسار' : ($locale === 'fr' ? 'Modifier le parcours' : 'Edit track')) : ($locale === 'ar' ? 'تسجيل مسار' : ($locale === 'fr' ? 'Enregistrer un parcours' : 'Register track')) }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    @foreach ([
                        'code' => ['Track reference', 'مرجع المسار', 'Référence du parcours'],
                        'board_reference' => ['Board reference', 'مرجع المجلس', 'Référence du board'],
                        'syllabus_version' => ['Syllabus version', 'إصدار المنهج', 'Version du syllabus'],
                        'year_level' => ['Year level', 'السنة الدراسية', 'Niveau / année'],
                        'title_en' => ['English title', 'العنوان بالإنجليزية', 'Titre anglais'],
                        'title_ar' => ['Arabic title', 'العنوان بالعربية', 'Titre arabe'],
                        'title_fr' => ['French title', 'العنوان بالفرنسية', 'Titre français'],
                    ] as $field => $labels)
                        <label class="block">
                            <span class="text-sm font-medium">{{ $locale === 'ar' ? $labels[1] : ($locale === 'fr' ? $labels[2] : $labels[0]) }}</span>
                            <input wire:model="form.{{ $field }}" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" @if(in_array($field, ['code','board_reference','syllabus_version'], true)) dir="ltr" @endif />
                            @error('form.'.$field)<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                    <label class="flex items-center gap-2 text-sm"><input wire:model="form.is_fixture" type="checkbox" /> {{ $locale === 'ar' ? 'بيانات اختبار/Fixture فقط' : ($locale === 'fr' ? 'Données fixture uniquement' : 'Fixture / synthetic data only') }}</label>
                    <label class="block">
                        <span class="text-sm font-medium">{{ $locale === 'ar' ? 'سبب التغيير (يُسجل في التدقيق)' : ($locale === 'fr' ? 'Motif du changement (audité)' : 'Change reason (audited)') }}</span>
                        <textarea wire:model="form.reason" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></textarea>
                        @error('form.reason')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">{{ $locale === 'ar' ? 'حفظ' : ($locale === 'fr' ? 'Enregistrer' : 'Save') }}</button>
                        @if ($editingId)<button type="button" wire:click="cancelEdit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">{{ $locale === 'ar' ? 'إلغاء' : ($locale === 'fr' ? 'Annuler' : 'Cancel') }}</button>@endif
                    </div>
                </form>
            </section>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">{{ $locale === 'ar' ? 'سجل التدقيق' : ($locale === 'fr' ? 'Historique d’audit' : 'Audit history') }}</h2>
            <div class="mt-3 space-y-3">
                @forelse ($this->auditRows() as $audit)
                    <div class="rounded-lg border border-gray-100 p-3 text-sm dark:border-white/5">
                        <div class="flex flex-wrap justify-between gap-2"><strong>{{ $audit->track_code }} · {{ $audit->action }}</strong><span class="text-xs text-gray-500" dir="ltr">{{ $audit->occurred_at }}</span></div>
                        <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $audit->reason }}</div>
                        <div class="mt-1 text-xs text-gray-500" dir="ltr">{{ $audit->actor_email ?? 'system/removed-user' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ $locale === 'ar' ? 'لا توجد تغييرات مسجلة بعد.' : ($locale === 'fr' ? 'Aucune modification auditée pour le moment.' : 'No audited changes yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
