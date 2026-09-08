{{-- Skeleton rows for a <table> (owner request — premium loading). Renders valid
     <tr>/<td> so it slots into a table body during search/pagination without CLS. --}}
@props(['rows' => 6, 'cols' => 4])
@for ($r = 0; $r < (int) $rows; $r++)
    <tr aria-hidden="true">
        @for ($c = 0; $c < (int) $cols; $c++)
            <td class="px-4 py-3"><x-ui.skeleton class="h-3 rounded {{ $c === 0 ? 'w-16' : 'w-3/4' }}" /></td>
        @endfor
    </tr>
@endfor
