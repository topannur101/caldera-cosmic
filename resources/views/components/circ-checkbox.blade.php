@props(['disabled' => false, 'id', 'model', 'name', 'desc', 'code', 'uom', 'loc', 'photo', 'dir_icon', 'qty', 'qtype', 'curr', 'amount', 'user_name', 'remarks', 'status', 'user_photo', 'assigner', 'date_human'])

<input {{ $disabled ? 'disabled' : '' }} id="{{ 'circ-'.$id }}" value="{{ $id }}" type="checkbox" x-model={{ $model }} class="hidden">
<label for="{{ 'circ-'.$id }}" {{ $attributes->merge(['class' => 'custom-checkbox cursor-pointer h-full flex items-center text-sm text-left text-neutral-600 dark:text-neutral-400 bg-white dark:bg-neutral-800 shadow rounded-lg overflow-hidden transition ease-in-out duration-150']) }}>
    <div class="h-full">
        <div class="w-28 h-full relative truncate text-base text-neutral-900 dark:text-neutral-100">
            @if($photo)
            <img class="absolute opacity-80 dark:opacity-50 w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="/storage/inv-items/{{ $photo }}" />
            @else
            <div class="absolute opacity-80 dark:opacity-50 flex w-full h-full bg-neutral-200 dark:bg-neutral-700">
                <div class="m-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="block h-8 w-auto fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793"><path d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z"></path></svg>
                </div>
            </div>
            @endif
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white dark:bg-neutral-800 px-2 py-1 rounded">
                <i class="fa fa-fw {{ $dir_icon }} mr-2"></i>{{($qty < 0 ? abs($qty) : $qty).' '.$uom}}
            </div>
        </div>
    </div>
    <div class="grow truncate px-2">
        <div class="flex items-center truncate">
            <div class="grow truncate p-3 sm:px-4">
                <div class="flex truncate gap-x-2 mb-2">
                    <div class="truncate font-medium text-neutral-900 dark:text-neutral-100">
                        {{ $name }}
                    </div>
                    <div>•</div>
                    <div class="truncate">
                        {{ $desc }}
                    </div>
                </div>
                <div class="flex truncate">
                    <div><i class="fa fa-fw fa-map-marker-alt mr-1"></i>{{ $loc }}</div>
                    <div class="mx-2">•</div>
                    <div>{{ $code }}</div>
                    @if($amount)
                        <div class="mx-2">•</div>
                        <div>{{ $curr . ' ' . $amount }}</div>
                    @endif
                </div>
            </div>
            <div>
                @switch($status)
                    @case(0)
                        <i class="fa fa-hourglass-half"></i>
                        @break
                    @case(1)
                        <i class="fa fa-thumbs-up"></i>
                        @break
                    @case(2)
                        <i class="fa fa-thumbs-down"></i>
                        @break
                @endswitch
            </div>
        </div>
        <hr class="border-neutral-300 dark:border-neutral-700">
        <div class="flex items-center py-2 px-3 sm:px-4">
            <div>
                <div class="w-8 h-8 mr-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                    @if($user_photo)
                    <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$user_photo }}" />
                    @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                    @endif
                </div>
            </div>
            <div class="truncate">
                <div class="truncate">
                    <div class="text-xs truncate text-neutral-400 dark:text-neutral-600">{{ $user_name }} @if($assigner) <span title="{{ $assigner }}">• <i class="fa fa-handshake-angle"></i></span> @endif {{ ' • '. $date_human}}</div>
                    <div class="truncate">@switch($qtype) @case(2) <x-badge>{{ __('Bekas') }}</x-badge> @break @case(3) <x-badge>{{ __('Diperbaiki') }}</x-badge> @break @endswitch{{ $remarks }}</div>
                </div>
            </div>
        </div>
    </div>
</label>
