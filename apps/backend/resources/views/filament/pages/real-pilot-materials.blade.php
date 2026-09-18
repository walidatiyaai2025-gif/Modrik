<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $metrics = $this->metrics();
        $materials = $this->materials();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-real-pilot-materials">
        <x-admin.operational-banner
            severity="warning"
            :title="$label('هذه مواد حقيقية وليست محتوى منشورًا للطلاب بعد', 'These are real sources, not student-published content yet', 'Ce sont des sources réelles, pas encore du contenu publié aux élèves')"
            :message="$label(
                'السجل يثبت ما استلمه المشروع ويقسمه ويمنع أي نشر قبل حسم الربط الأكاديمي والحقوق والخصوصية. الملفات الخام لا تُحفظ في GitHub، ويجب مطابقة SHA-256 عند رفع النسخة إلى التخزين المتحكم به.',
                'This registry proves what the project received, segments it, and blocks delivery until academic mapping, rights and privacy gates are resolved. Raw binaries are not stored in GitHub; controlled storage must match the recorded SHA-256.',
                'Ce registre prouve les sources reçues, les segmente et bloque toute diffusion tant que le mapping, les droits et la confidentialité ne sont pas validés. Les fichiers bruts ne sont pas stockés dans GitHub ; le stockage contrôlé doit correspondre au SHA-256.'
            )"
        />

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" aria-label="{{ $label('مؤشرات المواد الحقيقية', 'Real material indicators', 'Indicateurs des supports réels') }}">
            @foreach ([
                'total' => $label('المصادر', 'Sources', 'Sources'),
                'segmented' => $label('مصادر مقسمة', 'Segmented', 'Segmentées'),
                'rights_pending' => $label('حقوق معلقة', 'Rights pending', 'Droits en attente'),
                'mapping_required' => $label('ربط مطلوب', 'Mapping required', 'Mapping requis'),
                'pii_redaction_required' => $label('حذف PII مطلوب', 'PII redaction', 'Caviardage PII'),
                'delivery_eligible' => $label('جاهز للطلاب', 'Student-ready', 'Prêt élèves'),
            ] as $key => $copy)
                <div class="modrik-panel p-4">
                    <div class="text-xs text-gray-500">{{ $copy }}</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950">{{ $metrics[$key] }}</div>
                </div>
            @endforeach
        </section>

        <div class="space-y-5">
            @foreach ($materials as $material)
                @php
                    $rights = is_array($material['rights'] ?? null) ? $material['rights'] : [];
                    $privacy = is_array($material['privacy'] ?? null) ? $material['privacy'] : [];
                    $mapping = is_array($material['curriculum_mapping'] ?? null) ? $material['curriculum_mapping'] : [];
                    $summary = is_array($material['content_summary'] ?? null) ? $material['content_summary'] : [];
                    $segments = is_array($material['segments'] ?? null) ? $material['segments'] : [];
                    $sourceClaims = is_array($material['source_claims'] ?? null) ? $material['source_claims'] : [];
                    $blockers = is_array($material['delivery_blockers'] ?? null) ? $material['delivery_blockers'] : [];
                @endphp

                <article class="modrik-panel" wire:key="real-pilot-material-{{ $material['source_id'] }}">
                    <div class="modrik-panel-header">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge color="info">{{ $material['kind'] }}</x-filament::badge>
                                <x-filament::badge color="gray">{{ $material['subject'] }}</x-filament::badge>
                                <x-filament::badge :color="($rights['status'] ?? '') === 'approved' ? 'success' : 'warning'">
                                    {{ $label('حقوق', 'Rights', 'Droits') }}: {{ $rights['status'] ?? 'unknown' }}
                                </x-filament::badge>
                                @if (($privacy['status'] ?? '') === 'pii_redaction_required')
                                    <x-filament::badge color="danger">PII_REDACTION_REQUIRED</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">{{ $privacy['status'] ?? 'privacy_unknown' }}</x-filament::badge>
                                @endif
                            </div>

                            <h2 class="mt-3 text-base font-bold text-gray-950">{{ $this->localizedTitle($material) }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ $this->mappingLabel($material) }}</p>
                        </div>

                        <x-filament::badge :color="($material['delivery_eligible'] ?? false) ? 'success' : 'warning'">
                            {{ ($material['delivery_eligible'] ?? false)
                                ? $label('مسموح بالتسليم', 'Delivery eligible', 'Diffusion autorisée')
                                : $label('غير متاح للطلاب', 'Not student-deliverable', 'Non diffusable aux élèves') }}
                        </x-filament::badge>
                    </div>

                    <div class="modrik-panel-body space-y-5">
                        <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 text-sm">
                            <div class="rounded-xl bg-gray-50 p-4">
                                <dt class="text-xs font-semibold text-gray-500">Source ID</dt>
                                <dd class="mt-1 break-words font-mono text-xs text-gray-900" dir="ltr">{{ $material['source_id'] }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4">
                                <dt class="text-xs font-semibold text-gray-500">{{ $label('حالة الربط', 'Mapping state', 'État du mapping') }}</dt>
                                <dd class="mt-1 font-semibold text-gray-950">{{ $mapping['status'] ?? 'unknown' }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4">
                                <dt class="text-xs font-semibold text-gray-500">{{ $label('اللغة', 'Language', 'Langue') }}</dt>
                                <dd class="mt-1 font-semibold text-gray-950">{{ strtoupper((string) ($material['language'] ?? '')) }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4">
                                <dt class="text-xs font-semibold text-gray-500">{{ $label('عدد الصفحات', 'Pages', 'Pages') }}</dt>
                                <dd class="mt-1 font-semibold text-gray-950">{{ $material['page_count'] ?? '—' }}</dd>
                            </div>
                        </dl>

                        @if ($sourceClaims !== [])
                            <section>
                                <h3 class="text-sm font-bold text-gray-950">{{ $label('بيانات مثبتة من المصدر', 'Source-verified metadata', 'Métadonnées vérifiées par la source') }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($sourceClaims as $key => $value)
                                        <x-filament::badge color="gray">{{ $key }}: {{ $value }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ($summary !== [])
                            <section>
                                <h3 class="text-sm font-bold text-gray-950">{{ $label('ملخص المحتوى', 'Content summary', 'Résumé du contenu') }}</h3>
                                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach ($summary as $key => $value)
                                        <div class="rounded-xl border border-gray-200 p-3">
                                            <dt class="text-xs font-semibold text-gray-500">{{ $key }}</dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                @if (is_array($value))
                                                    {{ implode(' · ', array_map('strval', $value)) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        @endif

                        @if ($blockers !== [])
                            <section>
                                <h3 class="text-sm font-bold text-gray-950">{{ $label('حواجز قبل التسليم للطالب', 'Student-delivery blockers', 'Blocages avant diffusion') }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($blockers as $blocker)
                                        <x-filament::badge color="warning">{{ $blocker }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ($segments !== [])
                            <section data-testid="material-segments-{{ $material['source_id'] }}">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-950">{{ $label('تقسيم المصدر', 'Source segmentation', 'Segmentation de la source') }}</h3>
                                        <p class="mt-1 text-xs text-gray-500">{{ $label('التقسيم مبني على فهرس الكتاب ونواتج التعلم الظاهرة في المصدر.', 'Segmentation follows the book TOC and source learning outcomes.', 'La segmentation suit le sommaire et les résultats d’apprentissage visibles dans la source.') }}</p>
                                    </div>
                                    <x-filament::badge color="info">{{ count($segments) }} {{ $label('جزء', 'segments', 'segments') }}</x-filament::badge>
                                </div>

                                <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ $label('الوحدة', 'Unit', 'Unité') }}</th>
                                                <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ $label('الموضوع', 'Topic', 'Sujet') }}</th>
                                                <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ $label('المهارات', 'Skill families', 'Familles de compétences') }}</th>
                                                <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ $label('صفحات الكتاب', 'Printed pages', 'Pages imprimées') }}</th>
                                                <th class="px-3 py-2 text-start font-semibold text-gray-600">{{ $label('صفحات PDF', 'PDF pages', 'Pages PDF') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($segments as $segment)
                                                <tr>
                                                    <td class="px-3 py-2 align-top">{{ $segment['unit'] ?? '—' }}</td>
                                                    <td class="px-3 py-2 align-top">{{ $segment['topic'] ?? '—' }}</td>
                                                    <td class="px-3 py-2 align-top">{{ implode(' · ', array_map('strval', $segment['skill_families'] ?? [])) }}</td>
                                                    <td class="px-3 py-2 align-top" dir="ltr">{{ $segment['printed_page_start'] ?? '—' }}–{{ $segment['printed_page_end'] ?? '—' }}</td>
                                                    <td class="px-3 py-2 align-top" dir="ltr">{{ $segment['pdf_page_start'] ?? '—' }}–{{ $segment['pdf_page_end'] ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @endif

                        <details class="rounded-xl border border-gray-200 bg-white p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-700">{{ $label('بصمة المصدر والتتبع', 'Source fingerprint & traceability', 'Empreinte et traçabilité') }}</summary>
                            <dl class="mt-3 space-y-3 text-xs">
                                <div>
                                    <dt class="font-semibold text-gray-500">SHA-256</dt>
                                    <dd class="mt-1 break-all font-mono text-gray-700" dir="ltr">{{ $material['sha256'] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">{{ $label('التخزين', 'Storage', 'Stockage') }}</dt>
                                    <dd class="mt-1 text-gray-700">{{ data_get($material, 'storage.state', 'unknown') }}</dd>
                                </div>
                                @if (is_string($rights['note'] ?? null))
                                    <div>
                                        <dt class="font-semibold text-gray-500">{{ $label('ملاحظة الحقوق', 'Rights note', 'Note sur les droits') }}</dt>
                                        <dd class="mt-1 leading-5 text-gray-700">{{ $rights['note'] }}</dd>
                                    </div>
                                @endif
                                @if (is_string($privacy['note'] ?? null))
                                    <div>
                                        <dt class="font-semibold text-gray-500">{{ $label('ملاحظة الخصوصية', 'Privacy note', 'Note de confidentialité') }}</dt>
                                        <dd class="mt-1 leading-5 text-gray-700">{{ $privacy['note'] }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
