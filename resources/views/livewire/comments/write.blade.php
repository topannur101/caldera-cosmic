<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\ComItem;
use Livewire\WithFileUploads;
use Livewire\Attributes\Renderless;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {

    use WithFileUploads;

    public $mod;
    public $parent_id;
    public $users = [];
    public $userq;
    public $user_id;
    public $content;
    public $files = [];

    public function placeholder()
    {
        return view('livewire.modal-placeholder');
    }

    public function mount()
    {
        $this->user_id = Auth::user()->id;
    }

    #[Renderless]
    public function updatedUserq()
    {
        if($this->userq) {
            $q = $this->userq;
            $this->users = User::where(function (Builder $query) use ($q) {
                $query->orWhere('name', 'LIKE', '%'.$q.'%')
                      ->orWhere('emp_id', 'LIKE', '%'.$q.'%');
            })->where('is_active', 1)->get();
        } else {
            $this->users = [];
        }
    }

    public function save()
    {
        $this->validate([
            'user_id'   => ['required', 'integer', 'exists:App\Models\User,id'],
            'content'   => ['required_without:files', 'max:999'],
            'files.*'   => ['max:51200'],
        ], [
            'content.required_without' => __('Isi komentar atau unggah lampiran')
        ]);
        
        $name = $this->mod ? class_basename($this->mod) : 'test';
        $com_item = ComItem::create([
            'user_id' => $this->user_id,
            'content'   => $this->content,
            'mod'       => $name,
            'mod_id'    => $this->mod?->id ?? 1,
        ]);

        if ($this->parent_id) {
            $com_item->update([
                'parent_id' => $this->parent_id,
            ]);
        }

        // handle files here
        foreach($this->files as $file) {
            $com_item->saveFile($file);
        }

        $this->reset(['content', 'files']);
        $this->js('toast("'.__('Komentar ditambahkan').'", { type: "success" })'); 
        $this->dispatch('comment-added');
    }

    public function resetFiles()
    {
        $this->reset(['files']);
    }   

};

?>

<div x-data="{
    userp: false,
    userq: @entangle('userq').live,
    content: @entangle('content'),
    updateUserq: function(event) {
        const textarea = event.target;
        const word = this.getWordAtPosition(textarea);

        if (word.startsWith('@')) {
            this.userp = true;
            this.userq = word.substring(1); // Removing '@' from the word
        } else {
            this.userp = false;
            this.userq = '';
        }
    },
    getWordAtPosition: function(textarea) {
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const textBeforeCursor = value.slice(0, cursorPosition);

        const wordsArray = textBeforeCursor.split(/\s/);
        const lastWord = wordsArray[wordsArray.length - 1];

        return lastWord.trim(); // Trim to remove extra spaces
    },
    replaceWord: function(empid) {
        const textarea = this.$refs.comment;
        const value = this.content;
        const cursorPosition = textarea.selectionStart;
        const textBeforeCursor = value.slice(0, cursorPosition);

        const wordsArray = textBeforeCursor.split(/\s/);
        const lastWord = wordsArray[wordsArray.length - 1];
        const replacedWord = `@${empid} `; // The word to replace with (keeping @)

        if (lastWord.startsWith('@')) {
            const replacedText = textBeforeCursor.slice(0, textBeforeCursor.length - lastWord.length) + replacedWord;
            const updatedText = replacedText + value.slice(cursorPosition);
            this.content = updatedText;
            this.userp = false;
            this.userq = `${empid}`; // Update userq property
            this.$refs.comment.focus();
        }
    }
}" class="relative my-8">
    <div class="relative" wire:target="save" wire:loading.class="opacity-30">
        <div class="absolute bottom-1 left-0 w-full flex justify-center">
            @if(count($users))
            <div x-show="userp" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
                @foreach($users as $user)
                <button type=button class="flex p-3 text-left w-full hover:bg-caldy-500/10 active:bg-caldy-500/30" x-on:click="replaceWord('{{ $user->emp_id }}')">
                    <div class="w-8 h-8 my-auto mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                        @if($user->photo)
                        <img class="w-full h-full object-cover dark:brightness-75" src="{{ '/storage/users/'.$user->photo }}" />
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                        @endif
                    </div>
                    <div>
                        <div>{{ $user->name }}</div>
                        <div class="text-xs text-neutral-400 dark:text-neutral-600">{{ $user->emp_id }}</div>
                    </div>            
                </button>
                @endforeach
            </div>
            @endif
        </div>        
    </div>    
    <form wire:submit.prevent="save" class="flex gap-x-4" wire:target="save" wire:loading.class="opacity-30">
        <div>
            <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                @if (Auth::user()->photo)
                    <img class="w-full h-full object-cover dark:brightness-75"
                        src="/storage/users/{{ Auth::user()->photo }}" />
                @else
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000"
                        xmlns:v="https://vecta.io/nano">
                        <path
                            d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                    </svg>
                @endif
            </div>
        </div>
        <div class="w-full">
            <textarea x-model="content" rows="1" name="comment" x-ref="comment" x-on:focusin="buttons = true" x-on:input="updateUserq"
                placeholder="{{ __('Tulis komentar...') }}" style="min-height:66px;"
                class="block w-full border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 focus:border-caldy-500 dark:focus:border-caldy-600 focus:ring-caldy-500 dark:focus:ring-caldy-600 rounded-md shadow-sm"></textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-1" />
            <div wire:key="files" class="flex justify-between items-start mt-3">
                @if(count($files))
                <div class="text-sm">
                    @foreach($files as $file)
                    <div class="mb-1">{{ $file->getClientOriginalName() }}</div>
                    @endforeach
                    <x-text-button type="button" wire:click="resetFiles" class="mt-3"><i class="fa fa-times mr-2"></i>{{ __('Buang lampiran') }}</x-text-button>
                </div>
                @else
                <div class="text-sm">
                    <x-text-button type="button" x-on:click="$refs.upload.click()"><i class="fa fa-paperclip mr-2"></i>{{ __('Lampirkan') }}</x-text-button>
                    <input x-ref="upload" type="file" wire:model="files" hidden multiple />
                </div>
                @endif
                <div>
                    <span class="mr-2 text-xs font-mono" x-text="999 - (content ? content.length : 0)"></span>
                    <x-primary-button type="submit">{{ __('Kirim') }}</x-primary-button>
                </div>
            </div>
        </div>
    </form>  
    <div wire:loading.class.remove="hidden" wire:target="save" class="w-full h-full absolute top-0 left-0 hidden"></div>
    <x-spinner wire:target="save" wire:loading.class.remove="hidden"  class="hidden"></x-spinner>  
</div>