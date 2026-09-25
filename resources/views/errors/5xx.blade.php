@include('errors.layout', [
    'code'    => $exception->getStatusCode(),
    'title'   => __('Serverfehler'),
    'message' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte später nochmals versuchen.'),
])
