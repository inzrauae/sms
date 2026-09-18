<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found — {{ config('portal.brand_name') }}</title>
<meta name="description" content="This page does not exist. Head back to the {{ config('portal.brand_name') }} home page or the API reference.">
<meta name="robots" content="noindex, follow">
<link rel="stylesheet" href="{{ asset('css/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
<header class="masthead">
  <div class="shell">
    <a class="wordmark" href="/"><span class="glyph">eS</span><span>{{ config('portal.brand_name') }}</span></a>
  </div>
</header>
<main class="notfound">
  <div>
    <h1>Nothing here</h1>
    <p>That page does not exist. The dashboard and the API reference are below.</p>
    <a class="btn" href="/dashboard">Go to the dashboard</a>
    <a class="btn btn-line" href="/">Back to the home page</a>
  </div>
</main>
</body>
</html>
