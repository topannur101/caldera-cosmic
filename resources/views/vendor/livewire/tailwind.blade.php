@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
            <div class="flex justify-between flex-1 sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <button type="button" disabled class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150"><i class="fa fa-chevron-left"></i></button>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 rounded-md transition ease-in-out duration-150"><i class="fa fa-chevron-left mr-2"></i>{{__('Sebelumnya')}}</button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 rounded-md transition ease-in-out duration-150">{{__('Selanjutnya')}}<i class="fa fa-chevron-right ml-2"></i></button>
                    @else
                        <button type="button" disabled class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150"><i class="fa fa-chevron-right"></i></button>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center">
                <div class="ml-auto">
                    <span class="relative z-0 inline-flex">
                        <span>
                            {{-- Previous Page Link --}}
                            @if ($paginator->onFirstPage())
                                <button type="button" disabled aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" rel="prev" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 rounded-md transition ease-in-out duration-150" aria-label="{{ __('pagination.previous') }}"><i class="fa fa-chevron-left"></i>
                                </button>
                            @endif
                        </span>

                        {{-- Pagination Elements --}}
                        @foreach ($elements as $element)
                            {{-- "Three Dots" Separator --}}
                            @if (is_string($element))
                                <button type="button" disabled aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150">
                                    {{ $element }}
                                </button>
                            @endif

                            {{-- Array Of Links --}}
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <button type="button" disabled aria-current="page" aria-disabled="true" class="px-4 py-2 bg-white dark:bg-neutral-800 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150">{{ $page }}</button>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 rounded-md transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            {{-- Next Page Link --}}
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" rel="next" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-caldy-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-800 disabled:opacity-25 rounded-md transition ease-in-out duration-150" aria-label="{{ __('pagination.next') }}"><i class="fa fa-chevron-right"></i>
                                </button>
                            @else
                                <button type="button" disabled aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="px-4 py-2 font-semibold text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700  disabled:opacity-25 rounded-md transition ease-in-out duration-150">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>