@include('errors.layout', [
    'code'    => $exception->getStatusCode(),
    'title'   => __('Anfrage nicht möglich'),
    'message' => __('Diese Aktion lässt sich so nicht ausführen. Bitte zurückgehen und nochmals versuchen.'),
])
