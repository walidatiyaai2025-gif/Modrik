<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $steps = $this->lifecycle();
        $metrics = $this->metrics();
        $coverage = $this->coverage();
        $policy = $this->processingPolicy();
        $supporting = $this->supportingSurfaces();
        $deferred = $this->deferredCapabilities();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-content-operations">
        <x-admin.operational-banner severity="info" :title="$label('سلطة النشر محفوظة', 'Publication authority is preserved', 'L’autorité de publication est préservée')" :message="$label('هذه الصفحة ترشد المشغّل بين الأسطح المصرح بها فقط. لا تتجاوز التحقق أو الحقوق أو المراجعة، ولا تمنح المحتوى الذي ينشئه المستخدم صلاحية النشر التلقائي.', 'This hub only guides operators through authorized surfaces. It does not bypass validation, rights, review, or the no-UGC-auto-promotion rule.', 'Ce centre guide uniquement vers les surfaces autorisées. Il ne contourne ni validation, ni droits, ni révision, ni la règle interdisant la promotion automatique de l’UGC.')" />

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8" aria-label="{{ $label('مؤشرات عمليات المحتوى', 'Content operations KPIs', 'KPI des opérations de contenu') }}">
            @foreach ([
                'uploads' => $label('Uploads', 'Uploads', 'Uploads'),
                'processing' => $label('معالجة', 'Processing', 'Traitement'),
                'failed' => $label('فشل', 'Failed', 'Échec'),
                'review_backlog' => $label('مراجعة معلقة', 'Review backlog', 'Révision en attente'),
                'rights_backlog' => $label('حقوق معلقة', 'Rights backlog', 'Droits en attente'),
                'published' => $label('منشور', 'Published', 'Publié'),
                'lessons' => $label('دروس', 'Lessons', 'Leçons'),
                'questions' => $label('أسئلة', 'Questions', 'Questions'),
            ] as $key => $copy)
                <div class="modrik-panel p-4"><div class="text-xs text-gray-500">{{ $copy }}</div><div class="mt-2 text-2xl font-bold text-gray-950">{{ $metrics[$key] }}</div></div>
            @endforeach
        </section>

        <section class="modrik-panel" aria-labelledby="content-coverage-title">
            <div class="modrik-panel-header"><div><h2 id="content-coverage-title" class="modrik-panel-title">{{ $label('تغطية المنهج', 'Curriculum coverage', 'Couverture du programme') }}</h2><p class="modrik-panel-subtitle">{{ $label('أعداد مشتقة مباشرة من الجداول القانونية الحالية، وليست نسبة جودة أو اكتمال مخترعة.', 'Counts derived directly from canonical tables; this is not an invented quality or completeness score.', 'Comptages dérivés directement des tables canoniques ; il ne s’agit pas d’un score de qualité ou de complétude inventé.') }}</p></div></div>
            <div class="modrik-panel-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                @foreach ([
                    'tracks' => $label('المسارات', 'Tracks', 'Parcours'),
                    'tracks_with_nodes' => $label('مسارات بها محتوى', 'Tracks with nodes', 'Parcours avec contenu'),
                    'curriculum_nodes' => $label('عقد المنهج', 'Curriculum nodes', 'Nœuds du programme'),
                    'published_nodes' => $label('عقد منشورة', 'Published nodes', 'Nœuds publiés'),
                    'nodes_with_lessons' => $label('عقد بها دروس', 'Nodes with lessons', 'Nœuds avec leçons'),
                    'nodes_with_questions' => $label('عقد بها أسئلة', 'Nodes with questions', 'Nœuds avec questions'),
                    'nodes_with_quizzes' => $label('عقد بها اختبارات', 'Nodes with quizzes', 'Nœuds avec quiz'),
                ] as $key => $copy)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4"><div class="text-xs text-gray-500">{{ $copy }}</div><div class="mt-2 text-2xl font-bold text-gray-950">{{ $coverage[$key] }}</div></div>
                @endforeach
            </div>
        </section>

        <section class="modrik-panel" aria-labelledby="content-processing-policy-title" data-testid="content-processing-policy">
            <div class="modrik-panel-header"><div><h2 id="content-processing-policy-title" class="modrik-panel-title">{{ $label('سياسة المعالجة', 'Processing policy', 'Politique de traitement') }}</h2><p class="modrik-panel-subtitle">{{ $label('حالة قراءة فقط للعقد الفعلي؛ لا تختار الواجهة مزودًا ولا تجعل الذكاء المدفوع شرطًا.', 'Read-only status of the real contract; Admin does not select a provider or make paid AI a requirement.', 'État en lecture seule du contrat réel ; Admin ne choisit pas de fournisseur et ne rend pas l’IA payante obligatoire.') }}</p></div><x-filament::badge color="success">{{ $label('مسار بدون تكلفة مضمون', 'Zero-paid path required', 'Parcours sans coût requis') }}</x-filament::badge></div>
            <div class="modrik-panel-body space-y-4">
                <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5 text-sm">
                    <div class="rounded-xl bg-gray-50 p-4"><dt class="text-gray-500">{{ $label('النمط', 'Mode', 'Mode') }}</dt><dd class="mt-1 break-words font-semibold text-gray-950">{{ $policy['mode'] }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-4"><dt class="text-gray-500">{{ $label('المزود', 'Provider', 'Fournisseur') }}</dt><dd class="mt-1 break-words font-semibold text-gray-950">{{ $policy['provider'] }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-4"><dt class="text-gray-500">{{ $label('AI مدفوع Runtime', 'Paid AI runtime', 'IA payante runtime') }}</dt><dd class="mt-1 font-semibold text-gray-950">{{ $policy['paid_ai_runtime_enabled'] ? $label('مفعّل كقدرة اختيارية', 'Enabled as optional capability', 'Activée comme capacité optionnelle') : $label('غير مفعّل', 'Disabled', 'Désactivée') }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-4"><dt class="text-gray-500">paid_ai_required</dt><dd class="mt-1 font-semibold text-gray-950">false</dd></div>
                    <div class="rounded-xl bg-gray-50 p-4"><dt class="text-gray-500">{{ $label('سلطة التحقق', 'Validation authority', 'Autorité de validation') }}</dt><dd class="mt-1 break-words font-semibold text-gray-950">{{ $policy['validation_authority'] }}</dd></div>
                </dl>
                <x-admin.operational-banner severity="success" :title="$label('الـBackend يفرض المسار بدون AI مدفوع', 'Backend enforces the zero-paid path', 'Le Backend impose le parcours sans IA payante')" :message="$label('طلبات التحضير المقبولة يجب أن تحمل paid_ai_required=false، والنتيجة تعود كحزمة ZIP مرتبطة بالطلب وتخضع للتحقق الحتمي والحقوق والمراجعة قبل النشر.', 'Accepted preparation requests must use paid_ai_required=false. The returned ZIP remains request-bound and must pass deterministic validation, rights review and publication gates.', 'Les demandes acceptées doivent utiliser paid_ai_required=false. Le ZIP retourné reste lié à la demande et doit franchir validation déterministe, droits et publication.')" />
            </div>
        </section>

        <section class="modrik-panel" aria-labelledby="content-lifecycle-title">
            <div class="modrik-panel-header"><div><h2 id="content-lifecycle-title" class="modrik-panel-title">{{ $label('دورة المحتوى الرسمي', 'Official content lifecycle', 'Cycle du contenu officiel') }}</h2><p class="modrik-panel-subtitle">{{ $label('اتبع المراحل بالترتيب. أي حاجز ظاهر يجب حله في سطحه المخصص بدلاً من SQL أو روابط داخلية.', 'Follow the lifecycle in order. Resolve every blocker in its supported surface rather than through SQL or hidden URLs.', 'Suivez les étapes dans l’ordre. Résolvez chaque blocage dans sa surface dédiée, jamais par SQL ou URL cachée.') }}</p></div><x-filament::badge color="info">{{ count($steps) }} {{ $label('مراحل', 'stages', 'étapes') }}</x-filament::badge></div>
            <div class="modrik-panel-body"><ol class="grid gap-4 lg:grid-cols-2" aria-label="{{ $label('دورة عمليات المحتوى', 'Content operations lifecycle', 'Cycle des opérations de contenu') }}">@foreach ($steps as $index => $step)<li class="rounded-2xl border border-gray-200 bg-white p-5" data-testid="content-operation-step-{{ $index + 1 }}"><div class="flex items-start justify-between gap-4"><div class="min-w-0"><div class="flex items-center gap-2"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-bold text-primary-700">{{ $index + 1 }}</span><h3 class="text-base font-bold text-gray-950">{{ $step['label'] }}</h3></div><p class="mt-3 text-sm leading-6 text-gray-600">{{ $step['description'] }}</p></div><x-filament::badge :color="$step['state'] === 'active' ? 'success' : 'warning'">{{ $step['state'] }}</x-filament::badge></div><div class="mt-5"><x-filament::button tag="a" :href="$step['url']" icon="heroicon-o-arrow-up-right">{{ $label('فتح المرحلة', 'Open stage', 'Ouvrir l’étape') }}</x-filament::button></div></li>@endforeach</ol></div>
        </section>

        <section class="modrik-panel" aria-labelledby="content-supporting-surfaces-title">
            <div class="modrik-panel-header"><div><h2 id="content-supporting-surfaces-title" class="modrik-panel-title">{{ $label('الفرز والتتبع', 'Triage & traceability', 'Triage et traçabilité') }}</h2><p class="modrik-panel-subtitle">{{ $label('أسطح إضافية قابلة للاكتشاف بدون تكرار سلطة المعالجة أو النشر.', 'Discoverable supporting surfaces without duplicating processing or publication authority.', 'Surfaces complémentaires découvrables sans dupliquer l’autorité de traitement ou publication.') }}</p></div></div>
            <div class="modrik-panel-body grid gap-4 lg:grid-cols-2">@foreach ($supporting as $surface)<article class="rounded-2xl border border-gray-200 bg-white p-5"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-gray-950">{{ $surface['label'] }}</h3><p class="mt-2 text-sm leading-6 text-gray-600">{{ $surface['description'] }}</p></div><x-filament::badge :color="$surface['state'] === 'active' ? 'success' : 'gray'">{{ $surface['state'] }}</x-filament::badge></div><div class="mt-5"><x-filament::button tag="a" :href="$surface['url']" icon="heroicon-o-arrow-up-right">{{ $label('فتح', 'Open', 'Ouvrir') }}</x-filament::button></div></article>@endforeach</div>
        </section>

        <section class="modrik-panel" aria-labelledby="content-deferred-title"><div class="modrik-panel-header"><div><h2 id="content-deferred-title" class="modrik-panel-title">{{ $label('قدرات مؤجلة صراحة', 'Explicitly deferred capabilities', 'Fonctions explicitement différées') }}</h2><p class="modrik-panel-subtitle">{{ $label('لا يتم تقديم روابط وهمية أو تجاوزات يدوية لقدرات لا تملك Backend contract معتمد.', 'No fake routes or manual bypasses are exposed for capabilities without an approved Backend contract.', 'Aucun faux lien ni contournement manuel n’est exposé sans contrat Backend approuvé.') }}</p></div></div><div class="modrik-panel-body grid gap-4 lg:grid-cols-2">@foreach($deferred as $item)<x-admin.operational-banner severity="warning" :title="$item['label'].' · '.$item['classification']" :message="$item['reason']" />@endforeach</div></section>
    </div>
</x-filament-panels::page>
