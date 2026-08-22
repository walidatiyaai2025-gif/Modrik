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
            $url = is_string($step['url'] ?? null) && $step['url'] !== '' ? $step['url'] : null;
            $action = (string) ($step['action'] ?? '');
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
            @if ($url !== null)
                <a
                    href="{{ $url }}"
                    class="mt-3 inline-flex min-h-9 items-center rounded-lg text-xs font-semibold text-primary-700 underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    {{ $action !== '' ? $action : $title }}
                </a>
            @endif
        </div>
    @endforeach
</div>
