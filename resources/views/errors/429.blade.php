@include('errors.partials.shell', [
    'code' => '429', 'icon' => 'gauge', 'badge' => 'Slow down a moment',
    'title' => 'Too many requests',
    'message' => 'You’ve tried that a few times in a row. Take a short breather and we’ll let you back in automatically.',
    'back' => false,
    'retry' => (int) ($exception?->getHeaders()['Retry-After'] ?? 30),
])
