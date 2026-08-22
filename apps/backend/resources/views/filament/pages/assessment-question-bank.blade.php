<x-filament-panels::page>
    <div
        data-testid="modrik-assessment-question-bank"
        dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
    >
        @php
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $t = static fn (string $en, string $ar, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
            $questions = $this->questionRows();
            $quizzes = $this->quizRows();
            $trackOptions = $this->trackOptions();
            $subjectOptions = $this->subjectOptions();
            $quizOptions = $this->quizOptions();
            $publishedCount = collect($questions)->where('status', 'published')->count();
        @endphp

        <x-filament::section>
            <div class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-4xl">
                        <h2 class="text-base font-semibold">
                            {{ $t('Where published questions and answers live', 'مكان الأسئلة والإجابات بعد النشر', 'Où consulter les questions et réponses publiées') }}
                        </h2>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ $t(
                                'Use this page after official content publication to inspect the question bank by academic track, subject and assessment. Correct answers and explanations are visible to authorized Admin/Content operators only.',
                                'استخدم هذه الصفحة بعد النشر الرسمي للمحتوى لمراجعة بنك الأسئلة حسب المسار الأكاديمي والمادة والاختبار. تظهر الإجابات الصحيحة والشرح فقط للمشرفين وفريق المحتوى المصرح لهم.',
                                'Utilisez cette page après publication officielle pour consulter la banque par parcours, matière et évaluation. Les réponses correctes et explications sont réservées aux opérateurs autorisés.'
                            ) }}
                        </p>
                    </div>
                    <x-filament::badge color="success">
                        {{ $t('Read / verify', 'عرض ومراجعة', 'Lecture / vérification') }}
                    </x-filament::badge>
                </div>

                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm dark:border-warning-500/20 dark:bg-warning-500/5">
                    <strong>{{ $t('Assessment authority remains locked.', 'سلطة التقييم تظل محمية.', 'L’autorité d’évaluation reste verrouillée.') }}</strong>
                    <p class="mt-1">
                        {{ $t(
                            'There is intentionally no control here for attempt seed, student-specific selected set/order, resume order, or an attempt scoring snapshot.',
                            'لا توجد عمدًا أي أدوات هنا لتحديد بذرة المحاولة أو مجموعة/ترتيب أسئلة طالب بعينه أو ترتيب الاستكمال أو لقطة تصحيح المحاولة.',
                            'Aucun contrôle n’est volontairement exposé ici pour la graine, la sélection/l’ordre propre à une tentative, l’ordre de reprise ou le snapshot de notation.'
                        ) }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Find questions', 'البحث في بنك الأسئلة', 'Rechercher dans la banque') }}</x-slot>
            <x-slot name="description">
                {{ $t('Database-backed scope is always selected from lists; operators never type track, subject or quiz IDs.', 'يتم اختيار نطاق قاعدة البيانات دائمًا من القوائم؛ لا يكتب المستخدم معرف المسار أو المادة أو الاختبار يدويًا.', 'Le périmètre provenant de la base est toujours choisi dans des listes ; aucun identifiant interne n’est saisi manuellement.') }}
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Academic track', 'المسار الأكاديمي', 'Parcours académique') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="trackId">
                            <option value="">{{ $t('All available tracks', 'كل المسارات المتاحة', 'Tous les parcours disponibles') }}</option>
                            @foreach ($trackOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">{{ $t('Example: Kuwait Grade 6 National Curriculum.', 'مثال: المنهج الوطني الكويتي للصف السادس.', 'Exemple : Programme national du Koweït — 6e année.') }}</span>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Subject', 'المادة', 'Matière') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="subjectNodeId">
                            <option value="">{{ $t('All subjects', 'كل المواد', 'Toutes les matières') }}</option>
                            @foreach ($subjectOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">{{ $t('Choose a published subject from the selected track.', 'اختر مادة منشورة من المسار المحدد.', 'Choisissez une matière publiée du parcours sélectionné.') }}</span>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Assessment / practice', 'الاختبار / التدريب', 'Évaluation / entraînement') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="quizId">
                            <option value="">{{ $t('All assessments', 'كل الاختبارات', 'Toutes les évaluations') }}</option>
                            @foreach ($quizOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">{{ $t('Choose a quiz to see only its question membership.', 'اختر اختبارًا لعرض الأسئلة الموجودة فيه فقط.', 'Choisissez un quiz pour afficher uniquement ses questions.') }}</span>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Question type', 'نوع السؤال', 'Type de question') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="questionType">
                            <option value="all">{{ $t('All types', 'كل الأنواع', 'Tous les types') }}</option>
                            <option value="single_choice">{{ $t('Single choice', 'اختيار من متعدد', 'Choix unique') }}</option>
                            <option value="short_text">{{ $t('Short text', 'إجابة قصيرة', 'Réponse courte') }}</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Publication state', 'حالة النشر', 'État de publication') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="statusFilter">
                            <option value="all">{{ $t('All states', 'كل الحالات', 'Tous les états') }}</option>
                            <option value="published">{{ $t('Published', 'منشور', 'Publié') }}</option>
                            <option value="draft">{{ $t('Draft', 'مسودة', 'Brouillon') }}</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="block space-y-2">
                    <span class="text-sm font-medium">{{ $t('Search question text', 'البحث في نص السؤال', 'Rechercher dans le texte') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.250ms="search"
                            placeholder="{{ $t('e.g. review step', 'مثال: خطوة المراجعة', 'ex. étape de révision') }}"
                        />
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">{{ $t('Search by visible prompt, explanation or curriculum label.', 'ابحث بنص السؤال أو الشرح أو اسم جزء المنهج الظاهر.', 'Recherchez par énoncé, explication ou libellé du programme.') }}</span>
                </label>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="modrik-metric-card">
                <div class="modrik-metric-label">{{ $t('Questions shown', 'الأسئلة المعروضة', 'Questions affichées') }}</div>
                <div class="modrik-metric-value">{{ count($questions) }}</div>
                <div class="modrik-metric-meta">{{ $t('Filtered question bank', 'حسب الفلاتر الحالية', 'Selon les filtres actuels') }}</div>
            </div>
            <div class="modrik-metric-card" data-tone="success">
                <div class="modrik-metric-label">{{ $t('Published', 'منشور', 'Publié') }}</div>
                <div class="modrik-metric-value">{{ $publishedCount }}</div>
                <div class="modrik-metric-meta">{{ $t('Officially available records', 'سجلات متاحة رسميًا', 'Enregistrements officiellement disponibles') }}</div>
            </div>
            <div class="modrik-metric-card">
                <div class="modrik-metric-label">{{ $t('Assessments shown', 'الاختبارات المعروضة', 'Évaluations affichées') }}</div>
                <div class="modrik-metric-value">{{ count($quizzes) }}</div>
                <div class="modrik-metric-meta">{{ $t('Practice / quiz catalogue', 'كتالوج التدريب والاختبارات', 'Catalogue entraînement / quiz') }}</div>
            </div>
            <div class="modrik-metric-card">
                <div class="modrik-metric-label">{{ $t('Answer visibility', 'عرض الإجابات', 'Visibilité des réponses') }}</div>
                <div class="modrik-metric-value text-xl">{{ $t('Authorized', 'مصرح', 'Autorisé') }}</div>
                <div class="modrik-metric-meta">{{ $t('Admin / Content Team only', 'للمشرف وفريق المحتوى فقط', 'Admin / Équipe contenu uniquement') }}</div>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Question bank', 'بنك الأسئلة', 'Banque de questions') }}</x-slot>
            <x-slot name="description">{{ $t('Open any question to inspect choices, the approved answer, explanation and assessment membership.', 'افتح أي سؤال لمراجعة الاختيارات والإجابة المعتمدة والشرح والاختبارات التي ينتمي إليها.', 'Ouvrez une question pour consulter les choix, la réponse approuvée, l’explication et ses évaluations.') }}</x-slot>

            @if ($questions === [])
                <div class="py-12 text-center text-sm text-gray-500" role="status">
                    <p>{{ $t('No questions match these filters.', 'لا توجد أسئلة مطابقة لهذه الفلاتر.', 'Aucune question ne correspond à ces filtres.') }}</p>
                    <p class="mt-2">{{ $t('Choose another track/subject or clear the search.', 'اختر مسارًا أو مادة أخرى أو امسح البحث.', 'Choisissez un autre parcours/matière ou effacez la recherche.') }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($questions as $index => $question)
                        <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5" wire:key="question-bank-{{ $question['id'] }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge color="gray">{{ $t('Question', 'سؤال', 'Question') }} {{ $index + 1 }}</x-filament::badge>
                                        <x-filament::badge color="primary">{{ $question['type_label'] }}</x-filament::badge>
                                        <x-filament::badge :color="$question['status'] === 'published' ? 'success' : 'warning'">
                                            {{ $question['status'] === 'published' ? $t('Published', 'منشور', 'Publié') : $question['status'] }}
                                        </x-filament::badge>
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold leading-7" dir="auto">{{ $question['prompt'] }}</h3>
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ $question['track_title'] }} · {{ $question['subject_title'] }} · {{ $question['node_title'] }}
                                    </p>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <div>{{ $t('Score', 'الدرجة', 'Score') }}: <strong>{{ $question['maximum_score'] }}</strong></div>
                                    <div class="mt-1">{{ $t('Content version', 'إصدار المحتوى', 'Version contenu') }}: v{{ $question['content_version'] }}</div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/5">
                                <div class="text-xs font-semibold uppercase tracking-wide text-success-700 dark:text-success-300">
                                    {{ $t('Approved answer', 'الإجابة المعتمدة', 'Réponse approuvée') }}
                                </div>
                                <div class="mt-2 text-sm font-semibold" dir="auto">{{ $question['answer_summary'] }}</div>
                            </div>

                            @if ($question['options'] !== [])
                                <div class="mt-4 grid gap-2 md:grid-cols-2">
                                    @foreach ($question['options'] as $option)
                                        <div class="flex items-start gap-3 rounded-lg border p-3 {{ $option['correct'] ? 'border-success-300 bg-success-50 dark:border-success-500/30 dark:bg-success-500/5' : 'border-gray-200 dark:border-white/10' }}">
                                            <span aria-hidden="true" class="mt-0.5">{{ $option['correct'] ? '✓' : '○' }}</span>
                                            <div class="min-w-0">
                                                <div dir="auto" class="text-sm">{{ $option['label'] }}</div>
                                                @if ($option['correct'])
                                                    <div class="mt-1 text-xs font-medium text-success-700 dark:text-success-300">{{ $t('Correct answer', 'الإجابة الصحيحة', 'Bonne réponse') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                <strong>{{ $t('Explanation', 'الشرح', 'Explication') }}</strong>
                                <p class="mt-1" dir="auto">{{ $question['explanation'] !== '' ? $question['explanation'] : '—' }}</p>
                            </div>

                            <div class="mt-4">
                                <strong class="text-sm">{{ $t('Used in assessments', 'مستخدم في الاختبارات', 'Utilisée dans les évaluations') }}</strong>
                                @if ($question['quizzes'] === [])
                                    <p class="mt-1 text-sm text-gray-500">{{ $t('Not currently assigned to a quiz.', 'غير مضاف حاليًا إلى اختبار.', 'Non affectée actuellement à un quiz.') }}</p>
                                @else
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($question['quizzes'] as $membership)
                                            <x-filament::badge color="gray">{{ $membership['title'] }} · {{ $membership['kind'] }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <details class="mt-4 rounded-lg border border-gray-200 p-3 text-xs dark:border-white/10">
                                <summary class="cursor-pointer font-medium">{{ $t('Technical traceability', 'التتبع التقني', 'Traçabilité technique') }}</summary>
                                <dl class="mt-3 grid gap-2 md:grid-cols-2">
                                    <div><dt class="font-medium">Question ID</dt><dd class="break-all font-mono">{{ $question['id'] }}</dd></div>
                                    <div><dt class="font-medium">Curriculum node</dt><dd class="break-all font-mono">{{ $question['node_code'] }}</dd></div>
                                    <div><dt class="font-medium">Node type</dt><dd>{{ $question['node_type'] }}</dd></div>
                                    <div><dt class="font-medium">Updated</dt><dd>{{ $question['updated_at'] }}</dd></div>
                                </dl>
                                <div class="mt-3">
                                    <div class="font-medium">Answer contract</div>
                                    <pre class="mt-1 max-w-full overflow-auto whitespace-pre-wrap break-words rounded bg-gray-950 p-3 text-gray-100" dir="ltr">{{ json_encode($question['answer_contract'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            </details>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Assessment and practice catalogue', 'كتالوج الاختبارات والتدريب', 'Catalogue des évaluations et entraînements') }}</x-slot>
            <x-slot name="description">{{ $t('This is the current implemented quiz/practice catalogue. A separate exam entity is not invented when the Backend does not define one.', 'هذا هو كتالوج الاختبارات والتدريبات الموجود فعليًا في النظام. لا يتم اختراع كيان اختبار منفصل إذا لم يعرّفه الـBackend.', 'Il s’agit du catalogue quiz/entraînement réellement implémenté. Aucune entité examen séparée n’est inventée si le Backend ne la définit pas.') }}</x-slot>

            @if ($quizzes === [])
                <div class="py-10 text-center text-sm text-gray-500" role="status">{{ $t('No assessments match these filters.', 'لا توجد اختبارات مطابقة لهذه الفلاتر.', 'Aucune évaluation ne correspond à ces filtres.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 text-start">{{ $t('Assessment', 'الاختبار', 'Évaluation') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Type', 'النوع', 'Type') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Academic scope', 'النطاق الأكاديمي', 'Périmètre académique') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Questions', 'الأسئلة', 'Questions') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('State', 'الحالة', 'État') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Blueprint version', 'إصدار المخطط', 'Version blueprint') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($quizzes as $quiz)
                                <tr wire:key="assessment-catalogue-{{ $quiz['id'] }}">
                                    <td class="px-3 py-3 font-medium" dir="auto">{{ $quiz['title'] }}</td>
                                    <td class="px-3 py-3">{{ $quiz['kind_label'] }}</td>
                                    <td class="px-3 py-3">
                                        <div>{{ $quiz['track_title'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $quiz['year_level'] }} · {{ $quiz['node_title'] }}</div>
                                    </td>
                                    <td class="px-3 py-3">{{ $quiz['question_count'] }}</td>
                                    <td class="px-3 py-3"><x-filament::badge :color="$quiz['status'] === 'published' ? 'success' : 'warning'">{{ $quiz['status'] }}</x-filament::badge></td>
                                    <td class="px-3 py-3">v{{ $quiz['blueprint_version'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
