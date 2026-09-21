{{-- Soft-gate email-verification nudge (NAARA-BUILD-20 §2). Shown only in 'soft'
     mode to an unverified user once mail is configured — it NEVER blocks any
     purchase or feature; it just invites confirmation for receipts + security.
     Dismissible per session (Alpine + sessionStorage). --}}
@if (\App\Support\MailSettings::shouldNudge(auth()->user()))
    <div x-data="{ show: (() => { try { return sessionStorage.getItem('nx-verify-dismissed') !== '1'; } catch (e) { return true; } })() }"
         x-show="show" x-cloak
         class="border-b border-accent/30 bg-accent/10 px-4 py-2.5 text-sm dark:border-accent/25 dark:bg-accent/10">
        <div class="mx-auto flex max-w-6xl items-center gap-3">
            <x-icon name="mail" class="h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
            <p class="flex-1 text-slate-700 dark:text-slate-200">
                Confirm your email to secure your account and receive receipts.
                <form method="POST" action="{{ route('verification.send') }}" class="inline">
                    @csrf
                    <button type="submit" class="font-semibold text-primary underline hover:text-primary-dark">Resend the link</button>
                </form>
            </p>
            <button type="button" @click="show = false; try { sessionStorage.setItem('nx-verify-dismissed', '1'); } catch (e) {}; window.dispatchEvent(new Event('nx-layout-changed'))"
                    aria-label="Dismiss" class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-black/5 dark:hover:bg-white/10">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>
@endif
