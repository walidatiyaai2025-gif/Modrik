<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $metrics = $this->metrics();
        $records = $this->records();
        $versions = $this->canonicalVersions();
        $supersessions = $this->supersessionHistory();
        $rebuild = $this->rebuildStatus();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-content-traceability">
        <x-admin.operational-banner severity="info" :title="$label('التاريخ غير قابل لإعادة الكتابة', 'History is append-only', 'Historique en ajout seulement')" :message="$label('تعرض الصفحة مؤشرات التتبع والإصدارات وsupersession من السجلات الفعلية. لا تعيد تنشيط طلب قديم ولا تتجاوز الحقوق أو النشر.', 'This page exposes real traceability, version and supersession evidence. It does not reactivate stale requests or bypass rights/publication gates.', 'Cette page expose les preuves réelles de traçabilité, versions et supersession. Elle ne réactive pas les demandes obsolètes et ne contourne pas les droits/publication.')" />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7" aria-label="{{ $label('مؤشرات التتبع', 'Traceability metrics', 'Indicateurs de traçabilité') }}">
            @foreach ([
                'requests' => $label('طلبات', 'Requests', 'Demandes'),
                'superseded_requests' => $label('مستبدلة', 'Superseded', 'Remplacées'),
                'imports' => $label('Imports', 'Imports', 'Imports'),
                'published_imports' => $label('منشورة', 'Published', 'Publiées'),
                'lessons' => $label('دروس', 'Lessons', 'Leçons'),
                'questions' => $label('أسئلة', 'Questions', 'Questions'),
                'quizzes' => $label('اختبارات', 'Quizzes', 'Quiz'),
            ] as $key => $copy)
                <div class="modrik-panel p-4"><div class="text-xs text-gray-500">{{ $copy }}</div><div class="mt-2 text-2xl font-bold text-gray-950">{{ $metrics[$key] }}</div></div>
            @endforeach
        </section>

        <section class="modrik-panel" aria-labelledby="trace-records-title">
            <div class="modrik-panel-header"><div><h2 id="trace-records-title" class="modrik-panel-title">{{ $label('سلسلة التتبع', 'Traceability chain', 'Chaîne de traçabilité') }}</h2><p class="modrik-panel-subtitle">{{ $label('آخر 100 Import مع الروابط المنطقية بين الطلب والـhashes والحقوق والمراجعة والنشر.', 'Latest 100 imports with request, hashes, rights, review and publication linkage.', '100 derniers imports avec liens demande, hashes, droits, révision et publication.') }}</p></div></div>
            <div class="modrik-panel-body">
                @if ($records === [])
                    <p class="text-sm text-gray-600">{{ $label('لا توجد سجلات تتبع بعد.', 'No traceability records yet.', 'Aucun enregistrement de traçabilité.') }}</p>
                @else
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($records as $row)
                            <article class="rounded-2xl border border-gray-200 bg-white p-5" data-testid="content-traceability-record">
                                <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="text-xs text-gray-500">Import {{ $row['import_id'] }} · Request {{ $row['request_id'] ?? '—' }}</div><h3 class="mt-1 text-base font-bold text-gray-950">{{ $row['import_status'] }}</h3></div><x-filament::badge :color="$row['import_status'] === 'published' ? 'success' : ($row['import_status'] === 'superseded' ? 'gray' : 'info')">{{ $row['import_status'] }}</x-filament::badge></div>
                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-gray-500">{{ $label('Schema', 'Schema', 'Schéma') }}</dt><dd class="font-semibold">{{ $row['schema_version'] ?? '—' }}</dd></div>
                                    <div><dt class="text-gray-500">{{ $label('الحقوق', 'Rights', 'Droits') }}</dt><dd class="font-semibold">{{ $row['rights_review_status'] }} @if($row['rights_basis']) · {{ $row['rights_basis'] }} @endif</dd></div>
                                    <div><dt class="text-gray-500">settings</dt><dd><code class="text-xs">{{ $row['settings_hash'] ?? '—' }}</code></dd></div>
                                    <div><dt class="text-gray-500">archive</dt><dd><code class="text-xs">{{ $row['archive_hash'] ?? '—' }}</code></dd></div>
                                    <div><dt class="text-gray-500">content</dt><dd><code class="text-xs">{{ $row['content_hash'] ?? '—' }}</code></dd></div>
                                    <div><dt class="text-gray-500">dry-run</dt><dd><code class="text-xs">{{ $row['dry_run_hash'] ?? '—' }}</code></dd></div>
                                    <div><dt class="text-gray-500">{{ $label('المراجعة', 'Review', 'Révision') }}</dt><dd class="font-semibold">{{ $row['review_decision'] ?? '—' }}</dd></div>
                                    <div><dt class="text-gray-500">{{ $label('النشر', 'Publication', 'Publication') }}</dt><dd class="font-semibold">{{ $row['publication_status'] ?? '—' }} @if($row['publication_attempts']) · {{ $row['publication_attempts'] }} @endif</dd></div>
                                </dl>
                                @if ($row['rights_evidence_reference'])<div class="mt-4 rounded-xl bg-gray-50 p-3 text-sm"><span class="text-gray-500">{{ $label('مرجع دليل الحقوق', 'Rights evidence reference', 'Référence de preuve de droits') }}:</span> <span class="break-all font-medium text-gray-800">{{ $row['rights_evidence_reference'] }}</span></div>@endif
                                @if ($row['superseded_by_request_id'])<div class="mt-4"><x-filament::badge color="gray">{{ $label('استبدل بواسطة', 'Superseded by', 'Remplacée par') }} {{ $row['superseded_by_request_id'] }}</x-filament::badge></div>@endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3" aria-label="{{ $label('الإصدارات القانونية', 'Canonical versions', 'Versions canoniques') }}">
            @foreach ([
                'lessons' => $label('إصدارات الدروس', 'Lesson versions', 'Versions des leçons'),
                'questions' => $label('إصدارات الأسئلة', 'Question versions', 'Versions des questions'),
                'quizzes' => $label('إصدارات الاختبارات', 'Quiz versions', 'Versions des quiz'),
            ] as $group => $title)
                <section class="modrik-panel"><div class="modrik-panel-header"><div><h2 class="modrik-panel-title">{{ $title }}</h2></div><x-filament::badge color="info">{{ count($versions[$group]) }}</x-filament::badge></div><div class="modrik-panel-body space-y-3">@forelse ($versions[$group] as $item)<div class="rounded-xl border border-gray-200 p-3"><div class="flex items-center justify-between gap-3"><span class="min-w-0 truncate text-sm font-semibold">{{ $item['name'] ?? $item['reference'] ?? $item['type'] ?? '—' }}</span><x-filament::badge color="gray">v{{ $item['version'] }}</x-filament::badge></div><div class="mt-1 text-xs text-gray-500">{{ $item['node'] }} · {{ $item['status'] }}</div></div>@empty<p class="text-sm text-gray-600">{{ $label('لا توجد إصدارات.', 'No versions yet.', 'Aucune version.') }}</p>@endforelse</div></section>
            @endforeach
        </section>

        <section class="modrik-panel" aria-labelledby="supersession-history-title">
            <div class="modrik-panel-header"><div><h2 id="supersession-history-title" class="modrik-panel-title">{{ $label('سجل Supersession', 'Supersession history', 'Historique de supersession') }}</h2><p class="modrik-panel-subtitle">{{ $label('طلبات الإعداد القديمة تبقى تاريخًا محفوظًا ولا تتم إعادتها للحالة النشطة.', 'Older preparation requests remain protected history and are not reactivated.', 'Les anciennes demandes restent un historique protégé et ne sont pas réactivées.') }}</p></div></div>
            <div class="modrik-panel-body">@forelse ($supersessions as $row)<div class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm"><code>{{ $row['from'] }}</code><span aria-hidden="true">→</span><code>{{ $row['to'] }}</code><x-filament::badge color="gray">{{ $row['schema_version'] }}</x-filament::badge><span class="text-gray-500">{{ $row['superseded_at'] ?? '—' }}</span></div>@empty<p class="text-sm text-gray-600">{{ $label('لا توجد عمليات استبدال مسجلة.', 'No supersession history yet.', 'Aucun historique de supersession.') }}</p>@endforelse</div>
        </section>

        <section class="modrik-panel" aria-labelledby="rebuild-status-title"><div class="modrik-panel-header"><div><h2 id="rebuild-status-title" class="modrik-panel-title">{{ $label('إعادة بناء المنهج', 'Curriculum rebuild', 'Reconstruction du programme') }}</h2><p class="modrik-panel-subtitle">{{ $rebuild['classification'] }} · {{ $rebuild['code'] }}</p></div><x-filament::badge color="gray">{{ $rebuild['classification'] }}</x-filament::badge></div><div class="modrik-panel-body"><x-admin.operational-banner severity="warning" :title="$label('غير مفعّل بدون Backend contract', 'Disabled until Backend contract exists', 'Désactivé sans contrat Backend')" :message="$rebuild['message']" /></div></section>
    </div>
</x-filament-panels::page>
