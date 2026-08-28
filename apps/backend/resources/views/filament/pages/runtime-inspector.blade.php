<x-filament-panels::page>
    @if (! $available)
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">Runtime diagnostics are unavailable or disabled. Normal Admin workflows remain available.</div>
        </x-filament::section>
    @else
        @php($runtimeStatus = $summary['runtime_status'] ?? 'warn')
        @php($statusColor = $runtimeStatus === 'ok' ? 'success' : ($runtimeStatus === 'fail' ? 'danger' : 'warning'))

        <x-filament::section heading="Runtime health">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge :color="$statusColor">{{ strtoupper($runtimeStatus) }}</x-filament::badge>
                <span class="text-sm text-gray-600 dark:text-gray-300">Local runtime checks only.</span>
            </div>
            @if (($summary['runtime_reasons'] ?? []) !== [])
                <ul class="mt-3 list-disc space-y-1 ps-5 text-sm">
                    @foreach ($summary['runtime_reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
                </ul>
            @endif
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section heading="Runtime">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">Environment</dt><dd>{{ $summary['environment'] }}</dd></div>
                    <div><dt class="font-medium">Framework</dt><dd>{{ $summary['framework'] }}</dd></div>
                    <div><dt class="font-medium">PHP</dt><dd>{{ $summary['php'] }} / {{ $summary['php_sapi'] }}</dd></div>
                    <div><dt class="font-medium">Debug</dt><dd>{{ $summary['debug'] ? 'ON' : 'OFF' }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Release identity">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">Backend</dt><dd class="break-all font-mono text-xs">{{ $summary['backend_release_sha'] ?? 'missing' }}</dd></div>
                    <div><dt class="font-medium">Durable</dt><dd class="break-all font-mono text-xs">{{ $summary['durable_release_sha'] ?? 'missing' }}</dd></div>
                    <div><dt class="font-medium">Web</dt><dd class="break-all font-mono text-xs">{{ $summary['web_release_sha'] ?? 'missing' }}</dd></div>
                    <div><dt class="font-medium">Restart marker</dt><dd class="text-xs">{{ $summary['web_restart_marker_at'] ?? 'missing' }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Database / state">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">DB</dt><dd>{{ $summary['db_ok'] ? 'OK' : 'FAIL' }} — {{ $summary['db_driver'] }} @ {{ $summary['db_latency_ms'] ?? '—' }} ms</dd></div>
                    <div><dt class="font-medium">Cache</dt><dd>{{ $summary['cache_store'] }}</dd></div>
                    <div><dt class="font-medium">Session</dt><dd>{{ $summary['session_driver'] }}</dd></div>
                    <div><dt class="font-medium">Queue</dt><dd>{{ $summary['queue_connection'] }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="SMTP runtime">
                <dl class="space-y-2 text-sm">
                    <div><dt class="font-medium">Source</dt><dd>{{ $summary['mail_source'] }}</dd></div>
                    <div><dt class="font-medium">Enabled</dt><dd>{{ $summary['enabled_smtp_providers'] }}</dd></div>
                    @if (is_array($summary['active_smtp_provider'] ?? null))
                        <div><dt class="font-medium">Provider</dt><dd>{{ $summary['active_smtp_provider']['name'] }}</dd></div>
                        <div><dt class="font-medium">Server</dt><dd class="font-mono text-xs">{{ $summary['active_smtp_provider']['host'] }}:{{ $summary['active_smtp_provider']['port'] }}</dd></div>
                        <div><dt class="font-medium">Security</dt><dd>{{ $summary['active_smtp_provider']['security'] }}</dd></div>
                        <div><dt class="font-medium">Last test</dt><dd>{{ $summary['active_smtp_provider']['last_test_status'] ?? 'not tested' }} / {{ $summary['active_smtp_provider']['last_error_code'] ?? '—' }}</dd></div>
                    @endif
                </dl>
            </x-filament::section>
        </div>

        <x-filament::section heading="Laravel paths and caches">
            <dl class="grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="font-medium">Base path</dt><dd class="break-all font-mono text-xs">{{ $summary['base_path'] }}</dd></div>
                <div><dt class="font-medium">Resource views</dt><dd class="break-all font-mono text-xs">{{ $summary['resource_views_path'] }}</dd></div>
                <div><dt class="font-medium">View paths</dt><dd class="space-y-1">@foreach ($summary['view_paths'] as $path)<div class="break-all font-mono text-xs">{{ $path }}</div>@endforeach</dd></div>
                <div><dt class="font-medium">View path status</dt><dd><x-filament::badge :color="$summary['view_path_status'] === 'ok' ? 'success' : 'danger'">{{ strtoupper($summary['view_path_status']) }}</x-filament::badge></dd></div>
                <div><dt class="font-medium">Config cache</dt><dd>{{ $summary['config_cached'] ? 'YES' : 'NO' }} — <span class="break-all font-mono text-xs">{{ $summary['config_cache_path'] }}</span></dd></div>
                <div><dt class="font-medium">Route cache</dt><dd>{{ $summary['route_cached'] ? 'YES' : 'NO' }}</dd></div>
                <div><dt class="font-medium">Storage writable</dt><dd>{{ $summary['storage_writable'] ? 'YES' : 'NO' }}</dd></div>
                <div><dt class="font-medium">bootstrap/cache writable</dt><dd>{{ $summary['bootstrap_cache_writable'] ? 'YES' : 'NO' }}</dd></div>
            </dl>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section heading="Diagnostic events"><p class="text-2xl font-semibold">{{ $summary['diagnostic_events'] }}</p></x-filament::section>
            <x-filament::section heading="Outbox events"><p class="text-2xl font-semibold">{{ $summary['outbox_events'] }}</p></x-filament::section>
            <x-filament::section heading="Delivery attempts"><p class="text-2xl font-semibold">{{ $summary['outbox_delivery_attempts'] }}</p></x-filament::section>
        </div>

        <x-filament::section heading="Filters">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <label class="text-sm"><span class="font-medium">Correlation ID</span><input wire:model.live.debounce.300ms="correlationId" type="text" maxlength="96" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                <label class="text-sm"><span class="font-medium">Severity</span><select wire:model.live="severity" class="mt-1 block w-full rounded-lg border-gray-300"><option value="all">All</option><option value="debug">Debug</option><option value="info">Info</option><option value="warn">Warn</option><option value="error">Error</option><option value="critical">Critical</option></select></label>
                <label class="text-sm"><span class="font-medium">Surface</span><select wire:model.live="surface" class="mt-1 block w-full rounded-lg border-gray-300"><option value="all">All</option><option value="backend">Backend</option><option value="admin">Admin</option><option value="web">Web</option><option value="public">Public</option><option value="mobile">Mobile</option></select></label>
                <label class="text-sm"><span class="font-medium">Data class</span><select wire:model.live="dataClass" class="mt-1 block w-full rounded-lg border-gray-300"><option value="all">All</option><option value="application_log">Application log</option><option value="audit">Audit</option></select></label>
                <label class="text-sm"><span class="font-medium">Stable code</span><input wire:model.live.debounce.300ms="stableCode" type="text" maxlength="96" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                <label class="text-sm"><span class="font-medium">Window (hours)</span><input wire:model.live="hours" type="number" min="1" max="168" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            </div>
            <div class="mt-4"><x-filament::button wire:click="downloadDiagnosticBundle" icon="heroicon-o-arrow-down-tray">Export sanitized JSON</x-filament::button></div>
        </x-filament::section>

        <x-filament::section heading="Recent sanitized events">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead><tr class="text-left"><th class="px-2 py-2">Time</th><th class="px-2 py-2">Class</th><th class="px-2 py-2">Severity</th><th class="px-2 py-2">Surface</th><th class="px-2 py-2">Code</th><th class="px-2 py-2">Correlation</th><th class="px-2 py-2">Route/action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <tr><td class="px-2 py-2">{{ $event['occurred_at'] }}</td><td class="px-2 py-2">{{ $event['data_class'] }}</td><td class="px-2 py-2">{{ $event['severity'] }}</td><td class="px-2 py-2">{{ $event['surface'] }}</td><td class="px-2 py-2">{{ $event['stable_code'] ?? '—' }}</td><td class="px-2 py-2 font-mono text-xs">{{ $event['correlation_id'] }}</td><td class="px-2 py-2">{{ $event['route'] ?? $event['action'] ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-2 py-6 text-center text-gray-500">No matching diagnostic events.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
