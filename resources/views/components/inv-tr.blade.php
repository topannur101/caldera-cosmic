@props(['href', 'name', 'desc', 'code', 'curr', 'price', 'uom', 'loc', 'tags', 'qty', 'qty_main', 'qty_used', 'qty_rep'])

<tr>
   <td>                
        @switch($qty)
        @case('main')
            {{ $qty_main }}
            @break
        @case('used')
            {{ $qty_used }}
            @break
        @case('rep')
            {{ $qty_rep }}
            @break
        @default
            {{ $qty_main + $qty_used + $qty_rep}}
        @endswitch
        {{ $uom }}
    </td>
   <td>
       <span>{{ $name }}</span><span class="mx-2">•</span><span>{{ $desc }}</span>
   </td> 
   <td>{{ $code }}</td>
   <td>{{ $price ? ( $curr  . ' ' . $price . ' / ' . $uom) : null }}</td>
   <td>{{ $loc ?? null}}</td>
   <td>{{ $tags ?? __('Tak ada tag')}}</td>
   <td><x-link href="{{ $href }}" class="text-neutral-800 dark:text-neutral-200"><i class="fa fa-external-link"></i></x-link>
   </td>
</tr>