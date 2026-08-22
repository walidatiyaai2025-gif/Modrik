@props([
    'title',
    'message' => null,
])

<div class="modrik-empty-state">
    <x-filament::icon icon="heroicon-o-inbox" class="mx-auto h-8 w-8 text-gray-400" aria-hidden="true" />
    <div class="mt-3 font-semibold text-gray-950">{{ $title }}</div>
    @if ($message)
        <div class="mx-auto mt-1 max-w-xl text-sm leading-6">{{ $message }}</div>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
