@props(['name', 'class' => null])
@php
    static $cache = [];

    if (!isset($cache[$name])) {
        $path = resource_path("svg/icons/{$name}.svg");
        $cache[$name] = is_file($path) ? file_get_contents($path) : null;
    }

    $svg = $cache[$name];
@endphp
@if ($svg)
    {!! $class ? preg_replace('/<svg /', '<svg class="' . e($class) . '" ', $svg, 1) : $svg !!}
@endif
