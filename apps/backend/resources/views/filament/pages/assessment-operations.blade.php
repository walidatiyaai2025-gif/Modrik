<x-filament-panels::page>
    <div
        data-testid="modrik-assessment-operations"
        dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
    >
        @php
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $t = static fn (string $en, string $ar, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
            $assessments = $this->assessmentRows();
            $boundaries = $this->contractBoundaries();
            $published = collect($assessments)->where('status', 'published')->count();
            $inProgress = collect($assessments)->sum('in_progress_attempt_count');
            $protected = collect($assessments)->sum('attempt_count');
        @endphp

        <x-filament::section>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-4xl space-y-2">
                    <h2 class="text-base font-semibold">
                        {{ $t('Assessment operational truth', 'الحقيقة التشغيلية للتقييم', 'Vérité opérationnelle des évaluations') }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $t(
                            'This surface shows what the current Backend can prove about assessment definitions, blueprint constraints and immutable attempt snapshots. It does not create a UI-only publication or editing authority.',
                            'تعرض هذه الصفحة ما يستطيع الخادم إثباته فعليًا عن تعريفات التقييم وقيود المخطط ولقطات المحاولات غير القابلة للتغيير. وهي لا تنشئ سلطة نشر أو تعديل موجودة في الواجهة فقط.',
                            'Cette surface montre ce que le Backend peut réellement prouver sur les définitions, contraintes de blueprint et snapshots immuables. Elle ne crée aucune autorité de publication ou modification uniquement côté UI.'
                        ) }}
                    </p>
                </div>
                <x-filament::button tag="a" :href="$this->questionBankUrl()" color="primary">
                    {{ $t('Open Question Bank', 'فتح بنك الأسئلة', 'Ouvrir la banque de questions') }}
                </x-filament::button>
            </div>

            <div class="mt-4 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm dark:border-warning-500/20 dark:bg-warning-500/5" role="status">
                <strong>{{ $t('Mutation boundary is intentionally locked.', 'حدود التعديل مقفلة عمدًا.', 'La frontière de mutation est volontairement verrouillée.') }}</strong>
                <p class="mt-1">
                    {{ $t(
                        'No current Backend Admin service authorizes lifecycle, availability or blueprint mutations with legal-transition validation, stale-edit protection and immutable audit evidence. Those controls stay read-only until that contract exists.',
                        'لا توجد حاليًا خدمة إدارية في الخادم تسمح بتعديل دورة الحياة أو الإتاحة أو المخطط مع التحقق من الانتقالات القانونية وحماية التعارض وسجل تدقيق غير قابل للتغيير. لذلك تظل هذه الأدوات للقراءة فقط حتى يتوفر العقد.',
                        'Aucun service Admin Backend actuel n’autorise ces mutations avec validation de transition, protection contre les éditions obsolètes et audit immuable. Elles restent donc en lecture seule.'
                    ) }}
                </p>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="modrik-metric-card">
                <div class="modrik-metric-label">{{ $t('Assessments', 'التقييمات', 'Évaluations') }}</div>
                <div class="modrik-metric-value">{{ count($assessments) }}</div>
                <div class="modrik-metric-meta">{{ $t('Current catalogue', 'الكتالوج الحالي', 'Catalogue actuel') }}</div>
            </div>
            <div class="modrik-metric-card" data-tone="success">
                <div class="modrik-metric-label">{{ $t('Published', 'منشور', 'Publié') }}</div>
                <div class="modrik-metric-value">{{ $published }}</div>
                <div class="modrik-metric-meta">{{ $t('Backend status only', 'حالة الخادم فقط', 'État Backend uniquement') }}</div>
            </div>
            <div class="modrik-metric-card" data-tone="warning">
                <div class="modrik-metric-label">{{ $t('In-progress snapshots', 'لقطات قيد التنفيذ', 'Snapshots en cours') }}</div>
                <div class="modrik-metric-value">{{ $inProgress }}</div>
                <div class="modrik-metric-meta">{{ $t('Must remain immutable', 'يجب أن تبقى غير قابلة للتغيير', 'Doivent rester immuables') }}</div>
            </div>
            <div class="modrik-metric-card">
                <div class="modrik-metric-label">{{ $t('Protected attempts', 'المحاولات المحمية', 'Tentatives protégées') }}</div>
                <div class="modrik-metric-value">{{ $protected }}</div>
                <div class="modrik-metric-meta">{{ $t('Historical authority preserved', 'السلطة التاريخية محفوظة', 'Autorité historique préservée') }}</div>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Assessment catalogue and snapshot impact', 'كتالوج التقييم وتأثير اللقطات', 'Catalogue et impact des snapshots') }}</x-slot>
            <x-slot name="description">
                {{ $t('Blueprint configuration is shown as read-only contract data. Existing attempts keep their persisted blueprint and question snapshots regardless of later bank changes.', 'يظهر إعداد المخطط كبيانات عقد للقراءة فقط. تحتفظ المحاولات الحالية بنسخة المخطط ولقطات الأسئلة المحفوظة مهما تغير بنك الأسئلة لاحقًا.', 'Le blueprint est affiché comme donnée contractuelle en lecture seule. Les tentatives existantes conservent leurs snapshots persistés malgré les changements ultérieurs.') }}
            </x-slot>

            @if ($assessments === [])
                <div class="py-12 text-center text-sm text-gray-500" role="status">
                    {{ $t('No assessment definitions are available in the current operational scope.', 'لا توجد تعريفات تقييم متاحة في النطاق التشغيلي الحالي.', 'Aucune définition d’évaluation n’est disponible dans le périmètre actuel.') }}
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($assessments as $assessment)
                        <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5" wire:key="assessment-operations-{{ $assessment['id'] }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge color="primary">{{ $assessment['kind_label'] }}</x-filament::badge>
                                        <x-filament::badge :color="$assessment['status'] === 'published' ? 'success' : 'warning'">
                                            {{ $assessment['status'] }}
                                        </x-filament::badge>
                                        @if ($assessment['is_fixture'])
                                            <x-filament::badge color="gray">Fixture</x-filament::badge>
                                        @endif
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold" dir="auto">{{ $assessment['title'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $assessment['track_title'] }} · {{ $assessment['year_level'] }} · {{ $assessment['node_title'] }}
                                    </p>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <div>{{ $t('Questions', 'الأسئلة', 'Questions') }}: <strong>{{ $assessment['question_count'] }}</strong></div>
                                    <div class="mt-1">{{ $t('Blueprint', 'المخطط', 'Blueprint') }}: <strong>v{{ $assessment['blueprint_version'] }}</strong></div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                    <div class="text-xs text-gray-500">{{ $t('Question order policy', 'سياسة ترتيب الأسئلة', 'Politique d’ordre') }}</div>
                                    <div class="mt-1 font-semibold">{{ $assessment['blueprint']['question_order'] }}</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                    <div class="text-xs text-gray-500">{{ $t('Blueprint slots', 'خانات المخطط', 'Slots du blueprint') }}</div>
                                    <div class="mt-1 font-semibold">{{ $assessment['blueprint']['slot_count'] }}</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                    <div class="text-xs text-gray-500">{{ $t('In-progress attempts', 'محاولات قيد التنفيذ', 'Tentatives en cours') }}</div>
                                    <div class="mt-1 font-semibold">{{ $assessment['in_progress_attempt_count'] }}</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                    <div class="text-xs text-gray-500">{{ $t('Completed attempts', 'محاولات مكتملة', 'Tentatives terminées') }}</div>
                                    <div class="mt-1 font-semibold">{{ $assessment['completed_attempt_count'] }}</div>
                                </div>
                            </div>

                            @if ($assessment['snapshot_protected'])
                                <div class="mt-4 rounded-lg border border-info-200 bg-info-50 p-3 text-sm dark:border-info-500/20 dark:bg-info-500/5">
                                    <strong>{{ $t('Immutable attempt history is active.', 'سجل المحاولات غير القابل للتغيير موجود.', 'Un historique immuable est actif.') }}</strong>
                                    <p class="mt-1">
                                        {{ $t('Persisted attempt snapshots exist for this assessment. Current-bank or future blueprint changes must never rewrite those attempts.', 'توجد لقطات محاولات محفوظة لهذا التقييم. يجب ألا تعيد تغييرات بنك الأسئلة أو المخطط المستقبلية كتابة هذه المحاولات.', 'Des snapshots de tentative existent. Toute modification future de la banque ou du blueprint ne doit jamais réécrire ces tentatives.') }}
                                    </p>
                                    @if ($assessment['snapshot_blueprint_versions'] !== [])
                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                            {{ $t('Snapshot blueprint versions', 'إصدارات المخطط داخل اللقطات', 'Versions de blueprint des snapshots') }}:
                                            {{ implode(', ', array_map(static fn ($version) => 'v'.$version, $assessment['snapshot_blueprint_versions'])) }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <details class="mt-4 rounded-lg border border-gray-200 p-3 text-xs dark:border-white/10">
                                <summary class="cursor-pointer font-medium">{{ $t('Blueprint contract details', 'تفاصيل عقد المخطط', 'Détails du contrat blueprint') }}</summary>
                                @if ($assessment['blueprint']['configured'])
                                    <pre class="mt-3 max-w-full overflow-auto whitespace-pre-wrap break-words rounded bg-gray-950 p-3 text-gray-100" dir="ltr">{{ json_encode($assessment['blueprint']['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @else
                                    <p class="mt-3 text-gray-600 dark:text-gray-300">
                                        {{ $t('No explicit blueprint object is persisted. The Backend therefore uses its contract default selection with shuffled question order; this remains read-only here.', 'لا يوجد كائن مخطط صريح محفوظ. لذلك يستخدم الخادم اختيار العقد الافتراضي مع ترتيب أسئلة عشوائي؛ ويظل ذلك للقراءة فقط هنا.', 'Aucun objet blueprint explicite n’est persisté. Le Backend utilise donc la sélection contractuelle par défaut avec ordre mélangé ; cela reste en lecture seule ici.') }}
                                    </p>
                                @endif
                                <dl class="mt-3 grid gap-2 md:grid-cols-2">
                                    <div><dt class="font-medium">Assessment ID</dt><dd class="break-all font-mono">{{ $assessment['id'] }}</dd></div>
                                    <div><dt class="font-medium">Curriculum node</dt><dd class="break-all font-mono">{{ $assessment['node_code'] }}</dd></div>
                                    <div><dt class="font-medium">Created</dt><dd>{{ $assessment['created_at'] }}</dd></div>
                                    <div><dt class="font-medium">Updated</dt><dd>{{ $assessment['updated_at'] }}</dd></div>
                                </dl>
                            </details>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Contract-backed capability boundaries', 'حدود القدرات المدعومة بالعقد', 'Frontières de capacité contractuelles') }}</x-slot>
            <x-slot name="description">
                {{ $t('Unsupported mutation is classified explicitly instead of being represented by a button that bypasses Backend authority.', 'يتم تصنيف التعديل غير المدعوم بوضوح بدل تمثيله بزر يتجاوز سلطة الخادم.', 'Les mutations non supportées sont classifiées explicitement au lieu d’être représentées par un bouton contournant l’autorité Backend.') }}
            </x-slot>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($boundaries as $boundary)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $boundary['capability'] }}</h3>
                            <x-filament::badge :color="$boundary['state'] === 'present' ? 'success' : 'warning'">{{ $boundary['state'] }}</x-filament::badge>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $boundary['reason'] }}</p>
                        <div class="mt-2 text-xs font-mono text-gray-500">{{ $boundary['classification'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
