<x-filament-panels::page>
    <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6">
        @php
            $rows = $this->rows();
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-filament::button
                tag="a"
                :href="\App\Filament\Pages\ContentPreparationWizard::getUrl()"
                icon="heroicon-o-plus"
            >
                {{ $label('طلب إعداد جديد', 'New preparation request', 'Nouvelle demande') }}
            </x-filament::button>

            <div class="flex flex-wrap items-center gap-2">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $localeLabel)
                    <x-filament::button size="sm" :color="app()->getLocale() === $locale ? 'primary' : 'gray'" wire:click="setLocale('{{ $locale }}')">
                        {{ $localeLabel }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>

        <x-filament::section>
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-52 space-y-2">
                    <span class="text-sm font-medium">{{ $label('الحالة', 'Status', 'Statut') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="statusFilter">
                            <option value="all">{{ $label('الكل', 'All', 'Tous') }}</option>
                            <option value="ready">ready</option>
                            <option value="superseded">superseded</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <p class="text-sm text-gray-500">
                    {{ $label(
                        'يعرض آخر 100 طلب. افتح أي طلب لاسترجاع إعداداته المحفوظة والـPrompt والـBundle ومتابعة الـZIP.',
                        'Shows the latest 100 requests. Open any request to restore its saved settings, prompt, bundle, and returned ZIP workflow.',
                        'Affiche les 100 dernières demandes. Ouvrez-en une pour restaurer ses paramètres, son prompt, son bundle et le flux ZIP.'
                    ) }}
                </p>
            </div>
        </x-filament::section>

        @if ($rows === [])
            <x-filament::section>
                <div class="py-10 text-center">
                    <h3 class="font-semibold">{{ $label('لا توجد طلبات إعداد محفوظة', 'No saved preparation requests', 'Aucune demande enregistrée') }}</h3>
                    <p class="mt-2 text-sm text-gray-500">{{ $label('أنشئ أول طلب من معالج إعداد المحتوى.', 'Create the first request from the Content Preparation wizard.', 'Créez la première demande depuis l’assistant de préparation.') }}</p>
                </div>
            </x-filament::section>
        @else
            <div class="space-y-4">
                @foreach ($rows as $row)
                    @php
                        $statusColor = $row['status'] === 'ready' ? 'success' : ($row['status'] === 'superseded' ? 'gray' : 'warning');
                    @endphp
                    <x-filament::section wire:key="preparation-request-{{ $row['id'] }}">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge :color="$statusColor">{{ $row['status'] }}</x-filament::badge>
                                        <x-filament::badge color="gray">Schema {{ $row['schema_version'] }}</x-filament::badge>
                                        @foreach ($row['locales'] as $locale)
                                            <x-filament::badge color="info">{{ strtoupper($locale) }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 break-all font-mono text-xs">{{ $row['id'] }}</p>
                                </div>
                                <div class="text-xs text-gray-500">{{ $row['created_at'] }}</div>
                            </div>

                            <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div><dt class="font-medium">{{ $label('المسار', 'Track', 'Parcours') }}</dt><dd class="break-all">{{ $row['track_reference'] ?: '—' }}</dd></div>
                                <div><dt class="font-medium">{{ $label('الصف/السنة', 'Year level', 'Niveau') }}</dt><dd>{{ $row['year_level'] ?: '—' }}</dd></div>
                                <div><dt class="font-medium">{{ $label('الجهة/المجلس', 'Board', 'Conseil') }}</dt><dd>{{ $row['board_reference'] ?: '—' }}</dd></div>
                                <div><dt class="font-medium">{{ $label('نسخة المنهج', 'Syllabus version', 'Version du programme') }}</dt><dd>{{ $row['syllabus_version'] ?: '—' }}</dd></div>
                            </dl>

                            <div class="grid gap-3 text-sm md:grid-cols-2">
                                <div>
                                    <div class="font-medium">{{ $label('المواد', 'Subjects', 'Matières') }}</div>
                                    <div class="mt-1 break-words text-gray-600 dark:text-gray-300">{{ $row['subject_references'] === [] ? '—' : implode(', ', $row['subject_references']) }}</div>
                                </div>
                                <div>
                                    <div class="font-medium">{{ $label('أنواع المحتوى', 'Content types', 'Types de contenu') }}</div>
                                    <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $row['content_types'] === [] ? '—' : implode(', ', $row['content_types']) }}</div>
                                </div>
                            </div>

                            @if ($row['superseded_by_request_id'])
                                <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm dark:border-warning-700 dark:bg-warning-950/30">
                                    {{ $label('تم استبدال هذا الطلب بطلب أحدث:', 'This request was superseded by:', 'Cette demande a été remplacée par :') }}
                                    <span class="break-all font-mono text-xs">{{ $row['superseded_by_request_id'] }}</span>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                                <div class="text-xs text-gray-500">
                                    settings_hash: <span class="break-all font-mono">{{ $row['settings_hash'] }}</span>
                                </div>
                                <x-filament::button
                                    tag="a"
                                    :href="\App\Filament\Pages\ContentPreparationWizard::getUrl(['request' => $row['id']])"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                >
                                    {{ $label('فتح الطلب', 'Open request', 'Ouvrir la demande') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
