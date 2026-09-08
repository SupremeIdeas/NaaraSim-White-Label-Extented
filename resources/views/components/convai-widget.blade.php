@props(['context' => 'customer'])

{{-- ElevenLabs Convai voice-agent widget (task #17). Renders nothing unless an
     admin has enabled it AND set a public agent id for this context. The embed
     script loads from ElevenLabs only when active — the SecurityHeaders
     middleware widens the CSP for ElevenLabs on the same condition, so the strict
     default policy is untouched when the widget is off. When it is off, the
     existing WhatsApp / live-chat launcher is the support fallback. --}}
@if (\App\Support\ConvaiWidget::shownOn($context))
    <elevenlabs-convai agent-id="{{ \App\Support\ConvaiWidget::agentId() }}"></elevenlabs-convai>
    <script src="https://unpkg.com/@elevenlabs/convai-widget-embed" async type="text/javascript"></script>
@endif
