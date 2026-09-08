{{--
    Password input with a show/hide toggle (task #17). Progressive enhancement:
    the base type is "password", so no-JS visitors keep a masked field; Alpine
    only flips it to "text" while the eye is toggled. SVG icons only (eye /
    eye-off), accessible (aria-label + aria-pressed), dark-mode aware. Pass the
    same attributes you'd put on a normal <input> (name, wire:model, id, required,
    autocomplete, placeholder, class) — they pass straight through; we only add
    right padding so the copy never sits under the toggle.
--}}
<div x-data="{ show: false }" class="relative">
    <input type="password" x-bind:type="show ? 'text' : 'password'"
           {{ $attributes->merge(['class' => 'pr-10']) }}>
    <button type="button" @click="show = ! show" tabindex="-1"
            :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show.toString()"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus-visible:text-primary dark:hover:text-slate-200">
        <span x-show="! show"><x-icon name="eye" class="h-5 w-5" /></span>
        <span x-show="show" x-cloak><x-icon name="eye-off" class="h-5 w-5" /></span>
    </button>
</div>
