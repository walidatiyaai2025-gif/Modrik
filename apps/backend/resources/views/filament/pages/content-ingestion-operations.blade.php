<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $metrics = $this->metrics();
        $imports = $this->imports();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="content-ingestion-operations">
        <x-admin.operational-banner severity="info" :title="$label('المعالجة تبقى خاضعة لسلطة الخلفية', 'Backend processing authority is preserved', 'L’autorité de traitement Backend est préservée')" :message="$label('تعرض هذه الصفحة حالة الاستيعاب ونقاط الفشل وتعيد تشغيل الـdry-run المصرح به فقط. رفع الملف نفسه يبقى مرتبطًا بطلب الإعداد الأصلي ولا تتجاوز هذه الصفحة التحقق أو الحقوق أو النشر.', 'This surface exposes ingestion state and failure checkpoints and can only retry the authorized dry-run. File upload remains bound to the originating Preparation Request and this page does not bypass validation, rights, or publication.', 'Cette surface expose l’état d’ingestion et les points d’échec et ne relance que le dry-run autorisé. Le téléversement reste lié à la demande de préparation d’origine et ne contourne ni validation, ni droits, ni publication.')" />

        <section class="modrik-panel" aria-labelledby="returned-zip-upload-title">
            <div class="modrik-panel-header">
                <div>
                    <h2 id="returned-zip-upload-title" class="modrik-panel-title">{{ $label('رفع Returned ZIP', 'Returned ZIP upload', 'Téléversement du ZIP retourné') }}</h2>
                    <p class="modrik-panel-subtitle">{{ $label('اختر طلب الإعداد الأصلي ثم استخدم إجراء رفع الملف منه؛ لا يوجد رفع عام غير مرتبط بطلب.', 'Open the originating Preparation Request and use its upload action; unbound global upload is intentionally unavailable.', 'Ouvrez la demande de préparation d’origine puis utilisez son action de téléversement ; aucun upload global non lié n’est disponible.') }}</p>
                </div>
                <x-filament::button tag="a" :href="$this->uploadSurfaceUrl()" icon="heroicon-o-arrow-up-right" data-testid="open-returned-zip-upload">
                    {{ $label('فتح مسار الرفع', 'Open returned ZIP upload', 'Ouvrir le téléversement ZIP') }}
                </x-filament::button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4" aria-label="{{ $label('مؤشرات الاستيعاب', 'Ingestion metrics', 'Indicateurs d’ingestion') }}">@foreach (['total' => ['الإجمالي','Total','Total'], 'processing' => ['قيد المعالجة','Processing','En traitement'], 'blocked' => ['محجوب','Blocked','Bloqué'], 'failed' => ['فشل','Failed','Échec']] as $key => $copy)<div class="modrik-panel p-5"><div class="text-sm text-gray-600">{{ $label($copy[0], $copy[1], $copy[2]) }}</div><div class="mt-2 text-3xl font-bold">{{ $metrics[$key] }}</div></div>@endforeach</section>
        <section class="modrik-panel" aria-labelledby="ingestion-list-title"><div class="modrik-panel-header"><div><h2 id="ingestion-list-title" class="modrik-panel-title">{{ $label('سجل الاستيعاب', 'Ingestion log', 'Journal d’ingestion') }}</h2><p class="modrik-panel-subtitle">{{ $label('آخر 100 عملية مع الحالة ونقطة المعالجة والخطأ الآمن.', 'Latest 100 imports with state, checkpoint and safe error code.', '100 dernières importations avec état, étape et code d’erreur sûr.') }}</p></div></div><div class="modrik-panel-body overflow-x-auto">@if ($imports === [])<p class="py-8 text-center text-sm text-gray-500">{{ $label('لا توجد عمليات استيعاب بعد.', 'No ingestion activity yet.', 'Aucune ingestion pour le moment.') }}</p>@else<table class="min-w-full text-sm"><thead><tr class="text-start text-gray-600"><th class="p-3">{{ $label('الحالة','State','État') }}</th><th class="p-3">{{ $label('النقطة','Checkpoint','Étape') }}</th><th class="p-3">{{ $label('المحاولات','Attempts','Tentatives') }}</th><th class="p-3">{{ $label('الخطأ','Error','Erreur') }}</th><th class="p-3">{{ $label('الإجراء','Action','Action') }}</th></tr></thead><tbody>@foreach ($imports as $import)<tr class="border-t border-gray-200"><td class="p-3"><x-filament::badge>{{ $import['operation_state'] }}</x-filament::badge></td><td class="p-3">{{ $import['operation_checkpoint'] ?? '—' }}</td><td class="p-3">{{ $import['operation_attempts'] }}</td><td class="p-3">{{ $import['last_error_code'] ?? '—' }}</td><td class="p-3">@if (in_array($import['status'], ['staged','validated','reviewed'], true))<x-filament::button size="sm" wire:click="retryDryRun('{{ $import['id'] }}')">{{ $label('إعادة المحاولة','Retry dry-run','Relancer dry-run') }}</x-filament::button>@else<span class="text-gray-400">—</span>@endif</td></tr>@endforeach</tbody></table>@endif</div></section>
    </div>
</x-filament-panels::page>
