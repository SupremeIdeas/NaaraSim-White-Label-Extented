@php($t = \App\Support\PreloaderSettings::loadingText($cfg ?? []))
@php($chars = preg_split('//u', $t, -1, PREG_SPLIT_NO_EMPTY))
@foreach (array_slice($chars, 0, 12) as $ch)<span class="nx-letter">{{ $ch === ' ' ? "\u{00A0}" : $ch }}</span>@endforeach
