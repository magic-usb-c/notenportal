@include('errors.layout', [
    'code'    => 403,
    'title'   => 'Kein Zugriff',
    // Eigene Meldungen sind deutsch; Laravels Standardtext («This action is unauthorized.») nie anzeigen
    'message' => in_array($exception->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true)
        ? 'Du hast keine Berechtigung, diese Seite zu sehen.'
        : $exception->getMessage(),
])
