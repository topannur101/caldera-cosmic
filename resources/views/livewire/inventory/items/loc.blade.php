<div @if(!$isForm) class="p-6" @endif>
    @if(!$isForm)
    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
        {{ __('Edit lokasi') }}
    </h2>
    @endif
    <x-text-input-icon x-ref="input" wire:model.live="loc" icon="icon-map-pin" id="loc" list="qlocs"
    type="text" placeholder="{{ __('Lokasi') }}" />
    <datalist id="qlocs">
        @if(count($qlocs))
            @foreach($qlocs as $qloc)
                <option wire:key="{{ 'qloc'.$loop->index }}" value="{{ $qloc }}">
            @endforeach
        @endif
    </datalist>
    @error('loc')
        <x-input-error messages="{{ $message }}" class="mt-2" />
    @enderror
    @if(!$isForm)
    <div class="flex">
        <x-primary-button type="button" wire:click="apply" class="ml-auto mt-4">{{__('Perbarui')}}</x-primary-button>
    </div>
    @endif
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="apply"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden"></x-spinner>
</div>