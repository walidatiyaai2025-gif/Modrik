<x-filament-panels::page>
    <div class="space-y-6" data-testid="modrik-system-updates">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Current release</h2>
            <dl class="mt-3 grid gap-3 md:grid-cols-2"><div><dt class="text-sm text-gray-500">Version</dt><dd class="font-mono">{{ $currentVersion }}</dd></div><div><dt class="text-sm text-gray-500">Release SHA</dt><dd class="break-all font-mono">{{ $releaseSha }}</dd></div></dl>
        </section>

        @if($pendingActivation)
            <section class="rounded-2xl border border-warning-300 bg-warning-50 p-5 shadow-sm" data-testid="pending-host-activation">
                <h2 class="text-lg font-semibold text-gray-950">{{ app()->getLocale() === 'ar' ? 'التحديث ينتظر إعادة تشغيل تطبيق Node فقط' : 'Update is waiting only for the Node app restart' }}</h2>
                <p class="mt-2 text-sm text-gray-700">
                    {{ app()->getLocale() === 'ar'
                        ? 'تم تفعيل ملفات Backend وWeb مع الاحتفاظ بنسخة rollback. من cPanel افتح Setup Node.js App، اختر demo.modrik.org واضغط Restart مرة واحدة، ثم ارجع هنا واضغط تحقق وأكمل.'
                        : 'Backend and Web payloads are already live with rollback protection. In cPanel open Setup Node.js App, restart demo.modrik.org once, then return here and verify completion.' }}
                </p>
                <p class="mt-2 break-all font-mono text-xs text-gray-600">{{ $pendingActivation['release_sha'] }}</p>
                <x-filament::button class="mt-4" wire:click="verifyPendingUpdate" wire:loading.attr="disabled" data-testid="verify-pending-update">
                    {{ app()->getLocale() === 'ar' ? 'تحقق وأكمل التحديث' : 'Verify & Complete' }}
                </x-filament::button>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Validate and install an update package</h2>
            <p class="mt-1 text-sm text-gray-600">The package is uploaded directly to Laravel private storage, then validated before any staging or release mutation. Update packages may be up to {{ $maxPackageMb }} MB.</p>
            <form method="POST" action="{{ route('system-updates.upload-package') }}" enctype="multipart/form-data" class="mt-4 space-y-3" data-testid="system-update-upload-form">
                @csrf
                <input type="file" name="package" accept=".zip,application/zip" required class="block w-full rounded-lg border border-gray-300 p-2" data-testid="system-update-package-input" @disabled($pendingActivation) />
                @error('package') <p class="text-sm text-danger-600" data-testid="update-upload-error">{{ $message }}</p> @enderror
                <x-filament::button type="submit" :disabled="(bool) $pendingActivation">Validate package</x-filament::button>
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
                    @if(($installationResult['status'] ?? '') === 'REQUIRES_HOST_ACTION')
                        <p class="mt-1 text-sm">{{ app()->getLocale() === 'ar' ? 'تم تفعيل الكود ولم يتم عمل rollback. أعد تشغيل تطبيق demo.modrik.org من cPanel ثم اضغط تحقق وأكمل.' : 'Code remains activated and was not rolled back. Restart demo.modrik.org in cPanel, then use Verify & Complete.' }}</p>
                    @endif
                    @if(($installationResult['status'] ?? '') === 'SUCCESS')
                        <p class="mt-1 text-sm">{{ app()->getLocale() === 'ar' ? 'تم التحقق من النسخة الجديدة وإكمال التحديث بنجاح.' : 'The new release was verified and the update completed successfully.' }}</p>
                    @endif
                </div>
            @endif
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">Update history</h2>
            <div class="mt-3 space-y-2">@forelse($history as $entry)<div class="flex flex-wrap justify-between gap-2 border-t border-gray-100 pt-2 text-sm"><span>{{ $entry->created_at }} · {{ $entry->status }}</span><span class="font-mono">{{ $entry->to_version }} {{ $entry->release_sha }}</span></div>@empty<p class="text-sm text-gray-500">No update attempts recorded.</p>@endforelse</div>
        </section>
        <x-admin.operational-banner severity="warning" title="cPanel restart safety" message="A release is never marked successful until the live Backend, Web release identity and Student runtime are verified. If the restart marker cannot converge automatically, the Update Center retains a pending rollback-protected activation for one cPanel GUI restart and explicit verification." />
    </div>
</x-filament-panels::page>
