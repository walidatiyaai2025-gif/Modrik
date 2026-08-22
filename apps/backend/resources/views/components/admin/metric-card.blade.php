@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'neutral',
])

<article class="modrik-metric-card" data-tone="{{ $tone }}">
    <div class="modrik-metric-label">{{ $label }}</div>
    <div class="modrik-metric-value">{{ $value }}</div>
    @if ($meta)
        <div class="modrik-metric-meta">{{ $meta }}</div>
    @endif
</article>
