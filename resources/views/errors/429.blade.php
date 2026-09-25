@include('errors.layout', [
    'code'    => 429,
    'title'   => __('Zu viele Anfragen'),
    'message' => __('Bitte einen Moment warten und dann nochmals versuchen.'),
])
