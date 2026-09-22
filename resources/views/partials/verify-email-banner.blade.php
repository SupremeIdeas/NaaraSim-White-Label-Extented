{{-- Soft-gate email-verification nudge (NAARA-BUILD-20 §2). Shown only in 'soft'
     mode to an unverified user once mail is configured — it NEVER blocks any
     purchase or feature; it just invites confirmation for receipts + security.
     Dismissible per session (Alpine + sessionStorage). --}}
@if (\App\Support\MailSettings::shouldNudge(auth()->user()))
    {{-- relative z-10: keeps this above any full-bleed page cover below it
         (owner request, 2026-09-22) — a page's hero photo can legitimately
         paint behind in-flow content via a negative z-index, but this nudge
         must never be the thing it hides behind, and it needs a properly
         opaque (not just tinted) backdrop to stay readable when it lands on
         top of one. The seam fades to transparent instead of ending in a
         flat border line (no flat dividers — CLAUDE.md). --}}
    <div x-data="{ show: (() => { try { return sessionStorage.getItem('nx-verify-dismissed') !== '1'; } catch (e) { return true; } })() }"
         x-show="show" x-cloak
         class="relative z-10 bg-white/95 px-4 pb-4 pt-2.5 text-sm backdrop-blur-sm dark:bg-[#0F1D33]/95">
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
        <div class="pointer-events-none absolute inset-x-0 -bottom-3 h-3 bg-gradient-to-b from-white/95 to-transparent dark:from-[#0F1D33]/95"></div>
    </div>
@endif
