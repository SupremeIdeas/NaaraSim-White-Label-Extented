@include('errors.partials.shell', [
    'code' => '500', 'icon' => 'alert', 'badge' => 'Something went wrong',
    'title' => 'That’s on us',
    'message' => 'Something didn’t work on our end and it has been recorded. Please try again in a moment. If it keeps happening, contact support and quote the reference below.',
    'back' => false,
    'ref' => request()->attributes->get('error_ref'),
])
