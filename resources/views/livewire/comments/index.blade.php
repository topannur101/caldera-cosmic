<?php

use Livewire\Volt\Component;
use App\Models\ComFile;
use App\Models\ComItem;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    public $model_name;
    public $model_id;

    #[Url]
    public string $notif_id = "";

    public int $highlight_id = 0;

    public function mount()
    {
        $notif = $this->notif_id
            ? auth()
                ->user()
                ->notifications()
                ->where("id", $this->notif_id)
                ->first()
            : null;
        $com_item = $notif ? ComItem::find($notif->data["com_item_id"] ?? false) : null;
        $this->highlight_id = $com_item ? $com_item->id : 0;
    }

    #[On("comment-added")]
    public function with(): array
    {
        $comments = ComItem::orderByDesc("updated_at")
            ->where("model_name", $this->model_name)
            ->where("model_id", $this->model_id)
            ->whereNull("parent_id")
            ->get();
        $count = ComItem::orderByDesc("updated_at")
            ->where("model_name", $this->model_name)
            ->where("model_id", $this->model_id)
            ->count();

        return [
            "comments" => $comments,
            "count" => $count,
        ];
    }

    public function download($id)
    {
        $file = ComFile::find($id);

        if ($file && Storage::exists("/public/com-files/" . $file->name ?? "")) {
            $this->js('notyf.success("' . __("Unduhan dimulai...") . '")');
            return Storage::download("/public/com-files/" . $file->name, $file->client_name);
        } else {
            $this->js('notyf.error("' . __("Berkas tidak ditemukan") . '")');
        }
    }

    #[On("scroll-to-comment")]
    public function scrollToComment()
    {
        $this->js("document.getElementById('comment-highlight')?.scrollIntoView({ behavior: 'smooth', block: 'center' });");
    }
};

?>

<div class="pb-32">
    <div>
        <h1>{{ __("Komentar") . " " . "(" . $count . ")" }}</h1>
    </div>
    {{-- <hr class="border-neutral-300 dark:border-neutral-600" /> --}}
    <livewire:comments.write wire:key="write-parent" :$model_name :$model_id />
    @if ($comments->count())
        @foreach ($comments as $comment)
            <hr class="border-neutral-200 dark:border-neutral-800" />
            <div
                wire:key="comment-{{ $comment->id }}"
                id="{{ $comment->id === $highlight_id ? "comment-highlight" : "" }}"
                class="flex gap-x-4 py-4 {{ $comment->id === $highlight_id ? "bg-caldy-500 bg-opacity-10" : "" }}"
            >
                <div>
                    <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                        @if ($comment->user->photo)
                            <img class="w-full h-full object-cover dark:brightness-75" src="/storage/users/{{ $comment->user->photo }}" />
                        @else
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 1000 1000"
                                xmlns:v="https://vecta.io/nano"
                            >
                                <path
                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                                />
                            </svg>
                        @endif
                    </div>
                </div>
                <div class="w-full">
                    <div class="flex text-xs text-neutral-400 dark:text-neutral-600 mb-1 justify-between">
                        <div>{{ $comment->user->name . " • " . $comment->created_at->diffForHumans() }}</div>
                        {{-- <div><i class="icon-ellipsis"></i></div> --}}
                    </div>

                    @if ($comment->content || $comment->files->count())
                        <div class="break-all">{!! nl2br($comment->parseContent()) !!}</div>
                    @else
                        <div class="text-neutral-500 italic">{{ __("Komentar dihapus") }}</div>
                    @endif
                    <div wire:key="files-{{ $comment->id }}">
                        @if ($comment->files->count())
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                                @foreach ($comment->files->all() as $file)
                                    <x-card-button wire:key="file-{{ $file->id }}" type="button" class="p-3" wire:click="download('{{ $file->id }}')">
                                        <div class="flex justify-center items-center h-24">
                                            <div class="flex flex-col items-center gap-1">
                                                <div><i class="{{ $file->getIcon() }} text-xl"></i></div>
                                                <div class="text-xs">{{ $file->ext . " • " . $file->getFormattedSize() }}</div>
                                            </div>
                                        </div>
                                        <div class="truncate">{{ $file->client_name }}</div>
                                    </x-card-button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div
                        x-data="{
                            open: false,
                            setFocus: function () {
                                $nextTick(() => $refs.container.querySelector('textarea').focus())
                            },
                        }"
                        x-on:click.away="open = false"
                        x-ref="container"
                    >
                        <div class="text-neutral-400 dark:text-neutral-600 text-sm mt-2">
                            <x-text-button type="button" x-on:mousedown="open = true" x-on:mouseup="setFocus()">{{ __("Balas") }}</x-text-button>
                        </div>
                        <div x-show="open" x-cloak>
                            <livewire:comments.write wire:key="write-first-{{ $comment->id }}" :$model_name :$model_id :parent_id="$comment->id" />
                        </div>
                    </div>
                    <div
                        x-data="{
                            open: false,
                            emp_id: '',
                            setFocus: function () {
                                $nextTick(() => {
                                    const ta = $refs.container.querySelector('textarea')
                                    ta.focus()
                                    ta.value = '@' + this.emp_id + ' '
                                })
                            },
                        }"
                        x-on:click.away="open = false"
                        x-ref="container"
                    >
                        @foreach ($comment->children as $child)
                            <hr class="border-neutral-200 dark:border-neutral-800 mt-4" />
                            <div
                                wire:key="child-{{ $child->id }}"
                                id="{{ $child->id === $highlight_id ? "comment-highlight" : "" }}"
                                class="flex gap-x-4 py-4 {{ $child->id === $highlight_id ? "bg-caldy-500 bg-opacity-10" : "" }}"
                            >
                                <div>
                                    <div class="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                        @if ($child->user->photo)
                                            <img class="w-full h-full object-cover dark:brightness-75" src="/storage/users/{{ $child->user->photo }}" />
                                        @else
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                                viewBox="0 0 1000 1000"
                                                xmlns:v="https://vecta.io/nano"
                                            >
                                                <path
                                                    d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                                                />
                                            </svg>
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full">
                                    <div class="flex text-xs text-neutral-400 dark:text-neutral-600 mb-1 justify-between">
                                        <div>{{ $child->user->name . " • " . $child->updated_at->diffForHumans() }}</div>
                                        {{-- <div><i class="icon-ellipsis"></i></div> --}}
                                    </div>

                                    @if ($child->content || $child->files->count())
                                        <div class="break-all">{!! nl2br($child->parseContent()) !!}</div>
                                    @else
                                        <div class="text-neutral-500 italic">{{ __("Komentar dihapus") }}</div>
                                    @endif

                                    @if ($child->files->count())
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                                            @foreach ($child->files->all() as $file)
                                                <x-card-button wire:key="file-{{ $file->id }}" type="button" class="p-3" wire:click="download('{{ $file->id }}')">
                                                    <div class="flex justify-center items-center h-24">
                                                        <div class="flex flex-col items-center gap-1">
                                                            <div><i class="{{ $file->getIcon() }} text-xl"></i></div>
                                                            <div class="text-xs">
                                                                {{ $file->ext . " • " . $file->getFormattedSize() }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="truncate">{{ $file->client_name }}</div>
                                                </x-card-button>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="text-neutral-400 dark:text-neutral-600 text-sm mt-2">
                                        <x-text-button type="button" x-on:mousedown="open = true; emp_id = '{{ $child->user->emp_id }}'" x-on:mouseup="setFocus()">
                                            {{ __("Balas") }}
                                        </x-text-button>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div x-show="open" wire:key="wrap-second-{{ $comment->id }}" x-cloak>
                            <livewire:comments.write wire:key="write-second-{{ $comment->id }}" :$model_name :$model_id :parent_id="$comment->id" />
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>

@if ($highlight_id > 0)
    @script
        <script>
            $wire.$dispatch('scroll-to-comment');
        </script>
    @endscript
@endif
