@include('errors.layout', [
    'code'    => 403,
    'title'   => 'Kein Zugriff',
    'message' => $exception->getMessage() ?: 'Du hast keine Berechtigung, diese Seite zu sehen.',
])
