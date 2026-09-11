@include('errors.layout', [
    'code'    => 404,
    'title'   => __('Seite nicht gefunden'),
    'message' => __('Die angeforderte Seite existiert nicht.'),
])
