<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use App\Models\Announcement;

new #[Layout("layouts.app")] class extends Component {
    #[Url]
    public int $id = 0;

    public array $announcements = [
        0 => [
            "id" => "",
            "title" => "",
            "content" => "",
        ],
    ];

    public function mount()
    {
        $announcement = Announcement::findOrFail($this->id);
        if ($announcement) {
            $this->announcements[0]["id"] = $announcement->id;
            $this->announcements[0]["title"] = $announcement->title;
            $this->announcements[0]["content"] = $announcement->content;
        }
    }
};
?>

<x-slot name="title">{{ $announcements[0]["title"] . " - " . __("Pengumuman") }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <h1 class="px-6 text-2xl text-neutral-900 dark:text-neutral-100">{{ __("Pengumuman") }}</h1>

    <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-auto mt-8">
        <div class="p-6">
            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-3">
                {{ $announcements[0]["title"] }}
            </h2>
            <p>
                {!! $announcements[0]["content"] !!}
            </p>
        </div>
    </div>
</div>
