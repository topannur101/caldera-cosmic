<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('layouts.app')] 
class extends Component {

    public function with(): array
    {
      $notifications = [];

      $user = auth()->user();
      if ($user) {
          $notifications = $user->notifications()->orderBy('created_at', 'desc')->take(100)->get();
      }  

      foreach ($notifications as $notification) {
        $notification['url'] = $notification['data']['url'] . (parse_url($notification['data']['url'], PHP_URL_QUERY) ? '&' : '?') . 'notif_id=' . $notification->id;

        switch ($notification['type']) {
            case 'App\Notifications\ComMention':
                $user = User::find($notification['data']['user_id']);                    
                $notification['content'] = '
                <div class="flex gap-x-2">
                    <div>
                        <div class="mt-1 w-4 h-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            ' . ($user?->photo ? '<img class="w-full h-full object-cover dark:brightness-75" src="/storage/users/' . $user?->photo . '" />' : '
                            <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                            </svg>') . '
                        </div>
                    </div>
                    <div class="grow">
                        <div>
                            <span class="font-bold">' . $user?->name . '</span>
                            ' . __('menyebutmu') . ': ' . e($notification['data']['content']) . '
                        </div>
                    </div>
                </div>';
                break;
            case 'App\Notifications\ComReply':
                $user = User::find($notification['data']['user_id']);                    
                $notification['content'] = '
                <div class="flex gap-x-2">
                    <div>
                        <div class="mt-1 w-4 h-4 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            ' . ($user?->photo ? '<img class="w-full h-full object-cover dark:brightness-75" src="/storage/users/' . $user?->photo . '" />' : '
                            <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano">
                                <path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z" />
                            </svg>') . '
                        </div>
                    </div>
                    <div class="grow">
                        <div>
                            <span class="font-bold">' . $user?->name . '</span>
                            ' . __('membalas') . ': ' . e($notification['data']['content']) . '
                        </div>
                    </div>
                </div>';
                break;
            
            default:
                # code...
                break;
        }
    }

      return [
          'notifications' => $notifications,
      ];
    }


};
?>
<x-slot name="title">{{ __('Notifikasi') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
    <div>
        <h1 class="px-6 text-2xl text-neutral-900 dark:text-neutral-100">{{ __('Notifikasi') }}</h1>
        <div class="p-6 flex flex-col sm:flex-row gap-y-6 justify-between">
          <div class="btn-group">
              <x-radio-button wire:model.live="view" value="read" name="view" id="view-list">{{ __('Semua') }}</x-radio-button>
              <x-radio-button wire:model.live="view" value="unread" name="view" id="view-content">{{ __('Belum dibaca') }}</x-radio-button>
          </div>
          <x-text-button type="button" class="uppercase tracking-wide font-bold text-xs" wire:click="markAllRead"><i class="fa fa-check mr-2"></i>{{ __('Tandai semua sebagai sudah dibaca') }}</x-secondary-button>

        </div>
        <div class="bg-white dark:bg-neutral-800 shadow table sm:rounded-lg">
          @foreach($notifications as $notification)
            <x-dropdown-link :href="$notification['url']" wire:navigate>
                <div class="flex gap-x-2 items-center">
                    <div class="grow text-sm">
                        {!! $notification['content'] !!}
                    </div>
                    @if(!$notification['read_at'])
                    <div>
                        <div class="w-2 h-2 rounded-full bg-caldy-500"></div>
                    </div>
                    @endif
                </div>
            </x-dropdown-link>                            
          @endforeach
          <div wire:key="notifications-none">
              @if (!$notifications->count())
                  <div class="text-center py-12">
                      {{ __('Tak ada notifikasi ditemukan') }}
                  </div>
              @endif
          </div>
      </div>
    </div>
</div>
