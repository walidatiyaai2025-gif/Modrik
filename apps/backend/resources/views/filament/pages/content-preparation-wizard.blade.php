<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($trackOptions = $this->academicTrackOptions())
    @php($trackSummary = $this->selectedAcademicTrackSummary())

    <div
        dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
        style="--modrik-brand: {{ config('brand.colors.primary') }}; --modrik-navy: {{ config('brand.colors.navy') }};"
        data-testid="modrik-content-preparation"
    >
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="content-publication-journey">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                        {{ $locale === 'ar' ? 'مسار نشر المحتوى' : ($locale === 'fr' ? 'Parcours de publication' : 'Content publication journey') }}
                    </p>
                    <h2 id="content-publication-journey" class="mt-2 text-lg font-semibold text-gray-950">
                        {{ $locale === 'ar' ? 'اعرف دائمًا أين أنت وما الخطوة التالية' : ($locale === 'fr' ? 'Toujours savoir où vous êtes et la prochaine étape' : 'Always know where you are and what comes next') }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ $locale === 'ar' ? 'الواجهة تعرض أسماء مفهومة فقط؛ المراجع الداخلية والربط بين المراحل مسؤولية الخادم.' : ($locale === 'fr' ? 'L’interface affiche des noms lisibles; les références internes restent gérées par le serveur.' : 'The UI uses readable names only; internal references and cross-stage bindings remain server-owned.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2" aria-label="{{ __('admin.language') }}">
                    @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $language => $label)
                        <x-filament::button size="sm" :color="$locale === $language ? 'primary' : 'gray'" wire:click="setLocale('{{ $language }}')">
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>

            <x-admin.step-rail
                :label="$locale === 'ar' ? 'الخطوات الرسمية لنشر المحتوى' : ($locale === 'fr' ? 'Étapes officielles de publication' : 'Official content publication steps')"
                :steps="[
                    [
                        'state' => $trackOptions === [] ? 'blocked' : 'complete',
                        'label' => $locale === 'ar' ? '1. المسار الأكاديمي' : ($locale === 'fr' ? '1. Parcours académique' : '1. Academic track'),
                        'description' => $trackOptions === []
                            ? ($locale === 'ar' ? 'يجب تسجيل مسار أكاديمي معتمد أولًا.' : ($locale === 'fr' ? 'Créez d’abord un parcours approuvé.' : 'Register an approved academic track first.'))
                            : ($locale === 'ar' ? 'المسارات المسجلة جاهزة للاختيار.' : ($locale === 'fr' ? 'Les parcours enregistrés sont prêts.' : 'Registered tracks are ready for selection.')),
                        'url' => \App\Filament\Pages\AcademicCatalogue::getUrl(),
                        'action' => $locale === 'ar' ? 'فتح الكتالوج الأكاديمي' : ($locale === 'fr' ? 'Ouvrir le catalogue' : 'Open academic catalogue'),
                    ],
                    [
                        'state' => $preparationRequestId === null ? 'active' : 'complete',
                        'label' => $locale === 'ar' ? '2. إعداد المحتوى' : ($locale === 'fr' ? '2. Préparation' : '2. Content preparation'),
                        'description' => $locale === 'ar' ? 'اختر المسار والمواد وأنواع المحتوى ثم أنشئ الحزمة.' : ($locale === 'fr' ? 'Choisissez parcours, matières et types de contenu.' : 'Select track, subjects and content types, then generate the bundle.'),
                    ],
                    [
                        'state' => $preparationRequestId !== null ? 'active' : 'pending',
                        'label' => $locale === 'ar' ? '3. إرجاع ZIP والتحقق' : ($locale === 'fr' ? '3. ZIP et validation' : '3. Return ZIP and validate'),
                        'description' => $locale === 'ar' ? 'أعد الحزمة لنفس الطلب ليتم التحقق من الربط والمخطط.' : ($locale === 'fr' ? 'Retournez le ZIP dans sa demande d’origine.' : 'Return the ZIP to its originating request for binding/schema validation.'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? '4. مراجعة الحقوق' : ($locale === 'fr' ? '4. Droits' : '4. Rights review'),
                        'description' => $locale === 'ar' ? 'اعتماد دليل الحقوق للمحتوى الحقيقي عند الحاجة.' : ($locale === 'fr' ? 'Validez les droits lorsque requis.' : 'Approve required rights evidence for real content.'),
                        'url' => \App\Filament\Pages\ContentRightsReview::getUrl(),
                        'action' => $locale === 'ar' ? 'فتح مراجعة الحقوق' : ($locale === 'fr' ? 'Ouvrir les droits' : 'Open rights review'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? '5. Dry-run والمراجعة' : ($locale === 'fr' ? '5. Dry-run et revue' : '5. Dry-run and review'),
                        'description' => $locale === 'ar' ? 'راجع الفروقات والعوائق ثم اتخذ قرار المراجعة.' : ($locale === 'fr' ? 'Examinez les différences et blocages.' : 'Inspect deterministic differences/blockers and record the review decision.'),
                        'url' => \App\Filament\Pages\ContentReviewQueue::getUrl(),
                        'action' => $locale === 'ar' ? 'فتح قائمة المراجعة' : ($locale === 'fr' ? 'Ouvrir la revue' : 'Open review queue'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $locale === 'ar' ? '6. الاستيراد والاعتماد والنشر' : ($locale === 'fr' ? '6. Importer et publier' : '6. Import, approve and publish'),
                        'description' => $locale === 'ar' ? 'يُستورد المحتوى المعتمد كمسودة رسمية ثم يُنشر دون تجاوز أي بوابة.' : ($locale === 'fr' ? 'Le contenu approuvé est importé puis publié sans contourner les contrôles.' : 'Approved content enters canonical draft import and is then officially published without bypassing gates.'),
                    ],
                ]"
            />
        </section>

        <div class="flex flex-wrap items-center gap-2" aria-label="{{ __('admin.preparation.progress') }}">
            @foreach ([1 => 'scope', 2 => 'academic', 3 => 'generation', 4 => 'bundle'] as $number => $label)
                <x-filament::badge :color="$step >= $number ? 'primary' : 'gray'">
                    {{ $number }}. {{ __('admin.preparation.steps.'.$label) }}
                </x-filament::badge>
            @endforeach
        </div>

        @if ($preparationRequestId !== null)
            <div
                wire:dirty
                wire:target="locales,academicTrackId,subjectNames,contentTypes,includeAnswerExplanations,maximumQuestionsPerQuiz"
                class="rounded-xl border p-4 text-sm"
                style="border-color: var(--modrik-brand)"
                role="status"
            >
                <strong>{{ __('admin.preparation.settings_changed_title') }}</strong>
                <span>{{ __('admin.preparation.settings_changed_body') }}</span>
            </div>
        @endif

        @if (($requestResult['status'] ?? null) === 'superseded')
            <x-filament::section>
                <div role="alert" class="space-y-2">
                    <strong>{{ __('admin.preparation.stale_title') }}</strong>
                    <p>{{ __('admin.preparation.stale_body') }}</p>
                    @if (! empty($requestResult['superseded_by_request_id']))
                        <details class="mt-2 text-xs text-gray-500">
                            <summary class="cursor-pointer font-medium">{{ $locale === 'ar' ? 'بيانات التتبع التقنية' : ($locale === 'fr' ? 'Traçabilité technique' : 'Technical traceability') }}</summary>
                            <div class="mt-2 font-mono break-all" dir="ltr">{{ $requestResult['superseded_by_request_id'] }}</div>
                        </details>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($step === 1)
            <x-filament::section :heading="__('admin.preparation.steps.scope')" :description="__('admin.preparation.scope_help')">
                <div class="grid gap-6 lg:grid-cols-2">
                    <fieldset class="space-y-3">
                        <legend class="font-medium">{{ __('admin.preparation.locales') }}</legend>
                        <p class="text-xs leading-5 text-gray-500">
                            {{ $locale === 'ar' ? 'اختر اللغات التي يجب أن تتوفر في المحتوى. مثال: العربية + الإنجليزية للمحتوى الثنائي اللغة.' : ($locale === 'fr' ? 'Choisissez les langues requises. Exemple : arabe + anglais pour un contenu bilingue.' : 'Choose the languages the content must support. Example: Arabic + English for bilingual material.') }}
                        </p>
                        @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $language => $label)
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border px-3 py-2">
                                <input type="checkbox" value="{{ $language }}" wire:model="locales" class="rounded border-gray-300">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        @error('locales') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="font-medium">{{ __('admin.preparation.content_types') }}</legend>
                        <p class="text-xs leading-5 text-gray-500">
                            {{ $locale === 'ar' ? 'حدد ما تريد إنتاجه في هذه الدفعة. مثال: درس + اختبار تدريبي.' : ($locale === 'fr' ? 'Choisissez ce qui doit être produit. Exemple : leçon + quiz pratique.' : 'Choose what this batch should produce. Example: lesson + practice quiz.') }}
                        </p>
                        @foreach (['lesson', 'practice_quiz', 'mock_exam'] as $type)
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border px-3 py-2">
                                <input type="checkbox" value="{{ $type }}" wire:model="contentTypes" class="rounded border-gray-300">
                                <span>{{ __('admin.content_types.'.$type) }}</span>
                            </label>
                        @endforeach
                        @error('contentTypes') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button wire:click="nextStep" wire:loading.attr="disabled">{{ __('admin.actions.continue') }}</x-filament::button>
                    @if (config('modrik.fixture.enabled'))
                        <x-filament::button color="gray" wire:click="loadSyntheticFixture" wire:loading.attr="disabled">{{ __('admin.actions.load_fixture') }}</x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($step === 2)
            <x-filament::section :heading="__('admin.preparation.steps.academic')" :description="__('admin.preparation.academic_help')">
                @if ($trackOptions === [])
                    <x-admin.operational-banner
                        severity="warning"
                        :title="$locale === 'ar' ? 'لا يوجد مسار أكاديمي متاح' : ($locale === 'fr' ? 'Aucun parcours disponible' : 'No academic track is available')"
                        :message="$locale === 'ar' ? 'أنشئ المسار الأكاديمي أولًا. لن نسمح بكتابة مرجع يدوي لتجاوز هذه الخطوة.' : ($locale === 'fr' ? 'Créez d’abord le parcours. Aucune référence manuelle ne peut contourner cette étape.' : 'Create the academic track first. A raw reference cannot be typed to bypass this step.')"
                    >
                        @if (\App\Filament\Pages\AcademicCatalogue::canAccess())
                            <x-filament::button tag="a" :href="\App\Filament\Pages\AcademicCatalogue::getUrl()" size="sm">
                                {{ $locale === 'ar' ? 'إضافة مسار أكاديمي' : ($locale === 'fr' ? 'Ajouter un parcours' : 'Add academic track') }}
                            </x-filament::button>
                        @endif
                    </x-admin.operational-banner>
                @else
                    <div class="grid gap-5 lg:grid-cols-2">
                        <label class="space-y-2 lg:col-span-2">
                            <span class="font-medium">{{ $locale === 'ar' ? 'المسار الأكاديمي المعتمد' : ($locale === 'fr' ? 'Parcours académique approuvé' : 'Approved academic track') }}</span>
                            <select wire:model.live="academicTrackId" class="fi-select-input block w-full rounded-xl border-gray-300 text-sm">
                                <option value="">{{ $locale === 'ar' ? 'اختر المسار بالاسم' : ($locale === 'fr' ? 'Choisir le parcours par son nom' : 'Choose the track by name') }}</option>
                                @foreach ($trackOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs leading-5 text-gray-500">
                                {{ $locale === 'ar' ? 'اختر المسار الذي سيتبعه المحتوى. مثال: المنهج الوطني الكويتي – الصف السادس. المجلس والمنهج والسنة تُستخرج تلقائيًا من قاعدة البيانات.' : ($locale === 'fr' ? 'Exemple : Programme national du Koweït – 6e année. Les références associées sont dérivées automatiquement.' : 'Choose the track the content belongs to. Example: Kuwait National Curriculum — Grade 6. Board, syllabus and year are derived from the database automatically.') }}
                            </p>
                            @error('academicTrackId') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                        </label>

                        @if ($trackSummary !== null)
                            <div class="lg:col-span-2 rounded-xl border border-info-200 bg-info-50/40 p-4" aria-live="polite">
                                <div class="font-semibold text-gray-950">{{ $trackSummary['title'] }}</div>
                                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                    <div><dt class="font-medium text-gray-600">{{ $locale === 'ar' ? 'المجلس' : 'Board' }}</dt><dd class="mt-1 text-gray-950">{{ $trackSummary['board'] }}</dd></div>
                                    <div><dt class="font-medium text-gray-600">{{ $locale === 'ar' ? 'المنهج / الإصدار' : 'Syllabus / version' }}</dt><dd class="mt-1 text-gray-950">{{ $trackSummary['syllabus'] }}</dd></div>
                                    <div><dt class="font-medium text-gray-600">{{ $locale === 'ar' ? 'المرحلة / السنة' : 'Year level' }}</dt><dd class="mt-1 text-gray-950">{{ $trackSummary['year'] }}</dd></div>
                                </dl>
                                <p class="mt-3 text-xs leading-5 text-gray-500">
                                    {{ $locale === 'ar' ? 'هذه البيانات للعرض فقط. عند إنشاء الطلب يعيد الخادم قراءة المسار من قاعدة البيانات ولا يثق بقيم قادمة من المتصفح.' : ($locale === 'fr' ? 'Ces valeurs sont en lecture seule et sont relues depuis la base au moment de générer la demande.' : 'These values are read-only. The server re-loads the selected track from the database when generating the request and does not trust browser-supplied references.') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <label class="mt-5 block space-y-2">
                        <span class="font-medium">{{ $locale === 'ar' ? 'المواد المطلوبة' : ($locale === 'fr' ? 'Matières demandées' : 'Requested subjects') }}</span>
                        <textarea
                            wire:model="subjectNames"
                            rows="5"
                            class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                            placeholder="{{ $locale === 'ar' ? "الرياضيات\nالعلوم" : ($locale === 'fr' ? "Mathématiques\nSciences" : "Mathematics\nScience") }}"
                        ></textarea>
                        <p class="text-xs leading-5 text-gray-500">
                            {{ $locale === 'ar' ? 'اكتب اسم المادة كما يفهمه فريق المحتوى، مادة في كل سطر. مثال: الرياضيات. الخادم سيولد المرجع الداخلي عند الحاجة.' : ($locale === 'fr' ? 'Saisissez un nom lisible par ligne. Exemple : Mathématiques. Le serveur génère la référence interne.' : 'Enter one readable subject name per line. Example: Mathematics. The server generates the internal subject reference when needed.') }}
                        </p>
                        @error('subjectNames') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </label>
                @endif

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.back') }}</x-filament::button>
                    <x-filament::button wire:click="nextStep" wire:loading.attr="disabled" :disabled="$trackOptions === [] && $preparationRequestId === null">{{ __('admin.actions.continue') }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if ($step === 3)
            <x-filament::section :heading="__('admin.preparation.steps.generation')" :description="__('admin.preparation.generation_help')">
                <div class="grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.maximum_questions') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="1" max="200" wire:model="maximumQuestionsPerQuiz" />
                        </x-filament::input.wrapper>
                        <p class="text-xs leading-5 text-gray-500">
                            {{ $locale === 'ar' ? 'أقصى عدد أسئلة في الاختبار الواحد. مثال: 20 سؤالًا للاختبار التدريبي.' : ($locale === 'fr' ? 'Nombre maximal de questions par quiz. Exemple : 20.' : 'Maximum questions per quiz. Example: 20 questions for a practice quiz.') }}
                        </p>
                        @error('maximumQuestionsPerQuiz') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </label>
                    <label class="rounded-xl border p-4">
                        <span class="flex min-h-11 items-center gap-3">
                            <input type="checkbox" wire:model="includeAnswerExplanations" class="rounded border-gray-300">
                            <span class="font-medium">{{ __('admin.fields.include_explanations') }}</span>
                        </span>
                        <p class="mt-2 text-xs leading-5 text-gray-500">
                            {{ $locale === 'ar' ? 'فعّلها عندما تريد شرح سبب صحة الإجابة. مثال: اختبارات التدريب والمراجعة.' : ($locale === 'fr' ? 'Activez pour expliquer pourquoi une réponse est correcte. Exemple : quiz de révision.' : 'Enable when learners should receive an explanation of the correct answer. Example: practice/revision quizzes.') }}
                        </p>
                    </label>
                </div>

                <div class="mt-5 rounded-xl border p-4 text-sm">
                    <strong>{{ __('admin.preparation.ai_boundary_title') }}</strong>
                    <p>{{ __('admin.preparation.ai_boundary_body') }}</p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.back') }}</x-filament::button>
                    <x-filament::button wire:click="generate" wire:loading.attr="disabled" wire:target="generate,requestRegeneration">
                        {{ $preparationRequestId === null ? __('admin.actions.generate') : __('admin.actions.regenerate') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if ($step === 4)
            <div class="grid gap-6 xl:grid-cols-2">
                <x-filament::section :heading="__('admin.preparation.request_summary')">
                    @if ($requestResult === [])
                        <div class="py-8 text-center text-sm text-gray-500">{{ __('admin.preparation.no_request') }}</div>
                    @else
                        @if ($trackSummary !== null)
                            <div class="mb-4 rounded-xl border border-success-200 bg-success-50/40 p-4 text-sm">
                                <div class="font-semibold text-gray-950">{{ $trackSummary['title'] }}</div>
                                <div class="mt-2 text-gray-600">{{ $trackSummary['board'] }} · {{ $trackSummary['syllabus'] }} · {{ $trackSummary['year'] }}</div>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge color="primary">{{ $requestResult['status'] ?? 'ready' }}</x-filament::badge>
                            <span class="text-sm text-gray-600">{{ $locale === 'ar' ? 'الحزمة جاهزة للعودة والتحقق' : ($locale === 'fr' ? 'Bundle prêt pour retour et validation' : 'Bundle ready for return and validation') }}</span>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <x-filament::button color="gray" wire:click="downloadPrompt">{{ __('admin.actions.download_prompt') }}</x-filament::button>
                            <x-filament::button color="gray" wire:click="downloadBundle">{{ __('admin.actions.download_bundle') }}</x-filament::button>
                            <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.edit_settings') }}</x-filament::button>
                        </div>
                        <details class="mt-5 border-t border-gray-100 pt-4 text-xs text-gray-500">
                            <summary class="cursor-pointer font-medium">{{ $locale === 'ar' ? 'بيانات التتبع التقنية' : ($locale === 'fr' ? 'Traçabilité technique' : 'Technical traceability') }}</summary>
                            <dl class="mt-3 space-y-2" dir="ltr">
                                <div><dt class="font-medium">Request ID</dt><dd class="break-all font-mono">{{ $requestResult['preparation_request_id'] ?? '' }}</dd></div>
                                <div><dt class="font-medium">Schema</dt><dd>{{ $requestResult['schema_version'] ?? '' }}</dd></div>
                                <div><dt class="font-medium">Settings hash</dt><dd class="break-all font-mono">{{ $requestResult['settings_hash'] ?? '' }}</dd></div>
                            </dl>
                        </details>
                    @endif
                </x-filament::section>

                <x-filament::section :heading="__('admin.preparation.returned_zip')" :description="__('admin.preparation.returned_zip_help')">
                    @if ($preparationRequestId !== null)
                        <div class="space-y-4">
                            <input type="file" wire:model="returnedZip" accept=".zip,application/zip" class="block w-full rounded-xl border p-3 text-sm" aria-describedby="zip-help">
                            <p id="zip-help" class="text-xs leading-5 text-gray-500">
                                {{ __('admin.preparation.zip_binding_help') }}
                                {{ $locale === 'ar' ? ' مثال: ارفع ملف ZIP الناتج من نفس Bundle فقط، وليس من طلب آخر.' : ($locale === 'fr' ? ' Exemple : téléversez uniquement le ZIP de ce même bundle.' : ' Example: upload only the ZIP returned for this exact bundle, never one from another request.') }}
                            </p>
                            @error('returnedZip') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                            <x-filament::button wire:click="uploadReturnedZip" wire:loading.attr="disabled" wire:target="returnedZip,uploadReturnedZip">{{ __('admin.actions.validate_zip') }}</x-filament::button>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">{{ __('admin.messages.generate_first') }}</p>
                    @endif
                </x-filament::section>
            </div>

            @if ($requestResult !== [])
                <details class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer font-semibold text-gray-950">{{ $locale === 'ar' ? 'أدوات الحزمة التقنية' : ($locale === 'fr' ? 'Outils techniques du bundle' : 'Technical bundle tools') }}</summary>
                    <div class="mt-4 space-y-5">
                        <div>
                            <div class="mb-2 text-sm font-medium">{{ __('admin.preparation.prompt') }}</div>
                            <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-xl border bg-gray-50 p-4 text-xs dark:bg-white/5" dir="ltr">{{ $requestResult['prompt'] ?? '' }}</pre>
                        </div>
                        <div>
                            <div class="mb-2 text-sm font-medium">{{ __('admin.preparation.bundle') }}</div>
                            <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-xl border bg-gray-50 p-4 text-xs dark:bg-white/5" dir="ltr">{{ json_encode($requestResult['bundle'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                </details>
            @endif

            @if ($validationResult !== [])
                <x-filament::section :heading="__('admin.preparation.validation_result')">
                    @if (($validationResult['accepted'] ?? false) === true)
                        <div class="space-y-4" role="status" aria-live="polite">
                            <x-filament::badge color="success">{{ __('admin.messages.zip_validated') }}</x-filament::badge>
                            <p class="text-sm text-gray-600">
                                {{ $locale === 'ar' ? 'تم التحقق من الحزمة. الخطوة التالية هي مراجعة الحقوق ثم الـDry-run والمراجعة.' : ($locale === 'fr' ? 'Le bundle est validé. Continuez avec les droits puis le dry-run et la revue.' : 'The bundle is validated. Continue with rights review, then deterministic dry-run and review.') }}
                            </p>
                            <dl class="grid gap-3 text-sm md:grid-cols-2">
                                <div><dt class="font-medium">{{ __('admin.fields.files') }}</dt><dd>{{ $validationResult['data']['validated_file_count'] ?? 0 }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.records') }}</dt><dd>{{ $validationResult['data']['validated_record_count'] ?? 0 }}</dd></div>
                            </dl>
                            <div class="flex flex-wrap gap-2">
                                <x-filament::button tag="a" :href="\App\Filament\Pages\ContentRightsReview::getUrl()" color="gray">
                                    {{ $locale === 'ar' ? 'مراجعة الحقوق' : ($locale === 'fr' ? 'Revue des droits' : 'Rights review') }}
                                </x-filament::button>
                                <x-filament::button tag="a" :href="\App\Filament\Pages\ContentReviewQueue::getUrl()">
                                    {{ __('admin.actions.open_review_queue') }}
                                </x-filament::button>
                            </div>
                            <details class="text-xs text-gray-500">
                                <summary class="cursor-pointer font-medium">{{ $locale === 'ar' ? 'معرف الاستيراد التقني' : ($locale === 'fr' ? 'Identifiant technique' : 'Technical import identifier') }}</summary>
                                <div class="mt-2 break-all font-mono" dir="ltr">{{ $validationResult['data']['preparation_import_id'] ?? '' }}</div>
                            </details>
                        </div>
                    @else
                        <div role="alert" class="space-y-3">
                            <x-filament::badge color="danger">{{ __('admin.messages.zip_rejected') }}</x-filament::badge>
                            @foreach (($validationResult['errors'] ?? []) as $error)
                                <div class="rounded-lg border p-3 text-sm">
                                    <strong>{{ $error['code'] ?? 'VALIDATION_ERROR' }}</strong>
                                    <p>{{ $error['message'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endif
        @endif

        <x-filament::modal
            id="confirm-content-regeneration"
            width="2xl"
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            :close-by-clicking-away="false"
            :close-by-escaping="false"
            :close-button="false"
        >
            <x-slot name="heading">{{ __('admin.confirmations.regeneration_title') }}</x-slot>
            <x-slot name="description">{{ __('admin.confirmations.regeneration_description') }}</x-slot>

            <div class="space-y-4 text-sm">
                <div class="rounded-xl border border-warning-300 p-4" role="alert">
                    <strong>{{ __('admin.confirmations.regeneration_consequence_title') }}</strong>
                    <p class="mt-1">{{ __('admin.confirmations.regeneration_consequence_body') }}</p>
                </div>
                <details class="text-xs text-gray-500">
                    <summary class="cursor-pointer font-medium">{{ $locale === 'ar' ? 'بيانات التتبع التقنية' : ($locale === 'fr' ? 'Traçabilité technique' : 'Technical traceability') }}</summary>
                    <dl class="mt-3 space-y-2" dir="ltr">
                        <div><dt class="font-medium">Request ID</dt><dd class="break-all font-mono">{{ $pendingRegenerationRequestId ?? $preparationRequestId ?? '—' }}</dd></div>
                        <div><dt class="font-medium">Status</dt><dd>{{ $requestResult['status'] ?? '—' }}</dd></div>
                        <div><dt class="font-medium">Settings hash</dt><dd class="break-all font-mono">{{ $requestResult['settings_hash'] ?? '—' }}</dd></div>
                    </dl>
                </details>
            </div>

            <x-slot name="footerActions">
                <x-filament::button color="gray" wire:click="cancelRegeneration" wire:loading.attr="disabled" wire:target="confirmRegeneration,cancelRegeneration">{{ __('admin.actions.cancel') }}</x-filament::button>
                <x-filament::button color="danger" wire:click="confirmRegeneration" wire:loading.attr="disabled" wire:target="confirmRegeneration">{{ __('admin.actions.confirm_regeneration') }}</x-filament::button>
            </x-slot>
        </x-filament::modal>

        <div wire:loading.flex class="items-center gap-2 text-sm" aria-live="polite">
            <x-filament::loading-indicator class="h-5 w-5" />
            <span>{{ __('admin.messages.working') }}</span>
        </div>
    </div>
</x-filament-panels::page>
