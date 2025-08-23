<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;

class Notification extends Component
{
    public $notification;

    public $icon;

    public $user;

    public $content;

    public $url;

    public $timestamp;

    public function __construct($notification, public string $presentation = 'dropdown')
    {
        $this->notification = $notification;
        $this->icon = $notification['data']['icon'] ?? 'icon-bell';
        $this->user = User::find($notification['data']['user_id'] ?? null);
        $this->content = $notification['data']['content'];
        $this->url = $notification['data']['url'].(parse_url($notification['data']['url'], PHP_URL_QUERY) ? '&' : '?').'notif_id='.$notification->id;

    }

    public function render()
    {
        // Choose the appropriate view based on notification type
        $type = class_basename($this->notification['type']);

        return view('components.notifications.'.$type);
    }
}
