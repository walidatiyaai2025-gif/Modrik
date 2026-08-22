<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($rows = $this->rows())
    @php($auditRows = $this->auditRows())
    @php($boards = $this->boardOptions())
    @php($syllabi = $this->syllabusOptions())
    @php($years = $this->yearLevelOptions())
    @php($newOption = $this->newOptionValue())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-academic-catalogue">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="publication-journey-heading">
            <div class="mb-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                    {{ $locale === 'ar' ? 'المسار التشغيلي' : ($locale === 'fr' ? 'Parcours opérationnel' : 'Operator journey') }}
                </p>
                <h2 id="publication-journey-heading" class="mt-2 text-lg font-semibold text-gray-950">
                    {{ $locale === 'ar' ? 'من إنشاء المسار حتى نشر المحتوى' : ($locale === 'fr' ? 'Du parcours à la publication' : 'From academic track to published content') }}
                </h2>
                <p class="mt-1 text-sm leading-6 text-gray-500">
                    {{ $locale === 'ar' ? 'اتبع الخطوات بالترتيب. كل خطوة تسلّم بيانات موثوقة للخطوة التالية ولا تحتاج إلى كتابة أكواد داخلية.' : ($locale === 'fr' ? 'Suivez les étapes dans l’ordre. Chaque étape fournit des données fiables à la suivante sans saisir de codes internes.' : 'Follow the steps in order. Each stage hands trusted data to the next without requiring internal codes.') }}
                </p>
            </div>

            <x-admin.step-rail
                :label="$locale === 'ar' ? 'خطوات نشر المحتوى الرسمي' : ($locale === 'fr' ? 'Étapes de publication officielle' : 'Official content publication steps')"
                :steps="[
                    [
                        'state' => 'active',
                        'label' => $locale === 'ar' ? 'إنشاء أو اختيار المسار الأكاديمي' : ($locale === 'fr' ? 'Créer ou choisir le parcours' : 'Create or select academic track'),
                        'description' => $locale === 'ar' ? 'أدخل أسماء مفهومة؛ الخادم يدير الهوية والمراجع الداخلية.' : ($locale === 'fr' ? 'Saisissez des noms lisibles; le serveur gère les références internes.' : 'Enter readable names; the server owns internal identity and references.'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? 'إعداد المحتوى' : ($locale === 'fr' ? 'Préparer le contenu' : 'Prepare content'),
                        'description' => $locale === 'ar' ? 'اختر المسار المعتمد وحدد المواد وأنواع المحتوى.' : ($locale === 'fr' ? 'Choisissez le parcours approuvé et le contenu requis.' : 'Select the approved track and requested subjects/content types.'),
                        'url' => \App\Filament\Pages\ContentPreparationWizard::getUrl(),
                        'action' => $locale === 'ar' ? 'الانتقال لإعداد المحتوى' : ($locale === 'fr' ? 'Ouvrir la préparation' : 'Open content preparation'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? 'إرجاع ZIP والتحقق' : ($locale === 'fr' ? 'Retour ZIP et validation' : 'Return ZIP and validate'),
                        'description' => $locale === 'ar' ? 'ارفع الحزمة الناتجة لنفس طلب الإعداد ليتم التحقق من الربط والمخطط.' : ($locale === 'fr' ? 'Téléversez le ZIP dans sa demande d’origine pour valider le lien et le schéma.' : 'Upload the returned ZIP to its originating request for binding/schema validation.'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? 'مراجعة الحقوق' : ($locale === 'fr' ? 'Revue des droits' : 'Rights review'),
                        'description' => $locale === 'ar' ? 'المحتوى الحقيقي يظل محجوبًا حتى اعتماد دليل الحقوق عند الحاجة.' : ($locale === 'fr' ? 'Le contenu réel reste bloqué jusqu’à validation des droits si nécessaire.' : 'Real content stays blocked until required rights evidence is approved.'),
                        'url' => \App\Filament\Pages\ContentRightsReview::getUrl(),
                        'action' => $locale === 'ar' ? 'فتح مراجعة الحقوق' : ($locale === 'fr' ? 'Ouvrir les droits' : 'Open rights review'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? 'Dry-run والمراجعة' : ($locale === 'fr' ? 'Dry-run et revue' : 'Dry-run and review'),
                        'description' => $locale === 'ar' ? 'راجع الفروقات والعوائق ثم اعتمد أو ارفض أو اطلب إصلاحًا.' : ($locale === 'fr' ? 'Examinez les différences et blocages avant décision.' : 'Inspect deterministic differences/blockers before approval.'),
                        'url' => \App\Filament\Pages\ContentReviewQueue::getUrl(),
                        'action' => $locale === 'ar' ? 'فتح قائمة المراجعة' : ($locale === 'fr' ? 'Ouvrir la revue' : 'Open review queue'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? 'الاستيراد والاعتماد والنشر' : ($locale === 'fr' ? 'Importer, approuver et publier' : 'Import, approve and publish'),
                        'description' => $locale === 'ar' ? 'لا يُنشر إلا المحتوى المعتمد والحديث بعد الاستيراد كمسودة رسمية.' : ($locale === 'fr' ? 'Seul le contenu approuvé et à jour peut être publié.' : 'Only approved, fresh content can enter canonical import and official publication.'),
                    ],
                ]"
            />
        </section>

        @if ($sourceRequestId)
            <x-admin.operational-banner
                severity="warning"
                :title="$locale === 'ar' ? 'النطاق مأخوذ من طلب إعداد قائم' : ($locale === 'fr' ? 'Périmètre issu d’une demande existante' : 'Scope comes from an existing preparation request')"
                :message="$locale === 'ar' ? 'لن يُطلب منك كتابة أي مرجع. الخادم سيستخدم النطاق الأصلي كما هو حتى يظل الـZIP متطابقًا مع طلبه.' : ($locale === 'fr' ? 'Aucune référence ne doit être saisie. Le serveur conservera exactement le périmètre d’origine.' : 'You do not need to type any reference. The server will preserve the originating scope exactly so the returned ZIP remains compatible.')"
            />
        @endif

        <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(360px,1fr)]">
            <section class="min-w-0 rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="academic-tracks-heading">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 px-5 py-5">
                    <div class="min-w-0">
                        <h2 id="academic-tracks-heading" class="text-lg font-semibold text-gray-950">
                            {{ $locale === 'ar' ? 'المسارات الأكاديمية' : ($locale === 'fr' ? 'Parcours académiques' : 'Academic tracks') }}
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-gray-500">
                            {{ $locale === 'ar' ? 'اعرض أسماء يفهمها فريق المحتوى. الأكواد الداخلية لا تظهر في التشغيل اليومي.' : ($locale === 'fr' ? 'Affichez des noms métier lisibles; les codes internes restent techniques.' : 'Show operator-readable names. Internal codes stay out of the normal workflow.') }}
                        </p>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-wrap justify-end gap-2 sm:flex-none" role="search">
                        <label class="min-w-[12rem] flex-1 sm:flex-none">
                            <span class="sr-only">{{ $locale === 'ar' ? 'بحث في المسارات' : ($locale === 'fr' ? 'Rechercher les parcours' : 'Search tracks') }}</span>
                            <input wire:model.live.debounce.300ms="search" type="search" class="fi-input block w-full rounded-lg border-gray-300 text-sm" placeholder="{{ $locale === 'ar' ? 'ابحث بالاسم أو النطاق...' : ($locale === 'fr' ? 'Rechercher par nom...' : 'Search by name or scope...') }}" />
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
                        :title="$locale === 'ar' ? 'لا توجد مسارات بعد' : ($locale === 'fr' ? 'Aucun parcours' : 'No academic tracks yet')"
                        :message="$locale === 'ar' ? 'ابدأ بإضافة المسار بأسماء مفهومة، ثم انتقل لإعداد المحتوى.' : ($locale === 'fr' ? 'Commencez par créer un parcours lisible puis préparez le contenu.' : 'Start by registering a readable academic track, then continue to content preparation.')"
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المسار' : ($locale === 'fr' ? 'Parcours' : 'Track') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المجلس' : ($locale === 'fr' ? 'Conseil' : 'Board') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المنهج / الإصدار' : ($locale === 'fr' ? 'Programme / version' : 'Syllabus / version') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'المرحلة / السنة' : ($locale === 'fr' ? 'Niveau' : 'Year level') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $locale === 'ar' ? 'الحالة' : ($locale === 'fr' ? 'État' : 'Status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ $locale === 'ar' ? 'إجراء' : ($locale === 'fr' ? 'Action' : 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    <tr class="align-top">
                                        <td class="px-4 py-3 font-semibold text-gray-950">{{ $row['title'][$locale] ?? $row['title']['en'] ?? ($locale === 'ar' ? 'مسار أكاديمي' : 'Academic track') }}</td>
                                        <td class="px-4 py-3">{{ $row['board_label'] }}</td>
                                        <td class="px-4 py-3">{{ $row['syllabus_label'] }}</td>
                                        <td class="px-4 py-3">{{ $row['year_label'] }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge :color="$row['is_fixture'] ? 'gray' : 'success'">
                                                    {{ $row['is_fixture'] ? ($locale === 'ar' ? 'اختباري' : 'Fixture') : ($locale === 'ar' ? 'معتمد' : ($locale === 'fr' ? 'Approuvé' : 'Approved')) }}
                                                </x-filament::badge>
                                                @if ($row['locked'])
                                                    <x-filament::badge color="warning">{{ $locale === 'ar' ? 'مقفل تاريخيًا' : ($locale === 'fr' ? 'Historique verrouillé' : 'History locked') }}</x-filament::badge>
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
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">{{ $locale === 'ar' ? 'إدارة الهيكل الأكاديمي' : ($locale === 'fr' ? 'Gestion académique' : 'Academic management') }}</p>
                    <h2 id="academic-track-form-heading" class="mt-2 text-lg font-semibold text-gray-950">
                        {{ $editingId ? ($locale === 'ar' ? 'تعديل بيانات المسار' : ($locale === 'fr' ? 'Modifier le parcours' : 'Edit academic track')) : ($locale === 'ar' ? 'إضافة مسار أكاديمي' : ($locale === 'fr' ? 'Ajouter un parcours' : 'Add academic track')) }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ $locale === 'ar' ? 'لن تُدخل Track Reference أو أي كود داخلي. اكتب ما يفهمه فريق المحتوى فقط.' : ($locale === 'fr' ? 'Aucun code interne n’est demandé. Saisissez uniquement les données métier lisibles.' : 'No Track Reference or internal code is required. Enter business-readable data only.') }}
                    </p>
                </div>

                <form wire:submit="save" class="mt-5 space-y-5">
                    @if ($sourceRequestId)
                        <div class="rounded-xl border border-info-200 bg-info-50/40 p-4 text-sm">
                            <div class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'النطاق الأكاديمي المطلوب' : ($locale === 'fr' ? 'Périmètre académique requis' : 'Required academic scope') }}</div>
                            <dl class="mt-3 space-y-2 text-gray-700">
                                <div><dt class="font-medium">{{ $locale === 'ar' ? 'المجلس' : 'Board' }}</dt><dd>{{ $this->displayReference((string) ($form['board_reference'] ?? '')) }}</dd></div>
                                <div><dt class="font-medium">{{ $locale === 'ar' ? 'المنهج / الإصدار' : 'Syllabus / version' }}</dt><dd>{{ $this->displayReference((string) ($form['syllabus_version'] ?? '')) }}</dd></div>
                                <div><dt class="font-medium">{{ $locale === 'ar' ? 'المرحلة / السنة' : 'Year level' }}</dt><dd>{{ $this->displayReference((string) ($form['year_level'] ?? '')) }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'هذه القيم تُقرأ من الطلب الأصلي ويعيد الخادم التحقق منها عند الحفظ؛ لا يمكن تزويرها من الواجهة.' : ($locale === 'fr' ? 'Ces valeurs viennent de la demande d’origine et sont revérifiées côté serveur.' : 'These values come from the originating request and are re-validated server-side on save.') }}</p>
                        </div>
                    @else
                        <label class="block">
                            <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'المجلس / الجهة التعليمية' : ($locale === 'fr' ? 'Conseil / autorité' : 'Board / education authority') }}</span>
                            <select wire:model.live="form.board_reference" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                                <option value="">{{ $locale === 'ar' ? 'اختر من القيم المسجلة' : ($locale === 'fr' ? 'Choisir une valeur enregistrée' : 'Choose a registered value') }}</option>
                                @foreach ($boards as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                                <option value="{{ $newOption }}">{{ $locale === 'ar' ? '+ إضافة جهة جديدة' : ($locale === 'fr' ? '+ Ajouter une nouvelle autorité' : '+ Add a new authority') }}</option>
                            </select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'اختر جهة موجودة لتجنب أخطاء المراجع. مثال: وزارة التربية – الكويت.' : ($locale === 'fr' ? 'Choisissez une autorité existante. Exemple : Ministère de l’Éducation – Koweït.' : 'Choose an existing authority to prevent reference errors. Example: Kuwait Ministry of Education.') }}</p>
                            @error('form.board_reference')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                        @if (($form['board_reference'] ?? '') === $newOption)
                            <label class="block">
                                <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'اسم الجهة الجديدة' : ($locale === 'fr' ? 'Nom de la nouvelle autorité' : 'New authority name') }}</span>
                                <input wire:model="form.new_board_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                                <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'اكتب الاسم كما تريد أن يراه فريق العمل. مثال: وزارة التربية والتعليم الكويتية.' : ($locale === 'fr' ? 'Saisissez le nom métier lisible. Exemple : Ministère de l’Éducation du Koweït.' : 'Enter the readable business name. Example: Kuwait Ministry of Education.') }}</p>
                                @error('form.new_board_label')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                            </label>
                        @endif

                        <label class="block">
                            <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'المنهج / الإصدار' : ($locale === 'fr' ? 'Programme / version' : 'Syllabus / version') }}</span>
                            <select wire:model.live="form.syllabus_version" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                                <option value="">{{ $locale === 'ar' ? 'اختر من القيم المسجلة' : ($locale === 'fr' ? 'Choisir une valeur enregistrée' : 'Choose a registered value') }}</option>
                                @foreach ($syllabi as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                                <option value="{{ $newOption }}">{{ $locale === 'ar' ? '+ إضافة منهج/إصدار جديد' : ($locale === 'fr' ? '+ Ajouter un programme/version' : '+ Add a syllabus/version') }}</option>
                            </select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'اختر الإصدار المعتمد. مثال: المنهج الوطني 2026.' : ($locale === 'fr' ? 'Choisissez la version approuvée. Exemple : Programme national 2026.' : 'Choose the approved version. Example: National Curriculum 2026.') }}</p>
                            @error('form.syllabus_version')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                        @if (($form['syllabus_version'] ?? '') === $newOption)
                            <label class="block">
                                <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'اسم المنهج / الإصدار الجديد' : ($locale === 'fr' ? 'Nom du programme/version' : 'New syllabus/version name') }}</span>
                                <input wire:model="form.new_syllabus_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                                <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'مثال: منهج الصف السادس – إصدار 2026.' : ($locale === 'fr' ? 'Exemple : Programme 6e – édition 2026.' : 'Example: Grade 6 Curriculum — 2026 edition.') }}</p>
                                @error('form.new_syllabus_label')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                            </label>
                        @endif

                        <label class="block">
                            <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'المرحلة / السنة الدراسية' : ($locale === 'fr' ? 'Niveau / année' : 'Year level') }}</span>
                            <select wire:model.live="form.year_level" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                                <option value="">{{ $locale === 'ar' ? 'اختر المرحلة' : ($locale === 'fr' ? 'Choisir le niveau' : 'Choose a year level') }}</option>
                                @foreach ($years as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                                <option value="{{ $newOption }}">{{ $locale === 'ar' ? '+ إضافة مرحلة جديدة' : ($locale === 'fr' ? '+ Ajouter un niveau' : '+ Add a new year level') }}</option>
                            </select>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'اختر قيمة مسجلة أو أضف اسمًا واضحًا. مثال: الصف السادس.' : ($locale === 'fr' ? 'Choisissez une valeur enregistrée. Exemple : 6e année.' : 'Choose a registered value or add a readable one. Example: Grade 6.') }}</p>
                            @error('form.year_level')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                        @if (($form['year_level'] ?? '') === $newOption)
                            <label class="block">
                                <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'اسم المرحلة الجديدة' : ($locale === 'fr' ? 'Nom du nouveau niveau' : 'New year-level name') }}</span>
                                <input wire:model="form.new_year_level_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                                <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'مثال: الصف السادس الابتدائي.' : ($locale === 'fr' ? 'Exemple : 6e année primaire.' : 'Example: Grade 6 Primary.') }}</p>
                                @error('form.new_year_level_label')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                            </label>
                        @endif
                    @endif

                    @foreach ([
                        'title_en' => ['Track name in English', 'اسم المسار بالإنجليزية', 'Nom du parcours en anglais', 'Example: Kuwait Grade 6 National Curriculum'],
                        'title_ar' => ['Track name in Arabic', 'اسم المسار بالعربية', 'Nom du parcours en arabe', 'مثال: المنهج الوطني الكويتي – الصف السادس'],
                        'title_fr' => ['Track name in French', 'اسم المسار بالفرنسية', 'Nom du parcours en français', 'Exemple : Programme national du Koweït – 6e année'],
                    ] as $field => $labels)
                        <label class="block">
                            <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? $labels[1] : ($locale === 'fr' ? $labels[2] : $labels[0]) }}</span>
                            <input wire:model="form.{{ $field }}" type="text" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $labels[3] }}</p>
                            @error('form.'.$field)<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach

                    <label class="block rounded-lg border border-gray-200 px-3 py-3 text-sm">
                        <span class="flex min-h-8 items-center gap-3">
                            <input wire:model="form.is_fixture" type="checkbox" />
                            <span class="font-medium">{{ $locale === 'ar' ? 'بيانات اختبار فقط' : ($locale === 'fr' ? 'Données de test uniquement' : 'Fixture / synthetic data only') }}</span>
                        </span>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'فعّلها فقط للمسارات التجريبية غير الحقيقية. مثال: مسار مخصص لاختبارات CI.' : ($locale === 'fr' ? 'Activez uniquement pour les données synthétiques. Exemple : parcours CI.' : 'Enable only for synthetic/testing tracks. Example: a CI-only fixture track.') }}</p>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-900">{{ $locale === 'ar' ? 'سبب الإضافة أو التعديل' : ($locale === 'fr' ? 'Motif de la modification' : 'Reason for this change') }}</span>
                        <textarea wire:model="form.reason" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></textarea>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $locale === 'ar' ? 'اكتب سببًا يصلح للمراجعة لاحقًا. مثال: اعتماد مسار الصف السادس للعام الدراسي 2026.' : ($locale === 'fr' ? 'Indiquez un motif auditable. Exemple : approbation du parcours 6e pour 2026.' : 'Enter an auditable reason. Example: Approved Grade 6 track for the 2026 academic year.') }}</p>
                        @error('form.reason')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                    </label>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button type="submit">{{ $locale === 'ar' ? 'حفظ المسار' : ($locale === 'fr' ? 'Enregistrer le parcours' : 'Save academic track') }}</x-filament::button>
                        @if ($editingId)
                            <x-filament::button type="button" color="gray" wire:click="cancelEdit">{{ $locale === 'ar' ? 'إلغاء' : ($locale === 'fr' ? 'Annuler' : 'Cancel') }}</x-filament::button>
                        @endif
                    </div>
                </form>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="academic-audit-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="academic-audit-heading" class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'سجل التدقيق' : ($locale === 'fr' ? 'Historique d’audit' : 'Audit history') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'آخر تغييرات الكتالوج، مع المنفذ والسبب والتوقيت.' : ($locale === 'fr' ? 'Dernières modifications avec acteur, motif et horodatage.' : 'Recent catalogue changes with actor, reason and timestamp.') }}</p>
            </div>
            <x-admin.audit-timeline :items="$auditRows" :empty-title="$locale === 'ar' ? 'لا توجد تغييرات مسجلة بعد' : ($locale === 'fr' ? 'Aucune modification auditée' : 'No audited changes yet')" />
        </section>
    </div>
</x-filament-panels::page>
