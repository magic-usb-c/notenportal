@php
    // Laravels englische Standardtexte («This action is unauthorized.», «Forbidden») nie anzeigen
    $meldung = $exception->getMessage();
    $text = match (true) {
        $meldung === 'Invalid signature.' => __('Dieser Link ist nicht mehr gültig.'),
        in_array($meldung, ['', 'This action is unauthorized.', 'Forbidden'], true) => __('Du hast keine Berechtigung, diese Seite zu sehen.'),
        default => $meldung,
    };
@endphp
@include('errors.layout', [
    'code'    => 403,
    'title'   => __('Kein Zugriff'),
    'message' => $text,
])
