<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $metrics = $this->metrics();
        $exceptions = $this->exceptions();
        $outcomes = $this->dryRunOutcomes();
        $confidence = $this->automatedConfidenceStatus();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-content-review-exceptions">
        <x-admin.operational-banner severity="info" :title="$label('فرز مبني على أدلة محفوظة', 'Evidence-backed triage', 'Triage fondé sur des preuves')" :message="$label('لا تنشئ هذه الصفحة قرار مراجعة أو درجة ثقة. إنها تجمع نتائج Backend الحالية وتوجّهك إلى السطح المصرح به لاتخاذ الإجراء.', 'This page does not manufacture review decisions or confidence scores. It aggregates current Backend evidence and routes operators to the authorized action surface.', 'Cette page ne fabrique ni décision de révision ni score de confiance. Elle agrège les preuves Backend et oriente vers la surface autorisée.')" />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ $label('مؤشرات الاستثناءات', 'Exception metrics', 'Indicateurs d’exception') }}">
            @foreach ([
                'total_attention' => [$label('تحتاج انتباه', 'Needs attention', 'À traiter'), 'warning'],
                'rights_pending' => [$label('حقوق غير معتمدة', 'Rights unresolved', 'Droits non résolus'), 'warning'],
                'review_pending' => [$label('بانتظار قرار', 'Review pending', 'Révision en attente'), 'info'],
                'review_exception' => [$label('رفض/إصلاح', 'Rejected / fix', 'Rejet / correction'), 'danger'],
                'processing_blocked' => [$label('محجوب/فشل', 'Blocked / failed', 'Bloqué / échec'), 'danger'],
            ] as $key => $meta)
                <div class="modrik-panel p-5">
                    <div class="flex items-center justify-between gap-3"><span class="text-sm text-gray-600">{{ $meta[0] }}</span><x-filament::badge :color="$meta[1]">{{ $metrics[$key] }}</x-filament::badge></div>
                    <div class="mt-3 text-3xl font-bold text-gray-950">{{ $metrics[$key] }}</div>
                </div>
            @endforeach
        </section>

        <section class="modrik-panel" aria-labelledby="dry-run-outcomes-title">
            <div class="modrik-panel-header"><div><h2 id="dry-run-outcomes-title" class="modrik-panel-title">{{ $label('نتائج الـDry-run', 'Dry-run outcomes', 'Résultats du dry-run') }}</h2><p class="modrik-panel-subtitle">{{ $label('ملخص من dry_run_summary المخزن؛ لا يعيد حساب السياسة في الواجهة.', 'Summary of persisted dry_run_summary; policy is not recalculated in the UI.', 'Résumé du dry_run_summary persisté ; la politique n’est pas recalculée dans l’UI.') }}</p></div></div>
            <div class="modrik-panel-body space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    @foreach ([
                        'imports' => $label('عمليات', 'Runs', 'Exécutions'),
                        'publishable' => $label('قابل للنشر', 'Publishable', 'Publiable'),
                        'blocked' => $label('محجوب', 'Blocked', 'Bloqué'),
                        'question_create' => $label('أسئلة جديدة', 'Question create', 'Questions à créer'),
                        'question_reuse' => $label('أسئلة معاد استخدامها', 'Question reuse', 'Questions réutilisées'),
                        'question_conflict' => $label('تعارض أسئلة', 'Question conflicts', 'Conflits de questions'),
                    ] as $key => $copy)
                        <div class="rounded-2xl border border-gray-200 bg-white p-4"><div class="text-xs text-gray-500">{{ $copy }}</div><div class="mt-2 text-2xl font-bold">{{ $outcomes[$key] }}</div></div>
                    @endforeach
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-950">{{ $label('أسباب الحجب', 'Blocking reasons', 'Raisons de blocage') }}</h3>
                    @if ($outcomes['blocking_codes'] === [])
                        <p class="mt-2 text-sm text-gray-600">{{ $label('لا توجد أكواد حجب مسجلة.', 'No blocking codes recorded.', 'Aucun code de blocage enregistré.') }}</p>
                    @else
                        <div class="mt-3 flex flex-wrap gap-2">@foreach ($outcomes['blocking_codes'] as $code => $count)<x-filament::badge color="warning">{{ $code }} · {{ $count }}</x-filament::badge>@endforeach</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="modrik-panel" aria-labelledby="review-exceptions-title">
            <div class="modrik-panel-header"><div><h2 id="review-exceptions-title" class="modrik-panel-title">{{ $label('قائمة الاستثناءات', 'Exception queue', 'File des exceptions') }}</h2><p class="modrik-panel-subtitle">{{ $label('آخر 100 سجل يحتاج انتباه؛ الإجراءات نفسها تبقى في صفحات الاستيعاب أو الحقوق أو المراجعة.', 'Latest 100 records needing attention; mutations remain on ingestion, rights or review surfaces.', '100 derniers éléments à traiter ; les mutations restent sur les surfaces ingestion, droits ou révision.') }}</p></div><x-filament::badge color="warning">{{ count($exceptions) }}</x-filament::badge></div>
            <div class="modrik-panel-body">
                @if ($exceptions === [])
                    <x-admin.operational-banner severity="success" :title="$label('لا توجد استثناءات حالية', 'No current exceptions', 'Aucune exception actuelle')" :message="$label('لا توجد عمليات محتوى غير منشورة تطابق شروط الفرز الحالية.', 'No unpublished content operations match the current triage conditions.', 'Aucune opération non publiée ne correspond aux conditions actuelles.')" />
                @else
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($exceptions as $item)
                            <article class="rounded-2xl border border-gray-200 bg-white p-5" data-testid="content-review-exception">
                                <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label('Import', 'Import', 'Import') }} {{ substr($item['id'], 0, 12) }}</div><h3 class="mt-1 text-base font-bold text-gray-950">{{ $item['label'] }}</h3></div><x-filament::badge :color="$item['severity']">{{ $item['category'] }}</x-filament::badge></div>
                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-gray-500">{{ $label('الحالة', 'Status', 'État') }}</dt><dd class="font-semibold text-gray-900">{{ $item['status'] }} / {{ $item['operation_state'] }}</dd></div>
                                    <div><dt class="text-gray-500">{{ $label('الحقوق', 'Rights', 'Droits') }}</dt><dd class="font-semibold text-gray-900">{{ $item['rights_review_status'] }}</dd></div>
                                    @if ($item['last_error_code'])<div><dt class="text-gray-500">{{ $label('كود الخطأ', 'Error code', 'Code erreur') }}</dt><dd><code class="break-all text-xs">{{ $item['last_error_code'] }}</code></dd></div>@endif
                                    @if ($item['operation_checkpoint'])<div><dt class="text-gray-500">{{ $label('نقطة المعالجة', 'Checkpoint', 'Point de contrôle') }}</dt><dd><code class="break-all text-xs">{{ $item['operation_checkpoint'] }}</code></dd></div>@endif
                                </dl>
                                @if ($item['review_reason'])<p class="mt-4 rounded-xl bg-gray-50 p-3 text-sm leading-6 text-gray-700">{{ \Illuminate\Support\Str::limit($item['review_reason'], 320) }}</p>@endif
                                <div class="mt-5"><x-filament::button tag="a" :href="$item['next_url']" icon="heroicon-o-arrow-up-right">{{ $item['next_label'] }}</x-filament::button></div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="modrik-panel" aria-labelledby="confidence-status-title">
            <div class="modrik-panel-header"><div><h2 id="confidence-status-title" class="modrik-panel-title">{{ $label('الثقة الآلية', 'Automated confidence', 'Confiance automatisée') }}</h2><p class="modrik-panel-subtitle">{{ $confidence['classification'] }} · {{ $confidence['code'] }}</p></div><x-filament::badge color="gray">{{ $confidence['classification'] }}</x-filament::badge></div>
            <div class="modrik-panel-body"><x-admin.operational-banner severity="warning" :title="$label('لا توجد درجة ثقة قابلة للإدارة', 'No manageable confidence score', 'Aucun score de confiance administrable')" :message="$confidence['message']" /></div>
        </section>
    </div>
</x-filament-panels::page>
