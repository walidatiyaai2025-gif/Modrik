<x-filament-panels::page>
    <div class="space-y-6" data-testid="modrik-system-updates">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Current release</h2>
            <dl class="mt-3 grid gap-3 md:grid-cols-2"><div><dt class="text-sm text-gray-500">Version</dt><dd class="font-mono">{{ $currentVersion }}</dd></div><div><dt class="text-sm text-gray-500">Release SHA</dt><dd class="break-all font-mono">{{ $releaseSha }}</dd></div></dl>
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Validate and install an update package</h2>
            <p class="mt-1 text-sm text-gray-600">The package is uploaded directly to Laravel private storage, then validated before any staging or release mutation. Update packages may be up to {{ $maxPackageMb }} MB.</p>
            <form method="POST" action="{{ route('system-updates.upload-package') }}" enctype="multipart/form-data" class="mt-4 space-y-3" data-testid="system-update-upload-form">
                @csrf
                <input type="file" name="package" accept=".zip,application/zip" required class="block w-full rounded-lg border border-gray-300 p-2" data-testid="system-update-package-input" />
                @error('package') <p class="text-sm text-danger-600" data-testid="update-upload-error">{{ $message }}</p> @enderror
                <x-filament::button type="submit">Validate package</x-filament::button>
            </form>
            @if($validationResult)
                <div class="mt-4 rounded-xl p-4 {{ $validationResult['valid'] ? 'bg-success-50' : 'bg-danger-50' }}">
                    <strong>{{ $validationResult['valid'] ? 'Package is valid' : 'Package rejected' }}</strong>
                    @if($validationResult['valid'])
                        <p class="mt-1 text-sm">Target: {{ $validationResult['manifest']['version'] }} · Compatible with this installation</p>
                        <x-filament::button class="mt-3" color="danger" wire:click="installUpdate" wire:confirm="Install this validated update now? Maintenance mode and rollback safeguards will be used." wire:loading.attr="disabled">Install Update</x-filament::button>
                    @endif
                    @foreach($validationResult['errors'] as $error)<p class="mt-1 text-sm">{{ $error['code'] }} — {{ $error['message'] }}</p>@endforeach
                </div>
            @endif
            @if($installationResult)
                <div class="mt-4 rounded-xl border border-gray-200 p-4" data-testid="update-installation-result">
                    <strong>Final state: {{ $installationResult['status'] }}</strong>
                    @if(($installationResult['status'] ?? '') === 'REQUIRES_HOST_ACTION')<p class="mt-1 text-sm">The candidate was not declared successful. An authorized host operator must complete and verify the runtime restart.</p>@endif
                </div>
            @endif
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Update history</h2>
            <div class="mt-3 space-y-2">@forelse($history as $entry)<div class="flex flex-wrap justify-between gap-2 border-t border-gray-100 pt-2 text-sm"><span>{{ $entry->created_at }} · {{ $entry->status }}</span><span class="font-mono">{{ $entry->to_version }} {{ $entry->release_sha }}</span></div>@empty<p class="text-sm text-gray-500">No update attempts recorded.</p>@endforelse</div>
        </section>
        <x-admin.operational-banner severity="warning" title="Activation requires governed host confirmation" message="This foundation never reports success when the Node restart or health state is unknown. The production adapter remains REQUIRES_HOST_ACTION until an authorized hosting bridge is configured." />
    </div>
</x-filament-panels::page>
