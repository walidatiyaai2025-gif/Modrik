<x-filament-panels::page>
    @php
        $summary = $this->runtimeSummary();
        $events = $this->events();
        $outbox = $this->outboxSummary();
    @endphp

    <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge color="primary">{{ __('observability.application_logs') }}</x-filament::badge>
                <x-filament::badge color="warning">{{ __('observability.durable_audit') }}</x-filament::badge>
                <x-filament::badge color="gray">{{ __('observability.outbox_recovery') }}</x-filament::badge>
            </div>
            <div class="flex items-center gap-2" aria-label="{{ __('observability.language') }}">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)
                    <x-filament::button
                        size="sm"
                        :color="app()->getLocale() === $locale ? 'primary' : 'gray'"
                        wire:click="setLocale('{{ $locale }}')"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>

        <x-filament::section :heading="__('observability.runtime_summary')" :description="__('observability.runtime_summary_help')">
            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    'environment' => $summary['environment'] ?? 'unknown',
                    'build_ref' => $summary['build_ref'] ?? __('observability.not_configured'),
                    'release_version' => $summary['release_version'] ?? __('observability.not_configured'),
                    'php_version' => $summary['php_version'] ?? 'unknown',
                    'laravel_version' => $summary['laravel_version'] ?? 'unknown',
                    'database_driver' => $summary['database_driver'] ?? 'unknown',
                    'retention_days' => $summary['retention_days'] ?? __('observability.owner_controlled'),
                ] as $label => $value)
                    <div class="min-w-0 rounded-xl border p-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('observability.summary.'.$label) }}</dt>
                        <dd class="mt-1 break-all font-mono text-sm">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if (! ($summary['storage_available'] ?? false))
                <div class="mt-4 rounded-xl border border-warning-300 p-4" role="status">
                    <strong>{{ __('observability.storage_unavailable_title') }}</strong>
                    <p class="mt-1 text-sm">{{ __('observability.storage_unavailable_body') }}</p>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('observability.filters')" :description="__('observability.filters_help')">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.correlation_id') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live.debounce.250ms="correlationId" autocomplete="off" dir="ltr" />
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.stable_code') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live.debounce.250ms="stableCode" autocomplete="off" dir="ltr" />
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.window_minutes') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" min="5" max="10080" wire:model.live.debounce.250ms="windowMinutes" />
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.severity') }}</span>
                    <select wire:model.live="severity" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5">
                        <option value="all">{{ __('observability.all') }}</option>
                        @foreach (['debug', 'info', 'warn', 'error', 'critical'] as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.surface') }}</span>
                    <select wire:model.live="surface" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5">
                        <option value="all">{{ __('observability.all') }}</option>
                        <option value="backend">backend</option>
                        <option value="admin">admin</option>
                    </select>
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium">{{ __('observability.event_class') }}</span>
                    <select wire:model.live="eventClass" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5">
                        <option value="all">{{ __('observability.all') }}</option>
                        <option value="application_log">{{ __('observability.application_logs') }}</option>
                        <option value="durable_audit">{{ __('observability.durable_audit') }}</option>
                    </select>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <x-filament::button color="gray" wire:click="resetFilters">
                    {{ __('observability.reset_filters') }}
                </x-filament::button>
                <x-filament::button wire:click="exportDiagnostics" wire:loading.attr="disabled">
                    {{ __('observability.export') }}
                </x-filament::button>
            </div>
            <p class="mt-3 text-xs text-gray-500">{{ __('observability.export_help') }}</p>
        </x-filament::section>

        <x-filament::section :heading="__('observability.recent_events')" :description="__('observability.recent_events_help')">
            @if ($events === [])
                <p role="status" class="text-sm text-gray-500">{{ __('observability.no_events') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-start text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-2 text-start">{{ __('observability.time') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.class') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.severity') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.surface') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.category') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.correlation_id') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.route') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.stable_code') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.result') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.duration') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('observability.safe_metadata') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr class="border-b align-top">
                                    <td class="whitespace-nowrap px-3 py-3 font-mono text-xs">{{ $event['recorded_at'] ?? '—' }}</td>
                                    <td class="px-3 py-3"><x-filament::badge color="gray">{{ $event['event_class'] ?? '—' }}</x-filament::badge></td>
                                    <td class="px-3 py-3">{{ $event['severity'] ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $event['surface'] ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $event['category'] ?? '—' }}</td>
                                    <td class="max-w-64 break-all px-3 py-3 font-mono text-xs" dir="ltr">{{ $event['correlation_id'] ?? '—' }}</td>
                                    <td class="max-w-64 break-all px-3 py-3 font-mono text-xs" dir="ltr">{{ $event['route_name'] ?? '—' }}</td>
                                    <td class="max-w-56 break-all px-3 py-3 font-mono text-xs" dir="ltr">{{ $event['stable_code'] ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $event['outcome'] ?? '—' }} @if (($event['status_code'] ?? null) !== null) ({{ $event['status_code'] }}) @endif</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ ($event['duration_ms'] ?? null) !== null ? $event['duration_ms'].' ms' : '—' }}</td>
                                    <td class="max-w-80 break-all px-3 py-3 font-mono text-xs" dir="ltr">{{ json_encode($event['metadata'] ?? [], JSON_UNESCAPED_SLASHES) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('observability.outbox_recovery')" :description="__('observability.outbox_help')">
            @if (! ($outbox['available'] ?? false))
                <p role="status" class="text-sm text-gray-500">{{ __('observability.outbox_unavailable') }}</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border p-4">
                        <div class="text-xs text-gray-500">{{ __('observability.pending_events') }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $outbox['pending_events'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border p-4">
                        <div class="text-xs text-gray-500">{{ __('observability.published_events') }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $outbox['published_events'] ?? 0 }}</div>
                    </div>
                    @foreach (($outbox['delivery_attempts'] ?? []) as $status => $count)
                        <div class="rounded-xl border p-4">
                            <div class="break-all text-xs text-gray-500">{{ $status }}</div>
                            <div class="mt-1 text-xl font-semibold">{{ $count }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <p class="text-xs text-gray-500" role="note">{{ __('observability.privacy_note') }}</p>
    </div>
</x-filament-panels::page>
