<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

use App\Models\InvTag;
use App\Models\InvItem;

new class extends Component {

    public $inv_area_id;

    public $isForm = false;

    public $id;
    public $tags = [];
    public $qtags = [];

    public function rules()
    {
        return [
            'tags.*' => ['nullable', 'alpha_dash', 'max:20'],
        ];
    }

    public function messages() 
    {
        return [
            'tags.*.alpha_dash' => __('Hanya huruf, angka, dan strip'),
            'tags.*.max' => __('Maksimal 20 karakter'),
        ];
    }

    #[On('updated')]
    public function mount()
    {
        $item = InvItem::find($this->id);
        if($item) {
            $this->tags = $item->tags_array();
        }
    }

    public function addTag()
    {
        $this->tags[] = '';
    }

    public function removeTag($i)
    {
        unset($this->tags[$i]);
        $this->tags = array_values($this->tags); // Reindex the array
        $this->dispatch('tags-applied', tags: $this->tags);
    }

    public function updatedTags($value, $index)
    {
        $tag = '%'.$value.'%';
        $qtags = InvTag::where('inv_area_id', $this->inv_area_id)
        ->where('name', 'LIKE', $tag)
        ->orderBy('name')
        ->take(100)
        ->get()
        ->pluck('name');
        $this->qtags[$index] = $qtags->toArray();
    }

    public function apply()
    {
        if ($this->isForm) {
            $this->dispatch('tags-applied', tags: $this->tags);
        } else {
            $this->validate();
            $item = InvItem::find($this->id);
            if($item) {
                $item->updateTags($this->tags);
                $this->js('window.dispatchEvent(escKey)'); 
                $this->js('notyf.success("'.__('Tag diperbarui').'")');
                $this->dispatch('updated');
            }

        }

    }

}

?>

<div>
    <div @if($isForm) Wire:click.away="apply" @endif class="p-6">
        @if($isForm)
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Tag') }}
        </h2>
        @else
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Edit tag') }}
        </h2>
        @endif
        @foreach($tags as $i => $tag)
        <div class="flex mt-3">
            <div class="w-full">
                <x-text-input-icon wire:model.live="tags.{{ $i }}" type="text" icon="fa fa-fw fa-tag" id="tag{{ $i }}" list="qtags{{ $i }}"></x-text-input-icon>
            </div>
            <x-text-button wire:click="removeTag({{ $i }})" type="button" class="ms-3"><i class="fa fa-times"></i></x-text-button>
        </div>
        <datalist id="qtags{{ $i }}">
            @if(isset($qtags[$i]))
                @foreach($qtags[$i] as $qtag)
                    <option wire:key="{{ 'qtag'.$loop->index }}" value="{{ $qtag }}">
                @endforeach
            @endif
        </datalist>
        @error('tags.'.$i)
            <x-input-error messages="{{ $message }}" class="mt-2" />
        @enderror
        @endforeach
        <div wire:key="addTag">
            @if(count($tags) < 5)
                <x-text-button type="button" class="mt-3" wire:click="addTag"><i class="fa fa-plus mr-2"></i>{{ __('Tambah tag') }}</x-text-button>
            @endif
        </div>
        @if(!$isForm)
        <div class="flex">
            <x-primary-button type="button" wire:click="apply" class="ml-auto mt-4">{{__('Perbarui')}}</x-primary-button>
        </div>
        @endif
        <x-spinner-bg wire:loading.class.remove="hidden" wire:target="apply"></x-spinner-bg>
        <x-spinner wire:loading.class.remove="hidden" wire:target="apply" class="hidden"></x-spinner>
    </div>  
</div>