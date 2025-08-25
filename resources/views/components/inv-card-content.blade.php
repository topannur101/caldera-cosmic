@props([
    'url',
    'name',
    'desc',
    'code',
    'curr',
    'price',
    'uom',
    'loc',
    'tags',
    'qty',
    'photo',
    'qty_min',
    'qty_max'
    ])

<div class="bg-white dark:bg-neutral-800 shadow overflow-hidden rounded-none sm:rounded-md">
    <div class="flex">
        <div>
            <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                <div class="m-auto">
                    <svg xmlns="http://www.w3.org/2000/svg"  class="block h-16 w-auto fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z" /></svg>
                </div>
                @if($photo)
                <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ '/storage/inv-items/' . $photo }}" />
                @endif
            </div>            
        </div>
        <div class="grow truncate p-2">
            <div class="flex w-full">
                <div class="grow truncate">
                    <div class="p-1 truncate font-semibold" title="{{ $name }}">
                        <x-link :href="$url" wire:navigate>{{ $name }}</x-link>
                    </div>
                    <div class="px-1 truncate text-sm" title="{{ $desc }}">
                        {{ $desc }}
                    </div>
                    <div class="px-1 pt-1 text-sm text-neutral-500">
                        <div class="uppercase">
                            {{ $code ? $code : __('Tak ada kode')}}
                        </div>
                        <div title="{{ $price ? ($curr  . ' ' . number_format($price, 0) . ' / ' . $uom) : (' • ' .__('Tak ada harga')) }}"">
                            {{ $price ? ($curr  . ' ' . number_format($price, 0) . ' / ' . $uom) : __('Tak ada harga') }}
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="p-1 font-semibold">{{ $qty }}</div>
                    <div class="px-1 text-sm text-nowrap text-neutral-500">{{ $uom }}</div>
                    <div class="px-1 pt-1 text-sm text-neutral-500">
                        <i class="icon-chevrons-down-up mr-1"></i>{{ $qty_min . '-' . $qty_max }}
                    </div>
                </div>
            </div>
            <div class="p-1 flex gap-x-2 items-center text-sm text-neutral-500">
                <div title="{{ $loc }}">
                    <i class="icon-map-pin mr-1"></i>{{ $loc ? $loc : __('Tak ada lokasi') }}
                </div>
                <div class="truncate" title="{{ $tags }}">
                    <i class="icon-tag mr-1"></i>{{ $tags ? $tags : __('Tak ada tag') }}
                </div>
            </div>
        </div>
    </div>
</div>

