<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Email settings</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Configure how {{ config('app.name') }} sends account, order and security emails. Until you set a real
        mailer, mail is only written to the log and customers receive nothing. Settings are encrypted and apply
        immediately — no file editing.
    </p>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif
    @if ($testResult)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $testResult }}</span>
        </div>
    @endif
    @if ($testError)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $testError }}</span>
        </div>
    @endif

    {{-- Where-to-get-credentials guide (the "tooltip" the operator needs). --}}
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
        <p class="mb-1 flex items-center gap-2 font-semibold"><x-icon name="info" class="h-4 w-4" /> Where do I get these?</p>
        <ul class="ml-6 list-disc space-y-1 text-[13px] leading-relaxed">
            <li><b>cPanel email:</b> create an email account in cPanel → Email Accounts, then use its host (mail.yourdomain.com), port 465 (SSL) or 587 (STARTTLS), the full email as username, and its password.</li>
            <li><b>Mailgun / Postmark / Resend / Brevo / SendGrid:</b> sign up, open their SMTP/credentials page, and copy the SMTP host, port, username and password they give you.</li>
            <li><b>Gmail:</b> host <code>smtp.gmail.com</code>, port 587, your Gmail address, and a Google <b>App Password</b> (not your login password).</li>
        </ul>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Mailer</label>
                    <select wire:model.live="mailer"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="log">Log (development — sends nothing)</option>
                        <option value="smtp">SMTP (recommended — works with any provider)</option>
                        <option value="sendmail">Sendmail (local mail server on a VPS)</option>
                    </select>
                    @error('mailer') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($mailer === 'smtp')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">SMTP host</label>
                            <input type="text" wire:model="smtp_host" placeholder="mail.yourdomain.com" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('smtp_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Port</label>
                            <input type="text" wire:model="smtp_port" placeholder="587" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('smtp_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Username</label>
                            <input type="text" wire:model="smtp_username" placeholder="you@yourdomain.com" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('smtp_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                                <span>Password</span>
                                @if ($passwordPreview)<span class="font-mono text-[11px] text-slate-400">saved: {{ $passwordPreview }}</span>@endif
                            </label>
                            <input type="password" wire:model="smtp_password" autocomplete="off"
                                   placeholder="{{ $passwordPreview ? 'Leave blank to keep current' : 'SMTP password' }}"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('smtp_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Encryption</label>
                            <select wire:model="smtp_scheme"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                <option value="">STARTTLS (port 587)</option>
                                <option value="smtps">SSL/TLS (port 465)</option>
                            </select>
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">From address</label>
                        <input type="email" wire:model="from_address" placeholder="hello@yourdomain.com"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('from_address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">From name</label>
                        <input type="text" wire:model="from_name" placeholder="{{ config('app.name') }}"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Email verification enforcement (NAARA-BUILD-20 §2). --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Email verification</h2>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">How strongly to enforce confirming an email address. Only applies once a real mailer is configured above.</p>
            <div class="space-y-2">
                @foreach ([
                    'soft' => ['Soft — recommended', 'Never blocks anyone. Unverified users buy and use everything; a dismissible banner nudges them to confirm.'],
                    'hard' => ['Hard', 'Unverified users are held at the verification screen until they confirm — nothing else opens.'],
                    'off' => ['Off', 'Verification is never required or nudged, even with mail configured.'],
                ] as $val => [$label, $desc])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 {{ $verificationMode === $val ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 dark:border-[#2D4060]' }}">
                        <input type="radio" wire:model.live="verificationMode" value="{{ $val }}" class="mt-1 text-primary focus:ring-primary">
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $desc }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('verificationMode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="send" class="h-4 w-4" />
                <span wire:loading.remove wire:target="sendTest">Send test email</span>
                <span wire:loading wire:target="sendTest">Sending…</span>
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save settings
            </button>
        </div>
    </form>
</div>
