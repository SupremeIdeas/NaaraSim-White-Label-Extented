{{-- LEGACY staff reply box, kept behind config('composer.surfaces.staff_reply') = false as an instant rollback. Remove once Chat Composer Pro has run clean in production. --}}
                <form wire:submit="sendReply" class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 dark:border-[#2D4060]">
                    <input type="text" wire:model="reply" placeholder="Type your reply…"
                           class="flex-1 rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendReply"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark disabled:opacity-60">
                        <x-icon name="send" class="h-5 w-5" />
                    </button>
                </form>
