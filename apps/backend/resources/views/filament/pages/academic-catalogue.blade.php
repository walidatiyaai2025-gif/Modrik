<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($rows = $this->rows())
    @php($auditRows = $this->auditRows())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-academic-catalogue">
        @if ($sourceRequestId)
            <x-admin.operational-banner
                severity="warning"
                :title="$locale === 'ar' ? 'تمت تعبئة النطاق من طلب الإعداد' : ($locale === 'fr' ? 'Périmètre prérempli depuis la demande' : 'Scope prefilled from preparation request')"
                :message="$locale === 'ar' ? 'تحقق من أن القيم معتمدة من المالك قبل الحفظ. لن يغيّر هذا محتوى الـZIP أو يتجاوز فحص النطاق.' : ($locale === 'fr' ? 'Vérifiez que ces valeurs sont approuvées avant l’enregistrement. Cette action ne modifie pas le ZIP et ne contourne pas la validation de portée.' : 'Verify these values are owner-approved before saving. This does not edit the ZIP or bypass scope validation.')"
            >
                <div class="modrik-code mt-2 text-xs" dir="ltr">{{ $sourceRequestId }}</div>
            </x-admin.operational-banner>
        @endif

        <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="min-w-0 rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="academic-tracks-heading">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 px-5 py-5">
                    <div class="min-w-0">
                        <h2 id="academic-tracks-heading" class="text-lg font-semibold text-gray-950">
                            {{ $locale === 'ar' ? 'المسارات الأكاديمية' : ($locale === 'fr' ? 'Parcours académiques' : 'Academic tracks') }}
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-gray-500">
                            {{ $locale === 'ar' ? 'المسارات الحقيقية منفصلة بوضوح عن بيانات الاختبار، والمسارات المرتبطة بتاريخ تعليمي تصبح للقراءة فقط.' : ($locale === 'fr' ? 'Les parcours réels sont distingués des fixtures et les parcours liés à un historique deviennent en lecture seule.' : 'Owner-approved tracks are distinct from fixtures, and tracks referenced by learning history become read-only.') }}
                        </p>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-wrap justify-end gap-2 sm:flex-none" role="search">
                        <label class="min-w-[12rem] flex-1 sm:flex-none">
                            <span class="sr-only">{{ $locale === 'ar' ? 'بحث في المسارات' : ($locale === 'fr' ? 'Rechercher les parcours' : 'Search tracks') }}</span>
                            <input
                                wire:model.live.debounce.300ms="search"
                                type="search"
                                class="fi-input block w-full rounded-lg border-gray-300 text-sm"
                                placeholder="{{ $locale === 'ar' ? 'بحث...' : ($locale === 'fr' ? 'Rechercher...' : 'Search...') }}"
                            />
                        </label>
                        <label>
                            <span class="sr-only">{{ $locale === 'ar' ? 'نوع المسار' : ($locale === 'fr' ? 'Type de parcours' : 'Track type') }}</span>
                            <select wire:model.live="fixtureFilter" class="fi-select-input rounded-lg border-gray-300 text-sm">
                                <option value="all">{{ $locale === 'ar' ? 'الكل' : ($locale === 'fr' ? 'Tous' : 'All') }}</option>
                                <option value="real">{{ $locale === 'ar' ? 'حقيقي/معتمد' : ($locale === 'fr' ? 'Réel/approuvé' : 'Real / approved') }}</option>
                                <option value="fixture">{{ $locale === 'ar' ? 'اختباري' : 'Fixture' }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                @if ($rows === [])
                    <x-admin.empty-state
                        :title="$locale === 'ar' ? 'لا توجد مسارات مطابقة' : ($locale === 'fr' ? 'Aucun parcours correspondant' : 'No matching tracks')"
                        :message="$locale === 'ar' ? 'أضف مسارًا فقط عند توفر قيم معتمدة من المالك، أو عدّل مرشحات البحث.' : ($locale === 'fr' ? 'Ajoutez un parcours uniquement avec des valeurs approuvées, ou modifiez les filtres.' : 'Add a track only when owner-approved values are available, or adjust the filters.')"
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المرجع' : ($locale === 'fr' ? 'Référence' : 'Reference') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'العنوان' : ($locale === 'fr' ? 'Titre' : 'Title') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المجلس / الإصدار' : 'Board / version' }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'السنة' : ($locale === 'fr' ? 'Année' : 'Year') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'الحالة' : ($locale === 'fr' ? 'État' : 'Status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ $locale === 'ar' ? 'إجراء' : ($locale === 'fr' ? 'Action' : 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr class="align-top">
                                        <td class="modrik-code px-4 py-3 text-xs" dir="ltr">{{ $row['code'] }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-950">{{ $row['title'][$locale] ?? $row['title']['en'] ?? $row['code'] }}</td>
                                        <td class="px-4 py-3">
                                            <div dir="ltr">{{ $row['board_reference'] ?: '—' }}</div>
                                            <div class="modrik-code mt-1 text-xs text-gray-500" dir="ltr">{{ $row['syllabus_version'] ?: '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3">{{ $row['year_level'] }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge :color="$row['is_fixture'] ? 'gray' : 'success'">
                                                    {{ $row['is_fixture'] ? ($locale === 'ar' ? 'اختباري' : 'Fixture') : ($locale === 'ar' ? 'معتمد' : ($locale === 'fr' ? 'Approuvé' : 'Approved')) }}
                                                </x-filament::badge>
                                                @if ($row['locked'])
                                                    <x-filament::badge color="warning">
                                                        {{ $locale === 'ar' ? 'مقفل تاريخيًا' : ($locale === 'fr' ? 'Historique verrouillé' : 'History locked') }}
                                                    </x-filament::badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            @if ($row['locked'])
                                                <span class="text-xs text-gray-500">{{ $locale === 'ar' ? 'للقراءة فقط' : ($locale === 'fr' ? 'Lecture seule' : 'Read only') }}</span>
                                            @else
                                                <x-filament::button size="sm" color="gray" wire:click="edit('{{ $row['id'] }}')">
                                                    {{ $locale === 'ar' ? 'تعديل' : ($locale === 'fr' ? 'Modifier' : 'Edit') }}
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="academic-track-form-heading">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                        {{ $locale === 'ar' ? 'إدارة الهيكل الأكاديمي' : ($locale === 'fr' ? 'Gestion académique' : 'Academic management') }}
                    </p>
                    <h2 id="academic-track-form-heading" class="mt-2 text-lg font-semibold text-gray-950">
                        {{ $editingId ? ($locale === 'ar' ? 'تعديل المسار' : ($locale === 'fr' ? 'Modifier le parcours' : 'Edit track')) : ($locale === 'ar' ? 'تسجيل مسار' : ($locale === 'fr' ? 'Enregistrer un parcours' : 'Register track')) }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ $locale === 'ar' ? 'لا تحفظ أي قيمة غير معتمدة. التغيير يتطلب سببًا ويُسجل في سجل تدقيق غير قابل للتجاهل.' : ($locale === 'fr' ? 'N’enregistrez aucune valeur non approuvée. Chaque modification exige un motif et est auditée.' : 'Do not save unapproved values. Every change requires a reason and is audit recorded.') }}
                    </p>
                </div>

                <form wire:submit="save" class="mt-5 space-y-4">
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
                            <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? $labels[1] : ($locale === 'fr' ? $labels[2] : $labels[0]) }}</span>
                            <input
                                wire:model="form.{{ $field }}"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                                @if(in_array($field, ['code','board_reference','syllabus_version'], true)) dir="ltr" @endif
                            />
                            @error('form.'.$field)<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach

                    <label class="flex min-h-11 items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <input wire:model="form.is_fixture" type="checkbox" />
                        <span>{{ $locale === 'ar' ? 'بيانات اختبار / Fixture فقط' : ($locale === 'fr' ? 'Données fixture uniquement' : 'Fixture / synthetic data only') }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'سبب التغيير (يُسجل في التدقيق)' : ($locale === 'fr' ? 'Motif du changement (audité)' : 'Change reason (audited)') }}</span>
                        <textarea wire:model="form.reason" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></textarea>
                        @error('form.reason')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                    </label>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button type="submit">
                            {{ $locale === 'ar' ? 'حفظ' : ($locale === 'fr' ? 'Enregistrer' : 'Save') }}
                        </x-filament::button>
                        @if ($editingId)
                            <x-filament::button type="button" color="gray" wire:click="cancelEdit">
                                {{ $locale === 'ar' ? 'إلغاء' : ($locale === 'fr' ? 'Annuler' : 'Cancel') }}
                            </x-filament::button>
                        @endif
                    </div>
                </form>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="academic-audit-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="academic-audit-heading" class="text-lg font-semibold text-gray-950">
                    {{ $locale === 'ar' ? 'سجل التدقيق' : ($locale === 'fr' ? 'Historique d’audit' : 'Audit history') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $locale === 'ar' ? 'آخر تغييرات الكتالوج، مع المنفذ والسبب والتوقيت.' : ($locale === 'fr' ? 'Dernières modifications du catalogue avec acteur, motif et horodatage.' : 'Recent catalogue changes with actor, reason and timestamp.') }}
                </p>
            </div>
            <x-admin.audit-timeline
                :items="$auditRows"
                :empty-title="$locale === 'ar' ? 'لا توجد تغييرات مسجلة بعد' : ($locale === 'fr' ? 'Aucune modification auditée' : 'No audited changes yet')"
            />
        </section>
    </div>
</x-filament-panels::page>
