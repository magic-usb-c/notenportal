@props(['id', 'lernender' => null, 'mitCode' => false])
@php
    $k = \App\Services\Auswertung\Konfiguration::ausDb();
    $semesterId = $id !== null ? (int) $id : null;
    $sem = $semesterId !== null ? ($k->semester[$semesterId] ?? null) : null;
    $lernenderId = $lernender instanceof \App\Models\Lernender ? (int) $lernender->lernender_id : $lernender;
    $nummer = $sem && $lernenderId ? \App\Support\Lehrsemester::nummer($lernenderId, $semesterId) : null;
    $name = $sem ? ($nummer !== null ? \App\Support\Lehrsemester::name($nummer) : \App\Models\Semester::neutralerName($sem['start'])) : '–';
    $tooltip = $sem
        ? ($nummer !== null ? \App\Models\Semester::neutralerName($sem['start']).' · '.$sem['bezeichnung'] : $sem['bezeichnung'])
        : null;
@endphp
<span {{ $attributes->merge(['class' => '']) }} @if($tooltip) title="{{ $tooltip }}" @endif>{{ $name }}@if($mitCode && $sem) <span class="text-muted">({{ $sem['bezeichnung'] }})</span>@endif</span>
