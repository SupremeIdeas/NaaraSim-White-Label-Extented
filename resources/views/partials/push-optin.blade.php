@if (\App\Support\FeatureFlags::enabled('naara_push'))
    {{-- Self-hosted web-push opt-in (owner request). Registers the service
         worker, and offers a subtle prompt to enable closed-tab notifications.
         Nothing renders/prompts unless VAPID keys are configured. --}}
    <div x-data="naaraPush(@js(\App\Support\WebPushConfig::publicKey()))" x-init="init()" x-cloak>
        <div x-show="showPrompt" x-transition
             class="fixed bottom-24 left-1/2 z-40 w-[92vw] max-w-sm -translate-x-1/2 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:bottom-6 lg:left-auto lg:right-6 lg:translate-x-0 dark:border-[var(--brand-card-border-dark)] dark:bg-[#1B2A44]">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon name="bell" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Turn on notifications</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Get alerts about your orders, wallet and offers — even when this tab is closed.</p>
                    <div class="mt-3 flex gap-2">
                        <button @click="enable()" :disabled="busy"
                                class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                            <span x-show="!busy">Enable</span><span x-show="busy">Enabling…</span>
                        </button>
                        <button @click="dismiss()" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5">Not now</button>
                    </div>
                </div>
                <button @click="dismiss()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><x-icon name="x" class="h-4 w-4" /></button>
            </div>
        </div>
    </div>

    <script>
        function naaraPush(vapidPublicKey) {
            return {
                showPrompt: false,
                busy: false,
                reg: null,

                async init() {
                    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
                    try {
                        this.reg = await navigator.serviceWorker.register('/sw.js');
                    } catch (e) { return; }

                    // Already subscribed? keep the server copy fresh, no prompt.
                    const existing = await this.reg.pushManager.getSubscription();
                    if (existing) { this.sync(existing); return; }

                    // Only nudge if the user hasn't decided and hasn't snoozed us.
                    if (Notification.permission === 'default' && localStorage.getItem('nx_push_snooze') !== '1') {
                        this.showPrompt = true;
                    }
                },

                async enable() {
                    this.busy = true;
                    try {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') { this.dismiss(); return; }
                        const sub = await this.reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlB64ToUint8Array(vapidPublicKey),
                        });
                        await this.sync(sub);
                        this.showPrompt = false;
                    } catch (e) {
                        // best-effort; leave the prompt for next time
                    } finally {
                        this.busy = false;
                    }
                },

                async sync(sub) {
                    const json = sub.toJSON();
                    await fetch('{{ route('push.subscribe') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            endpoint: json.endpoint,
                            keys: json.keys,
                            contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                        }),
                    });
                },

                dismiss() {
                    this.showPrompt = false;
                    localStorage.setItem('nx_push_snooze', '1');
                },

                urlB64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const raw = atob(base64);
                    const out = new Uint8Array(raw.length);
                    for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
                    return out;
                },
            };
        }
    </script>
@endif
