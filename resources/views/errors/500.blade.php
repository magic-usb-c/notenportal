@include('errors.layout', [
    'code'    => 500,
    'title'   => __('Serverfehler'),
    'message' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte später nochmals versuchen.'),
])
