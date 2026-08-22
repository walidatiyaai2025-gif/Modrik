@props([
    'steps' => [],
    'label' => 'Workflow progress',
])

<div class="modrik-step-rail" role="list" aria-label="{{ $label }}">
    @foreach ($steps as $index => $step)
        @php
            $state = (string) ($step['state'] ?? 'pending');
            $title = (string) ($step['label'] ?? '');
            $description = (string) ($step['description'] ?? '');
        @endphp
        <div class="modrik-step" data-state="{{ $state }}" role="listitem">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">
                    {{ $index + 1 }}
                </span>
                <span class="text-sm font-semibold text-gray-950">{{ $title }}</span>
            </div>
            @if ($description !== '')
                <div class="mt-2 text-xs leading-5 text-gray-500">{{ $description }}</div>
            @endif
        </div>
    @endforeach
</div>
