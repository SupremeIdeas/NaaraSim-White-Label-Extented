@include('errors.partials.shell', [
    'code' => '503', 'icon' => 'wrench', 'badge' => 'Back shortly',
    'title' => 'We’re doing a quick tune-up',
    'message' => config('app.name').' is briefly down for maintenance. This page refreshes by itself, and nothing is wrong with your account.',
    'back' => false,
    'refresh' => 30,
])
