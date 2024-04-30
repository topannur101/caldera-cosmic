<x-ss-layout>
    @switch($id)
        @case(1)
            <livewire:insight.ss.1 />
        @break
        @case(2)
        <livewire:insight.ss.2 />
        @break
        @case(3)
        <livewire:insight.ss.3 />
        @break
    @break
    @endswitch
</x-ss-layout>
