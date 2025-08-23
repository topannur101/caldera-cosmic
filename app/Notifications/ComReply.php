<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ComReply extends Notification implements ShouldQueue
{
    use Queueable;

    protected $com_item; // Add this property declaration

    /**
     * Create a new notification instance.
     */
    public function __construct($com_item) // Add parameter to constructor
    {
        $this->com_item = $com_item; // Store the passed value
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'com_item_id' => $this->com_item->id,
            'user_id' => $this->com_item->user_id,
            'model_name' => $this->com_item->model_name,
            'model_id' => $this->com_item->model_id,
            'content' => $this->com_item->content,
            'url' => $this->com_item->url,
        ];
    }
}
