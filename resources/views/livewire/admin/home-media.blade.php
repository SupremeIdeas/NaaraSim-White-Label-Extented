<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Homepage media</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The video section and the scroll-revealed story, both shown after the flag carousel on the marketing homepage.</p>
    </div>

    @if ($saved)
        <div class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- ===================== Video section (§8) ===================== --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Video section</h2>

        <form wire:submit="saveVideoHeading" class="mb-6 space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Section heading</label>
                <input type="text" wire:model="vid_heading" placeholder="{{ \App\Support\HomeVideos::DEFAULT_HEADING }}"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('vid_heading') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Sub-heading</label>
                <input type="text" wire:model="vid_subheading" placeholder="{{ \App\Support\HomeVideos::DEFAULT_SUBHEADING }}"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save heading</button>
            </div>
        </form>

        {{-- Existing entries --}}
        <div class="mb-6 space-y-2">
            @forelse ($videos as $v)
                <div wire:key="hv-{{ $v->id }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-2.5 dark:border-[#243352]">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-[#243352]">
                        @if ($v->posterOrFallback())
                            <img src="{{ $v->posterOrFallback() }}" alt="" class="h-full w-full object-cover">
                        @else
                            <x-icon name="play" class="h-5 w-5 text-slate-400" />
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $v->title }}</p>
                        <p class="text-[11px] text-slate-400">{{ ucfirst($v->source_type) }} · {{ ucfirst($v->orientation) }}{{ $v->is_active ? '' : ' · hidden' }}</p>
                    </div>
                    <button type="button" wire:click="toggleVideo({{ $v->id }})"
                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        {{ $v->is_active ? 'Hide' : 'Show' }}
                    </button>
                    <button type="button" wire:click="deleteVideo({{ $v->id }})" wire:confirm="Remove this video?"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-400">No videos yet — add one below. The section is hidden on the homepage until at least one is active.</p>
            @endforelse
        </div>

        {{-- Add a video --}}
        <form wire:submit="addVideo" class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-[#182742]">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Add a video</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Title</label>
                    <input type="text" wire:model="v_title" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('v_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Orientation</label>
                    <select wire:model="v_orientation" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="portrait">Portrait (default on mobile)</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Source</label>
                <select wire:model.live="v_source" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="youtube">YouTube link</option>
                    <option value="upload">Upload a clip (MP4/WebM)</option>
                </select>
            </div>

            @if ($v_source === 'youtube')
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">YouTube link or video id</label>
                    <input type="text" wire:model="v_youtube" placeholder="https://youtu.be/…"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('v_youtube') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Video file (MP4/WebM, ≤10 MB)</label>
                    <input type="file" wire:model="v_upload" accept="video/mp4,video/webm"
                           class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                    @error('v_upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Poster image <span class="text-slate-400">(optional — YouTube thumbnail used if blank; standard 1280×720)</span></label>
                <input type="file" wire:model="v_poster" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}"
                       class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                @error('v_poster') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="addVideo,v_upload,v_poster"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="addVideo">Add video</span>
                    <span wire:loading wire:target="addVideo,v_upload,v_poster" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ===================== Story section (§9) ===================== --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <form wire:submit="saveStory" class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Story section</h2>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <x-ui.switch wire:model="story_enabled" label="Show story section" />
                    <span>Show on homepage</span>
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Eyebrow</label>
                    <input type="text" wire:model="story_eyebrow" placeholder="{{ \App\Support\HomeStory::DEFAULTS['eyebrow'] }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Transition line (leads into the next section)</label>
                    <input type="text" wire:model="story_transition" placeholder="{{ \App\Support\HomeStory::DEFAULTS['transition'] }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Heading</label>
                <input type="text" wire:model="story_heading" placeholder="{{ \App\Support\HomeStory::DEFAULTS['heading'] }}"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('story_heading') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Body <span class="text-slate-400">(blank line between paragraphs)</span></label>
                <textarea wire:model="story_body" rows="5" placeholder="Tell the Naara story…"
                          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('story_body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save story</button>
            </div>
        </form>
    </div>
</div>
