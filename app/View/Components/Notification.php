<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\User;

class Notification extends Component
{
    public $notification;
    public $user;
    public $content;
    public $timestamp;
    public $url;
    
    public function __construct($notification, public string $presentation = 'dropdown')
    {
        $this->notification   = $notification;
        $this->url            = $notification['data']['url'] . (parse_url($notification['data']['url'], PHP_URL_QUERY) ? '&' : '?') . 'notif_id=' . $notification->id;
        $this->user           = User::find($notification['data']['user_id']);
        $this->content        = $notification['data']['content'];
    }
    
    public function render()
    {
        // Choose the appropriate view based on notification type
        $type = class_basename($this->notification['type']);
        return view('components.notifications.' . $type);
    }
}