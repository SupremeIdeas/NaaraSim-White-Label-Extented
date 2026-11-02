@include('errors.partials.shell', [
    'code' => '403', 'icon' => 'lock', 'badge' => 'Access restricted',
    'title' => 'This area is not open to your account',
    'message' => 'You’re signed in, but you don’t have permission to open this page. If you think that’s a mistake, contact support and we’ll sort it out.',
])
