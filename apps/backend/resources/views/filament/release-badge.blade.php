@php
    $fullRelease = is_string($release ?? null) && $release !== '' ? $release : 'dev';
    $shortRelease = $fullRelease === 'dev' ? 'dev' : substr($fullRelease, 0, 12);
@endphp

<span
    data-testid="modrik-release-badge"
    title="MODRIK deployed release: {{ $fullRelease }}"
    style="display:inline-flex;align-items:center;white-space:nowrap;border:1px solid rgba(148,163,184,.45);border-radius:9999px;padding:.2rem .55rem;font-size:.72rem;font-weight:700;line-height:1.15;letter-spacing:.02em;color:rgb(71 85 105);background:rgba(248,250,252,.9);"
>
    Build {{ $shortRelease }}
</span>
