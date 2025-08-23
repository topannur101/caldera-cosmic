@props([
    "id",
    "grow" => false,
    "title",
])

<div class="cal-radio relative {{ $grow ? "grow" : "" }}" title="{{ $title ?? false ?: "" }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        {{ $attributes->merge(["class" => "w-px h-px absolute top-0 left-0 opacity-0"]) }}
    />
    <label
        for="{{ $id }}"
        class="flex flex-col h-full cursor-pointer px-4 py-2 opacity-50 font-semibold text-xs text-neutral-700 dark:text-neutral-300 uppercase tracking-widest hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-25 transition ease-in-out duration-150"
    >
        {{ $slot }}
        <div class="cal-checked w-1 h-1 bg-caldy-500 mx-auto rounded-full hidden"></div>
    </label>
</div>
