@php
    $builtCssFile = public_path('css/app.css');
    $cssV = is_file($builtCssFile) ? '?v=' . filemtime($builtCssFile) : '';

    $compiledHref = is_file($builtCssFile) ? asset('css/app.css') . $cssV : asset('css/app.css');

    try {
        $mixHrefRaw = trim((string) mix('css/app.css'));
    } catch (\Throwable $e) {
        $mixHrefRaw = '';
    }

    $hotPointsAtLocalDev =
        $mixHrefRaw !== ''
        && is_file(public_path('hot'))
        && preg_match('#localhost|127\.0\.0\.1#', $mixHrefRaw) === 1;
@endphp
@if ($hotPointsAtLocalDev)
    <link rel="stylesheet" href="{{ $compiledHref }}">
    <link rel="stylesheet" href="{{ $mixHrefRaw }}">
@else
    @php
        $href = $mixHrefRaw !== '' ? $mixHrefRaw : $compiledHref;

        if ($cssV !== '' && ! str_contains($href, '?')) {
            $href .= $cssV;
        }
    @endphp
    <link rel="stylesheet" href="{{ $href }}">
@endif
