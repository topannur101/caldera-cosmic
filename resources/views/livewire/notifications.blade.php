<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.app')] 
class extends Component {

    use WithPagination;

    public string $view = 'all';

    public int $perPage = 20;

    #[On('updated')]
    public function with(): array
    {

      $user = auth()->user();

      switch ($this->view) {    
        case 'unread':
            $notifications = $user->unreadNotifications();
            break;

        default:
            $notifications = $user->notifications();
            break;
      }

      $unreadCount = $user->unreadNotifications->count();
      $notifications->orderBy('created_at', 'desc');

      return [
        'unreadCount'   => $unreadCount,
        'notifications' => $notifications->paginate($this->perPage),
      ];
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();
        $this->js('toast("' . __('Semua notifikasi ditandai sudah dibaca') . '", { type: "success" })');
    }

    public function updating($property)
    {
        if ($property == 'view') {
            $this->reset('perPage');
        }
    }

    public function loadMore()
    {
        $this->perPage += 20;
    }


};
?>
<x-slot name="title">{{ __('Notifikasi') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <h1 class="px-6 text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Notifikasi') }}</h1>
    <div class="p-6 flex flex-col sm:flex-row gap-y-6 justify-between items-center">
        <div class="btn-group">
            <x-radio-button wire:model.live="view" value="all" name="view" id="view-all">
                <div class="my-auto">{{ __('Semua') }}</div>
            </x-radio-button>
            <x-radio-button wire:model.live="view" value="unread" name="view" id="view-unread">
                <div class="my-auto">{{ __('Belum dibaca') }}</div>
            </x-radio-button>
        </div>
        <div>
            <x-text-button type="button" class="uppercase tracking-wide font-bold text-xs" wire:click="markAllAsRead"><i class="icon-circle-check-check mr-2"></i>{{ __('Tandai semua sudah dibaca') }}</x-secondary-button>
        </div>
    </div>
    <div wire:loading.class="cal-shimmer" class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
        @foreach($notifications as $notification)
            <x-notification :$notification presentation="page" />                          
        @endforeach
        <div wire:key="notifications-none">
            @if (!$notifications->count())
                <div class="text-center py-12">
                    {{ __('Tak ada notifikasi ditemukan') }}
                </div>
            @endif
        </div>
    </div>
    <div wire:key="observer" class="flex items-center relative h-16">
        @if (!$notifications->isEmpty())
            @if ($notifications->hasMorePages())
                <div wire:key="more" x-data="{
                    observe() {
                        const observer = new IntersectionObserver((notifications) => {
                            notifications.forEach(notification => {
                                if (notification.isIntersecting) {
                                    @this.loadMore()
                                }
                            })
                        })
                        observer.observe(this.$el)
                    }
                }" x-init="observe"></div>
                <x-spinner class="sm" />
            @else
                <div class="mx-auto">{{ __('Tidak ada lagi') }}</div>
            @endif
        @endif
    </div>
</div>
