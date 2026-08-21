<x-filament-panels::page>
    @if (! $available)
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Runtime diagnostics are unavailable or disabled. Normal Admin workflows remain available.
            </div>
        </x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section heading="Runtime">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">Environment</dt><dd>{{ $summary['environment'] }}</dd></div>
                    <div><dt class="font-medium">Build</dt><dd>{{ $summary['build_identity'] ?? 'not supplied' }}</dd></div>
                    <div><dt class="font-medium">Framework</dt><dd>{{ $summary['framework'] }}</dd></div>
                    <div><dt class="font-medium">PHP</dt><dd>{{ $summary['php'] }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Application logs">
                <p class="text-2xl font-semibold">{{ $summary['diagnostic_events'] }}</p>
                <p class="text-xs text-gray-500">Bounded diagnostic envelope rows.</p>
            </x-filament::section>

            <x-filament::section heading="Durable diagnostic audit">
                <p class="text-sm text-gray-600 dark:text-gray-300">Privileged diagnostic actions are append-only rows with internal actor IDs only.</p>
            </x-filament::section>

            <x-filament::section heading="Outbox / recovery state">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">Outbox events</dt><dd>{{ $summary['outbox_events'] }}</dd></div>
                    <div><dt class="font-medium">Delivery attempts</dt><dd>{{ $summary['outbox_delivery_attempts'] }}</dd></div>
                </dl>
            </x-filament::section>
        </div>

        <x-filament::section heading="Filters">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <label class="text-sm">
                    <span class="font-medium">Correlation ID</span>
                    <input wire:model.live.debounce.300ms="correlationId" type="text" maxlength="96" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900" />
                </label>
                <label class="text-sm">
                    <span class="font-medium">Severity</span>
                    <select wire:model.live="severity" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="all">All</option><option value="debug">Debug</option><option value="info">Info</option><option value="warn">Warn</option><option value="error">Error</option><option value="critical">Critical</option>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="font-medium">Surface</span>
                    <select wire:model.live="surface" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="all">All</option><option value="backend">Backend</option><option value="admin">Admin</option><option value="web">Web</option><option value="public">Public</option><option value="mobile">Mobile</option>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="font-medium">Data class</span>
                    <select wire:model.live="dataClass" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="all">All</option><option value="application_log">Application log</option><option value="audit">Audit</option>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="font-medium">Stable code</span>
                    <input wire:model.live.debounce.300ms="stableCode" type="text" maxlength="96" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900" />
                </label>
                <label class="text-sm">
                    <span class="font-medium">Window (hours)</span>
                    <input wire:model.live="hours" type="number" min="1" max="168" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900" />
                </label>
            </div>
            <div class="mt-4">
                <x-filament::button wire:click="downloadDiagnosticBundle" icon="heroicon-o-arrow-down-tray">
                    Export sanitized JSON
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Recent sanitized events">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left">
                            <th class="px-2 py-2">Time</th><th class="px-2 py-2">Class</th><th class="px-2 py-2">Severity</th><th class="px-2 py-2">Surface</th><th class="px-2 py-2">Code</th><th class="px-2 py-2">Correlation</th><th class="px-2 py-2">Route/action</th><th class="px-2 py-2">Metadata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($events as $event)
                            <tr>
                                <td class="whitespace-nowrap px-2 py-2">{{ $event['occurred_at'] }}</td>
                                <td class="px-2 py-2">{{ $event['data_class'] }}</td>
                                <td class="px-2 py-2">{{ $event['severity'] }}</td>
                                <td class="px-2 py-2">{{ $event['surface'] }}</td>
                                <td class="px-2 py-2">{{ $event['stable_code'] ?? '—' }}</td>
                                <td class="px-2 py-2 font-mono text-xs">{{ $event['correlation_id'] }}</td>
                                <td class="px-2 py-2">{{ $event['route'] ?? $event['action'] ?? '—' }}</td>
                                <td class="max-w-md px-2 py-2 font-mono text-xs">{{ json_encode($event['metadata'], JSON_UNESCAPED_SLASHES) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-2 py-6 text-center text-gray-500">No matching diagnostic events.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
