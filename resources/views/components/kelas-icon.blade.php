@props(['kelas', 'ukuran' => 2.5])

@php
    $url = $kelas?->icon_url;
    $gaya = "width: {$ukuran}rem; height: {$ukuran}rem; border-radius: 9999px; flex-shrink: 0;";
@endphp

@if($url)
    <img
        src="{{ $url }}"
        alt="{{ $kelas->nama_kelas }}"
        style="{{ $gaya }} object-fit: cover;"
        {{ $attributes }}
    >
@else
    <span
        style="{{ $gaya }} display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: {{ $ukuran * 0.45 }}rem;"
        {{ $attributes->class('bg-primary-100 text-primary-700') }}
    >
        {{ mb_strtoupper(mb_substr($kelas?->nama_kelas ?? '?', 0, 1)) }}
    </span>
@endif