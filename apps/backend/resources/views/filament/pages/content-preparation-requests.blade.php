<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-preparation-history">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-gray-500">
                    {{ $label(
                        'ابدأ من النطاق الأكاديمي والحالة. المعرّفات التقنية محفوظة للتتبع عند الحاجة فقط.',
                        'Start with academic scope and workflow state. Technical identifiers stay available for traceability only.',
                        'Commencez par le périmètre académique et l’état du flux. Les identifiants techniques restent disponibles pour la traçabilité.'
                    ) }}
                </p>
            </div>
            <x-filament::button
                tag="a"
                :href="\App\Filament\Pages\ContentPreparationWizard::getUrl()"
                icon="heroicon-o-plus"
            >
                {{ $label('طلب إعداد جديد', 'New preparation request', 'Nouvelle demande') }}
            </x-filament::button>
        </div>

        <section class="modrik-panel" aria-labelledby="preparation-history-filter-title">
            <div class="modrik-panel-header">
                <div>
                    <h2 id="preparation-history-filter-title" class="modrik-panel-title">
                        {{ $label('تصفية سجل الإعداد', 'Filter preparation history', 'Filtrer l’historique') }}
                    </h2>
                    <p class="modrik-panel-subtitle">
                        {{ $label(
                            'يعرض آخر 100 طلب مع إبراز ما يحتاج قرارًا أو متابعة.',
                            'Shows the latest 100 requests with workflow state and actionable context first.',
                            'Affiche les 100 dernières demandes en privilégiant l’état et le contexte actionnable.'
                        ) }}
                    </p>
                </div>
                <x-filament::badge color="gray">
                    {{ count($rows) }} {{ $label('نتيجة', 'results', 'résultats') }}
                </x-filament::badge>
            </div>
            <div class="modrik-panel-body">
                <label class="block max-w-xs space-y-2">
                    <span class="text-sm font-semibold text-gray-950">{{ $label('الحالة', 'Status', 'Statut') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="statusFilter">
                            <option value="all">{{ $label('كل الحالات', 'All statuses', 'Tous les statuts') }}</option>
                            <option value="ready">ready</option>
                            <option value="superseded">superseded</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        </section>

        @if ($rows === [])
            <section class="modrik-panel">
                <x-admin.empty-state
                    :title="$label('لا توجد طلبات إعداد محفوظة', 'No saved preparation requests', 'Aucune demande enregistrée')"
                    :message="$label('أنشئ أول طلب من معالج إعداد المحتوى وسيظهر هنا مع حالة دورة العمل.', 'Create the first request from the Content Preparation wizard and its workflow state will appear here.', 'Créez la première demande dans l’assistant de préparation ; son état apparaîtra ici.')"
                >
                    <x-filament::button
                        tag="a"
                        :href="\App\Filament\Pages\ContentPreparationWizard::getUrl()"
                        icon="heroicon-o-plus"
                    >
                        {{ $label('إنشاء طلب', 'Create request', 'Créer une demande') }}
                    </x-filament::button>
                </x-admin.empty-state>
            </section>
        @else
            <div class="space-y-4">
                @foreach ($rows as $row)
                    @php
                        $statusColor = $row['status'] === 'ready' ? 'success' : ($row['status'] === 'superseded' ? 'gray' : 'warning');
                        $scopeTitle = trim(implode(' · ', array_filter([
                            $row['track_reference'],
                            $row['year_level'],
                        ])));
                    @endphp

                    <article class="modrik-panel" wire:key="preparation-request-{{ $row['id'] }}">
                        <div class="modrik-panel-header">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge :color="$statusColor">{{ $row['status'] }}</x-filament::badge>
                                    @foreach ($row['locales'] as $locale)
                                        <x-filament::badge color="info">{{ strtoupper($locale) }}</x-filament::badge>
                                    @endforeach
                                </div>
                                <h2 class="mt-3 text-base font-bold text-gray-950">
                                    {{ $scopeTitle !== '' ? $scopeTitle : $label('نطاق أكاديمي غير مسمى', 'Unnamed academic scope', 'Périmètre académique sans nom') }}
                                </h2>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $label('أُنشئ', 'Created', 'Créée') }}: {{ $row['created_at'] }}
                                </p>
                            </div>

                            <x-filament::button
                                tag="a"
                                :href="\App\Filament\Pages\ContentPreparationWizard::getUrl(['request' => $row['id']])"
                                icon="heroicon-o-arrow-up-right"
                            >
                                {{ $label('فتح الطلب', 'Open request', 'Ouvrir la demande') }}
                            </x-filament::button>
                        </div>

                        <div class="modrik-panel-body space-y-5">
                            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-xl bg-gray-50 p-3">
                                    <dt class="text-xs font-semibold text-gray-500">{{ $label('المسار', 'Track', 'Parcours') }}</dt>
                                    <dd class="mt-1 break-words text-sm font-semibold text-gray-950">{{ $row['track_reference'] ?: '—' }}</dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-3">
                                    <dt class="text-xs font-semibold text-gray-500">{{ $label('الصف/السنة', 'Year level', 'Niveau') }}</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-950">{{ $row['year_level'] ?: '—' }}</dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-3">
                                    <dt class="text-xs font-semibold text-gray-500">{{ $label('الجهة/المجلس', 'Board', 'Conseil') }}</dt>
                                    <dd class="mt-1 break-words text-sm font-semibold text-gray-950">{{ $row['board_reference'] ?: '—' }}</dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-3">
                                    <dt class="text-xs font-semibold text-gray-500">{{ $label('نسخة المنهج', 'Syllabus version', 'Version du programme') }}</dt>
                                    <dd class="mt-1 break-words text-sm font-semibold text-gray-950">{{ $row['syllabus_version'] ?: '—' }}</dd>
                                </div>
                            </dl>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div>
                                    <div class="text-xs font-semibold text-gray-500">{{ $label('المواد', 'Subjects', 'Matières') }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($row['subject_references'] as $subject)
                                            <x-filament::badge color="gray">{{ $subject }}</x-filament::badge>
                                        @empty
                                            <span class="text-sm text-gray-400">—</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-gray-500">{{ $label('أنواع المحتوى', 'Content types', 'Types de contenu') }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($row['content_types'] as $type)
                                            <x-filament::badge color="gray">{{ $type }}</x-filament::badge>
                                        @empty
                                            <span class="text-sm text-gray-400">—</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            @if ($row['superseded_by_request_id'])
                                <x-admin.operational-banner
                                    severity="warning"
                                    :title="$label('تم استبدال هذا الطلب', 'Request superseded', 'Demande remplacée')"
                                    :message="$label('يوجد طلب أحدث لنفس دورة الإعداد. افتح النسخة الأحدث قبل متابعة أي إجراء.', 'A newer request exists for this preparation lifecycle. Use the newer version before continuing.', 'Une demande plus récente existe pour ce cycle. Utilisez-la avant de poursuivre.')"
                                >
                                    <div class="modrik-code mt-2 text-xs text-gray-500">{{ $row['superseded_by_request_id'] }}</div>
                                </x-admin.operational-banner>
                            @endif

                            <details class="rounded-xl border border-gray-200 bg-white p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-gray-700">
                                    {{ $label('بيانات التتبع التقنية', 'Technical traceability', 'Traçabilité technique') }}
                                </summary>
                                <dl class="mt-3 grid gap-3 text-xs md:grid-cols-2">
                                    <div>
                                        <dt class="font-semibold text-gray-500">Request ID</dt>
                                        <dd class="modrik-code mt-1 text-gray-700">{{ $row['id'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Schema</dt>
                                        <dd class="modrik-code mt-1 text-gray-700">{{ $row['schema_version'] }}</dd>
                                    </div>
                                    <div class="md:col-span-2">
                                        <dt class="font-semibold text-gray-500">settings_hash</dt>
                                        <dd class="modrik-code mt-1 text-gray-700">{{ $row['settings_hash'] }}</dd>
                                    </div>
                                </dl>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
