<?php

use Livewire\Volt\Component;
use App\Models\InsRtcRecipe;
use Livewire\Attributes\Renderless;

new class extends Component {
    public InsRtcRecipe $recipe;
    public $recipe_id = '';

    public $is_superuser = false;

    public function rules()
    {
        return [
            'name'      => ['required','min:1', 'max:128'],
            'og_rs'     => ['required','min:1', 'max:4'],
            'std_min'   => ['required', 'numeric', 'min:0', 'max:10'],
            'std_max'   => ['required', 'numeric', 'min:0', 'max:10'],
            'std_mid'   => ['required', 'numeric', 'min:0', 'max:10'],
        ];
    }

    // public function messages()
    // {
    //     return [
    //         'user_id.exists' => __('Tag hanya boleh berisi huruf, angka, dan strip')
    //     ];
    // }

    public function placeholder()
    {
        return view('livewire.layout.modal-placeholder');
    }

    public function mount(InsRtcRecipe $recipe)
    {
        if ($recipe->id) {
            $this->recipe_id = $recipe->id;
            // other properties
        }
        $this->is_superuser = Gate::allows('superuser');
    }

    public function save()
    {
        Gate::authorize('superuser');

        // VALIDATE

        $this->validate();

        $this->js('$dispatch("close")');
    }

    public function delete()
    {
        $this->recipe->delete();
        $this->js('$dispatch("close")');
        $this->js('notyfSuccess("' . __('Resep dihapus') . '")');
        $this->dispatch('updated');
    }

};

?>

<div>
    <form wire:submit="save" class="p-6">
        <div class="flex justify-between items-start">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                {{ __('Resep') }}
            </h2>
            <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
        </div>
      
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="delete"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="delete" class="hidden"></x-spinner>
    <x-spinner-bg wire:loading.class.remove="hidden" wire:target="save"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" wire:target="save" class="hidden"></x-spinner>
</div>
