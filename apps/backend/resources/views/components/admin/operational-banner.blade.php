@props([
    'severity' => 'info',
    'title',
    'message' => null,
])

<div class="modrik-operational-banner" data-severity="{{ $severity }}" role="status">
    <div aria-hidden="true">
        @if ($severity === 'danger')
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-danger-600" />
        @elseif ($severity === 'warning')
            <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-5 w-5 text-warning-600" />
        @elseif ($severity === 'success')
            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-600" />
        @else
            <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-info-600" />
        @endif
    </div>
    <div class="min-w-0">
        <div class="font-semibold text-gray-950">{{ $title }}</div>
        @if ($message)
            <div class="mt-1 text-sm leading-6 text-gray-600">{{ $message }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
