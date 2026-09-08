<div>
    @if ($sent)
        <x-ui.alert variant="info" title="Message sent" icon="badge-check">
            {{ $success ?? "We got your message. Someone from our team will respond within 2 hours. Check your spam folder if you don't hear from us — we'll be there." }}
        </x-ui.alert>
    @elseif (! $this->canDeliver)
        <x-ui.alert variant="warning" title="Email us directly" icon="mail">
            Our contact form is being set up. Reach us directly at
            <a href="mailto:{{ config('naara.support.email') }}" class="font-semibold underline">{{ config('naara.support.email') }}</a>
            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                or on <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="font-semibold underline">WhatsApp</a>
            @endif
            — a human will reply fast.
        </x-ui.alert>
    @else
        <form wire:submit="send" class="space-y-4">
            {{-- Honeypot (hidden from humans). --}}
            <div class="hidden" aria-hidden="true"><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Full Name</label>
                    <input type="text" wire:model="name" placeholder="What should we call you?"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email Address</label>
                    <input type="email" wire:model="email" placeholder="Where should we reply?"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">What's this about?</label>
                <select wire:model="subject" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($subjects as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Your Message</label>
                <textarea wire:model="message" rows="5" placeholder="Tell us what's going on. The more detail you give us, the faster we can help."
                          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-slate-400">We don't share your information. Ever.</p>
                <x-ui.btn variant="primary" target="send" icon="send">Send Message</x-ui.btn>
            </div>
        </form>
    @endif
</div>
