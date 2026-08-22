@props([
    'items' => [],
    'emptyTitle' => 'No activity yet',
])

@if ($items === [])
    <x-admin.empty-state :title="$emptyTitle" />
@else
    <ol class="divide-y divide-gray-100" aria-label="Audit activity">
        @foreach ($items as $item)
            <li class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-950">{{ $item['action'] }}</span>
                        @if (($item['status'] ?? '') !== '')
                            <x-filament::badge color="gray">{{ $item['status'] }}</x-filament::badge>
                        @endif
                    </div>
                    @if (($item['reason'] ?? '') !== '')
                        <p class="mt-1 text-sm leading-6 text-gray-600">{{ $item['reason'] }}</p>
                    @endif
                    @if (($item['actor'] ?? '') !== '')
                        <p class="modrik-code mt-2 text-xs text-gray-400">actor: {{ $item['actor'] }}</p>
                    @endif
                </div>
                <time class="text-xs text-gray-500">{{ $item['created_at'] }}</time>
            </li>
        @endforeach
    </ol>
@endif
