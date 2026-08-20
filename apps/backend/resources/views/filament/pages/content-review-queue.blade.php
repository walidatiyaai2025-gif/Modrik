<x-filament-panels::page>
    <div
        dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
        style="--modrik-brand: {{ config('brand.colors.primary') }}; --modrik-navy: {{ config('brand.colors.navy') }};"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium" for="status-filter">{{ __('admin.review.filter') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="status-filter" wire:model.live="statusFilter">
                        <option value="all">{{ __('admin.status.all') }}</option>
                        @foreach (['staged', 'validated', 'reviewed', 'imported', 'published', 'superseded', 'rejected', 'validating'] as $status)
                            <option value="{{ $status }}">{{ __('admin.status.'.$status) }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div class="flex items-center gap-2" aria-label="{{ __('admin.language') }}">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)
                    <x-filament::button size="sm" :color="app()->getLocale() === $locale ? 'primary' : 'gray'" wire:click="setLocale('{{ $locale }}')">
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>

        @php
            $rows = $this->queueRows();
            $pendingPublication = collect($rows)->firstWhere('id', $pendingPublicationImportId);
            $lifecycleStatuses = ['staged', 'validated', 'reviewed', 'imported', 'published'];
            $remediationFor = static function (?string $code): string {
                if (! is_string($code) || $code === '') {
                    return '';
                }

                $key = 'admin.review.remediation.'.$code;

                return \Illuminate\Support\Facades\Lang::has($key)
                    ? __($key)
                    : __('admin.review.remediation.default');
            };
        @endphp
        @if ($rows === [])
            <x-filament::section>
                <div class="py-12 text-center">
                    <h3 class="text-base font-semibold">{{ __('admin.review.empty_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.review.empty_body') }}</p>
                    <div class="mt-5">
                        <x-filament::button tag="a" :href="\App\Filament\Pages\ContentPreparationWizard::getUrl()">
                            {{ __('admin.actions.new_preparation') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="space-y-5">
                @foreach ($rows as $row)
                    @php
                        $status = $row['status'];
                        $decision = $row['review_decision'];
                        $dryRun = $row['dry_run_summary'];
                        $validation = $row['validation_summary'];
                        $stale = $row['request_status'] === 'superseded' || $row['superseded_by_request_id'] !== null || $status === 'superseded';
                        $statusColor = match ($status) {
                            'published' => 'success',
                            'rejected', 'superseded' => 'danger',
                            'reviewed', 'imported', 'validated' => 'warning',
                            default => 'gray',
                        };
                        $lifecyclePosition = array_search($status, $lifecycleStatuses, true);
                        $nextLifecycleStatus = null;
                        if ($lifecyclePosition !== false && $lifecyclePosition < count($lifecycleStatuses) - 1) {
                            if ($status !== 'reviewed' || $decision === 'approved') {
                                $nextLifecycleStatus = $lifecycleStatuses[$lifecyclePosition + 1];
                            }
                        }
                        $reasonReady = trim((string) ($reasons[$row['id']] ?? '')) !== '';
                    @endphp
                    <x-filament::section wire:key="content-import-{{ $row['id'] }}">
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge :color="$statusColor">{{ __('admin.status.'.$status) }}</x-filament::badge>
                                        @if ($decision !== null)
                                            <x-filament::badge color="gray">{{ __('admin.decisions.'.$decision) }}</x-filament::badge>
                                        @endif
                                        @if ($row['operation_state'] === 'failed')
                                            <x-filament::badge color="danger">{{ __('admin.status.failed') }}</x-filament::badge>
                                        @endif
                                    </div>
                                    <p class="break-all font-mono text-xs">{{ $row['id'] }}</p>
                                    <p class="text-sm text-gray-500">{{ __('admin.review.created') }}: {{ $row['created_at'] }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($row['preparation_request_id'] !== null)
                                        <x-filament::button color="gray" size="sm" tag="a" :href="$this->preparationUrl($row['preparation_request_id'])">
                                            {{ __('admin.actions.open_preparation') }}
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button color="gray" size="sm" wire:click="selectImport('{{ $row['id'] }}')">
                                        {{ $selectedImportId === $row['id'] ? __('admin.actions.hide_audit') : __('admin.actions.show_audit') }}
                                    </x-filament::button>
                                </div>
                            </div>

                            @if ($stale)
                                <div role="alert" class="rounded-xl border p-4 text-sm">
                                    <strong>{{ __('admin.review.stale_title') }}</strong>
                                    <p>{{ __('admin.review.stale_body') }}</p>
                                    @if ($row['superseded_by_request_id'] !== null)
                                        <p class="mt-2 break-all font-mono text-xs">{{ $row['superseded_by_request_id'] }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($lifecyclePosition !== false)
                                <div class="rounded-xl border p-4 text-sm" role="group" aria-label="{{ __('admin.review.lifecycle_title') }}">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <strong>{{ __('admin.review.lifecycle_title') }}</strong>
                                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.review.lifecycle_help') }}</p>
                                        </div>
                                        <div class="text-xs">
                                            <span>{{ __('admin.review.current_step', ['status' => __('admin.status.'.$status)]) }}</span>
                                            @if ($nextLifecycleStatus !== null)
                                                <span class="mx-1" aria-hidden="true">·</span>
                                                <span>{{ __('admin.review.next_step', ['status' => __('admin.status.'.$nextLifecycleStatus)]) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <ol class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('admin.review.lifecycle_title') }}">
                                        @foreach ($lifecycleStatuses as $stepIndex => $stepStatus)
                                            <li
                                                class="min-w-0 rounded-lg border p-2"
                                                @if ($stepIndex === $lifecyclePosition) aria-current="step" @endif
                                            >
                                                <x-filament::badge :color="$stepIndex < $lifecyclePosition ? 'success' : ($stepIndex === $lifecyclePosition ? 'primary' : 'gray')">
                                                    {{ __('admin.status.'.$stepStatus) }}
                                                </x-filament::badge>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif

                            <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div><dt class="font-medium">{{ __('admin.fields.request_id') }}</dt><dd class="break-all font-mono text-xs">{{ $row['preparation_request_id'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.schema_version') }}</dt><dd>{{ $row['schema_version'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.rights_status') }}</dt><dd>{{ $row['rights_status'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.checkpoint') }}</dt><dd>{{ $row['operation_checkpoint'] ?? '—' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium">{{ __('admin.fields.settings_hash') }}</dt><dd class="break-all font-mono text-xs">{{ $row['settings_hash'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.operation_attempts') }}</dt><dd>{{ $row['operation_attempts'] }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.publication_attempts') }}</dt><dd>{{ $row['publication_attempt_count'] }}</dd></div>
                            </dl>

                            @if ($row['last_error_code'] !== null)
                                <div role="alert" class="rounded-xl border border-danger-300 p-4 text-sm">
                                    <strong>{{ __('admin.review.last_error') }}: <code dir="ltr">{{ $row['last_error_code'] }}</code></strong>
                                    <p class="mt-2"><span class="font-medium">{{ __('admin.review.remediation_label') }}:</span> {{ $remediationFor($row['last_error_code']) }}</p>
                                    <p class="mt-2 text-xs text-gray-500">{{ __('admin.review.failure_help') }}</p>
                                    @if ($row['last_error_at'] !== null)
                                        <p class="mt-1 text-xs text-gray-500">{{ $row['last_error_at'] }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($status === 'rejected' && is_array($validation))
                                <div class="space-y-2" role="alert">
                                    <strong>{{ __('admin.preparation.validation_result') }}</strong>
                                    @foreach (($validation['errors'] ?? []) as $error)
                                        @php($validationCode = is_string($error['code'] ?? null) ? $error['code'] : 'VALIDATION_ERROR')
                                        <div class="rounded-lg border p-3 text-sm">
                                            <strong><code dir="ltr">{{ $validationCode }}</code></strong>
                                            <span>{{ $error['message'] ?? '' }}</span>
                                            <p class="mt-1 text-xs text-gray-500">{{ $remediationFor($validationCode) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if (is_array($dryRun))
                                <div class="rounded-xl border p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <strong>{{ __('admin.review.dry_run') }}</strong>
                                        <x-filament::badge :color="($dryRun['publishable'] ?? false) ? 'success' : 'danger'">
                                            {{ ($dryRun['publishable'] ?? false) ? __('admin.review.publishable') : __('admin.review.blocked') }}
                                        </x-filament::badge>
                                    </div>
                                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                        @foreach (($dryRun['counts'] ?? []) as $entity => $counts)
                                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                                <div class="font-medium">{{ __('admin.entities.'.$entity) }}</div>
                                                <div class="mt-1 text-xs text-gray-500">
                                                    {{ __('admin.review.create') }} {{ $counts['create'] ?? 0 }} ·
                                                    {{ __('admin.review.reuse') }} {{ $counts['reuse'] ?? 0 }} ·
                                                    {{ __('admin.review.conflict') }} {{ $counts['conflict'] ?? 0 }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if (($dryRun['blocking_codes'] ?? []) !== [])
                                        <ul class="mt-3 space-y-2" aria-label="{{ __('admin.review.blocking_guidance') }}">
                                            @foreach ($dryRun['blocking_codes'] as $code)
                                                <li class="rounded-lg border border-danger-300 p-3 text-sm">
                                                    <x-filament::badge color="danger"><span dir="ltr">{{ $code }}</span></x-filament::badge>
                                                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $remediationFor(is_string($code) ? $code : null) }}</p>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            @if (! $stale)
                                <div class="space-y-3">
                                    @if ($status === 'staged')
                                        <x-filament::button wire:click="runDryRun('{{ $row['id'] }}')" wire:loading.attr="disabled" wire:target="runDryRun('{{ $row['id'] }}')">
                                            {{ __('admin.actions.run_dry_run') }}
                                        </x-filament::button>
                                    @elseif ($status === 'validated')
                                        <label class="block space-y-2" for="review-reason-{{ $row['id'] }}">
                                            <span class="font-medium text-sm">{{ __('admin.review.reason') }}</span>
                                            <span class="block text-xs text-gray-500">{{ __('admin.review.reason_requirement') }}</span>
                                            <textarea
                                                id="review-reason-{{ $row['id'] }}"
                                                rows="3"
                                                maxlength="2000"
                                                aria-describedby="review-reason-help-{{ $row['id'] }}"
                                                wire:model.live.debounce.150ms="reasons.{{ $row['id'] }}"
                                                class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                                placeholder="{{ __('admin.review.reason_placeholder') }}"
                                            ></textarea>
                                            <span id="review-reason-help-{{ $row['id'] }}" class="block text-xs text-gray-500">
                                                {{ $reasonReady ? __('admin.review.reason_ready_help') : __('admin.review.reason_required_help') }}
                                            </span>
                                        </label>
                                        <div class="flex flex-wrap gap-3">
                                            <x-filament::button color="success" wire:click="approve('{{ $row['id'] }}')" wire:loading.attr="disabled">{{ __('admin.actions.approve') }}</x-filament::button>
                                            <x-filament::button
                                                color="warning"
                                                wire:click="requestFix('{{ $row['id'] }}')"
                                                wire:loading.attr="disabled"
                                                :disabled="! $reasonReady"
                                                :aria-disabled="$reasonReady ? 'false' : 'true'"
                                            >{{ __('admin.actions.request_fix') }}</x-filament::button>
                                            <x-filament::button
                                                color="danger"
                                                wire:click="reject('{{ $row['id'] }}')"
                                                wire:loading.attr="disabled"
                                                :disabled="! $reasonReady"
                                                :aria-disabled="$reasonReady ? 'false' : 'true'"
                                            >{{ __('admin.actions.reject') }}</x-filament::button>
                                        </div>
                                    @elseif ($status === 'reviewed' && $decision === 'approved')
                                        <div class="flex flex-wrap items-center gap-3">
                                            <x-filament::button wire:click="importReviewed('{{ $row['id'] }}')" wire:loading.attr="disabled">
                                                {{ __('admin.actions.import_reviewed') }}
                                            </x-filament::button>
                                            <span class="text-xs text-gray-500">{{ __('admin.review.import_help') }}</span>
                                        </div>
                                    @elseif ($status === 'reviewed' && in_array($decision, ['rejected', 'request_fix'], true))
                                        <div class="rounded-xl border p-4 text-sm">
                                            <strong>{{ __('admin.decisions.'.$decision) }}</strong>
                                            @if ($row['review_reason'] !== null)<p class="mt-1">{{ $row['review_reason'] }}</p>@endif
                                            <p class="mt-2 text-gray-500">{{ __('admin.review.return_new_zip') }}</p>
                                        </div>
                                    @elseif ($status === 'imported')
                                        <div class="space-y-3">
                                            <div class="rounded-xl border-2 border-warning-400 bg-warning-50/70 p-4 text-sm dark:bg-warning-400/10" role="note">
                                                <strong>{{ __('admin.review.imported_draft_title') }}</strong>
                                                <p class="mt-1">{{ __('admin.review.imported_draft_body') }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <x-filament::button color="success" wire:click="requestPublication('{{ $row['id'] }}')" wire:loading.attr="disabled">
                                                    {{ __('admin.actions.publish_official') }}
                                                </x-filament::button>
                                                <span class="text-xs text-gray-500">{{ __('admin.review.publish_help') }}</span>
                                            </div>
                                        </div>
                                    @elseif ($status === 'published')
                                        <div class="rounded-xl border p-4 text-sm" role="status">
                                            <strong>{{ __('admin.review.published_title') }}</strong>
                                            <p>{{ __('admin.review.published_body') }}</p>
                                            @if ($row['published_at'] !== null)<p class="mt-1 text-xs text-gray-500">{{ $row['published_at'] }}</p>@endif
                                        </div>
                                    @endif

                                    @if ($row['operation_state'] === 'failed' && in_array($status, ['reviewed', 'imported'], true))
                                        <x-filament::button color="warning" wire:click="retry('{{ $row['id'] }}')" wire:loading.attr="disabled">
                                            {{ __('admin.actions.retry') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            @endif

                            @if ($selectedImportId === $row['id'])
                                <div class="border-t pt-5">
                                    <h4 class="font-semibold">{{ __('admin.review.audit_history') }}</h4>
                                    @php($audits = $this->auditRows($row['id']))
                                    @if ($audits === [])
                                        <p class="mt-2 text-sm text-gray-500">{{ __('admin.review.no_audit') }}</p>
                                    @else
                                        <ol class="mt-4 space-y-3" aria-label="{{ __('admin.review.audit_history') }}">
                                            @foreach ($audits as $audit)
                                                <li class="rounded-xl border p-4 text-sm">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <strong>{{ $audit['action'] }}</strong>
                                                        <span class="text-xs text-gray-500">{{ $audit['created_at'] }}</span>
                                                    </div>
                                                    <div class="mt-1 text-xs text-gray-500">
                                                        {{ $audit['from_status'] ?? '—' }} → {{ $audit['to_status'] ?? '—' }}
                                                        @if ($audit['actor_id'] !== null) · {{ __('admin.review.actor') }} {{ $audit['actor_id'] }} @endif
                                                    </div>
                                                    @if ($audit['reason'] !== null)<p class="mt-2">{{ $audit['reason'] }}</p>@endif
                                                    @if (is_array($audit['metadata']) && $audit['metadata'] !== [])
                                                        <pre class="mt-2 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-2 text-xs dark:bg-white/5" dir="ltr">{{ json_encode($audit['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        <x-filament::modal
            id="confirm-content-publication"
            width="2xl"
            icon="heroicon-o-exclamation-triangle"
            icon-color="danger"
            :close-by-clicking-away="false"
            :close-by-escaping="false"
            :close-button="false"
        >
            <x-slot name="heading">
                {{ __('admin.confirmations.publication_title') }}
            </x-slot>
            <x-slot name="description">
                {{ __('admin.confirmations.publication_description') }}
            </x-slot>

            <div class="space-y-4 text-sm">
                <div class="rounded-xl border border-danger-300 p-4" role="alert">
                    <strong>{{ __('admin.confirmations.publication_consequence_title') }}</strong>
                    <p class="mt-1">{{ __('admin.confirmations.publication_consequence_body') }}</p>
                </div>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="font-medium">{{ __('admin.fields.import_id') }}</dt>
                        <dd class="break-all font-mono text-xs">{{ $pendingPublication['id'] ?? $pendingPublicationImportId ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('admin.fields.status') }}</dt>
                        <dd>{{ isset($pendingPublication['status']) ? __('admin.status.'.$pendingPublication['status']) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('admin.fields.request_id') }}</dt>
                        <dd class="break-all font-mono text-xs">{{ $pendingPublication['preparation_request_id'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('admin.fields.rights_status') }}</dt>
                        <dd>{{ $pendingPublication['rights_status'] ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium">{{ __('admin.review.dry_run') }}</dt>
                        <dd>
                            @if (is_array($pendingPublication['dry_run_summary'] ?? null))
                                {{ ($pendingPublication['dry_run_summary']['publishable'] ?? false) ? __('admin.review.publishable') : __('admin.review.blocked') }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <x-slot name="footerActions">
                <x-filament::button color="gray" wire:click="cancelPublication" wire:loading.attr="disabled" wire:target="confirmPublication,cancelPublication">
                    {{ __('admin.actions.cancel') }}
                </x-filament::button>
                <x-filament::button color="danger" wire:click="confirmPublication" wire:loading.attr="disabled" wire:target="confirmPublication">
                    {{ __('admin.actions.confirm_publication') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        <div wire:loading.flex class="items-center gap-2 text-sm" aria-live="polite">
            <x-filament::loading-indicator class="h-5 w-5" />
            <span>{{ __('admin.messages.working') }}</span>
        </div>
    </div>
</x-filament-panels::page>
