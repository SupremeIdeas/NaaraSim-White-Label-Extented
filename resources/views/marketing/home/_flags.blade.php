{{-- Premium "No Borders" flag carousels (decorative, non-CMS). Two rows scroll
     in opposite directions; flags are the self-hosted flag-icons SVG set. --}}
@php
    $rowA = ['ng','gh','ke','za','us','gb','ca','fr','de','es','ae','sa','in','jp','br','au'];
    $rowB = ['it','pt','nl','tr','eg','ma','cn','kr','sg','th','ph','id','mx','ar','ch','se'];
    $labels = ['ng'=>'Nigeria','gh'=>'Ghana','ke'=>'Kenya','za'=>'South Africa','us'=>'USA','gb'=>'UK','ca'=>'Canada','fr'=>'France','de'=>'Germany','es'=>'Spain','ae'=>'UAE','sa'=>'Saudi Arabia','in'=>'India','jp'=>'Japan','br'=>'Brazil','au'=>'Australia','it'=>'Italy','pt'=>'Portugal','nl'=>'Netherlands','tr'=>'Türkiye','eg'=>'Egypt','ma'=>'Morocco','cn'=>'China','kr'=>'South Korea','sg'=>'Singapore','th'=>'Thailand','ph'=>'Philippines','id'=>'Indonesia','mx'=>'Mexico','ar'=>'Argentina','ch'=>'Switzerland','se'=>'Sweden'];
@endphp
<section data-reveal class="overflow-hidden py-14">
    <p class="mb-8 text-center text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">190+ countries · one eSIM · no borders</p>

    @foreach (['A' => ['dir' => 'ltr', 'flags' => $rowA], 'B' => ['dir' => 'rtl', 'flags' => $rowB]] as $rowKey => $row)
        <div class="nx-marquee {{ $rowKey === 'B' ? 'mt-4' : '' }}">
            <div class="nx-marquee__track nx-marquee__track--{{ $row['dir'] }}">
                @foreach (array_merge($row['flags'], $row['flags']) as $code)
                    <span class="flex shrink-0 items-center gap-2.5 rounded-full border border-slate-200/70 bg-white/70 px-4 py-2.5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                        <span class="fi fi-{{ $code }} h-5 w-7 rounded-[3px] bg-cover shadow-sm ring-1 ring-black/10" role="img" aria-label="{{ $labels[$code] ?? $code }}"></span>
                        <span class="whitespace-nowrap text-sm font-medium text-slate-600 dark:text-slate-300">{{ $labels[$code] ?? strtoupper($code) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endforeach
</section>
