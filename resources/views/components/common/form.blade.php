{{--
    Component Form dùng chung.
    Tự thêm @csrf và @method khi cần (PUT/PATCH/DELETE).

    Cách dùng:
        <x-common.form :action="route('login')" method="POST" id="loginForm">
            ... các trường ...
        </x-common.form>
--}}
@props([
    'action' => '#',
    'method' => 'POST',
])

@php
    $verb = strtoupper($method);
    $needsSpoof = in_array($verb, ['PUT', 'PATCH', 'DELETE']);
    $htmlMethod = $needsSpoof ? 'POST' : $verb;
@endphp

<form action="{{ $action }}" method="{{ $htmlMethod }}" {{ $attributes }}>
    @if ($htmlMethod !== 'GET')
        @csrf
    @endif
    @if ($needsSpoof)
        @method($verb)
    @endif

    {{ $slot }}
</form>
