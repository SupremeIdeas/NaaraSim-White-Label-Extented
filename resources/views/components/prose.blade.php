@props(['body' => ''])

{{-- Safe light-markup renderer (Module 30). Everything is escaped, so admin or
     post content can never inject HTML/scripts. Line-oriented so it is robust
     even when a "## Heading" is not separated from the next paragraph by a blank
     line. Supports: "## Heading", "- bullet", blank-line paragraphs, and a
     standalone image line `![alt](/images/...)` (local paths or https only). --}}
@php
    $lines = preg_split('/\r?\n/', (string) $body);
    $nodes = [];
    $para = [];
    $list = [];
    $flushPara = function () use (&$para, &$nodes) {
        if ($para !== []) { $nodes[] = ['t' => 'p', 'v' => implode(' ', $para)]; $para = []; }
    };
    $flushList = function () use (&$list, &$nodes) {
        if ($list !== []) { $nodes[] = ['t' => 'ul', 'v' => $list]; $list = []; }
    };
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '') { $flushList(); $flushPara(); continue; }
        if (preg_match('~^!\[([^\]]*)\]\(((?:/images/|https://)[A-Za-z0-9._\~:/?#@!$&\'()*+,;=%-]+)\)$~', $line, $m)) {
            $flushList(); $flushPara();
            $nodes[] = ['t' => 'img', 'v' => $m[2], 'alt' => $m[1]];
        } elseif (str_starts_with($line, '## ')) {
            $flushList(); $flushPara();
            $nodes[] = ['t' => 'h2', 'v' => trim(substr($line, 3))];
        } elseif (str_starts_with($line, '- ')) {
            $flushPara();
            $list[] = trim(substr($line, 2));
        } else {
            $flushList();
            $para[] = $line;
        }
    }
    $flushList();
    $flushPara();
@endphp
<div {{ $attributes->merge(['class' => 'space-y-4 leading-relaxed text-slate-600 dark:text-slate-300']) }}>
    @foreach ($nodes as $node)
        @if ($node['t'] === 'h2')
            <h2 class="pt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $node['v'] }}</h2>
        @elseif ($node['t'] === 'img')
            <figure class="py-2">
                <img src="{{ str_starts_with($node['v'], '/') ? asset(ltrim($node['v'], '/')) : $node['v'] }}" alt="{{ $node['alt'] }}" loading="lazy" decoding="async"
                     class="w-full rounded-2xl">
            </figure>
        @elseif ($node['t'] === 'ul')
            <ul class="list-disc space-y-1.5 pl-5">
                @foreach ($node['v'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            <p>{{ $node['v'] }}</p>
        @endif
    @endforeach
</div>
