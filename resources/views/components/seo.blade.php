@props([
    'title',
    'description',
    'canonical' => null,
    'robots' => 'index, follow',
    'type' => 'website',
    'image' => null,
    'og' => true,
    'keywords' => null,
    'region' => null,
])
@php
    $canonical = $canonical ?? url()->current();
    $ogImage = $image ?? asset('images/og-cover.png');
    $places = ['LK' => 'Sri Lanka'];
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
@if ($keywords)
<meta name="keywords" content="{{ $keywords }}">
@endif
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">
@if ($region && isset($places[$region]))
<meta name="geo.region" content="{{ $region }}">
<meta name="geo.placename" content="{{ $places[$region] }}">
@endif
@if ($og)
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ config('portal.brand_name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">
@endif
