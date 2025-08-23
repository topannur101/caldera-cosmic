<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FeatureNew extends Notification implements ShouldQueue
{
    use Queueable;

    protected $feature; // Add this property declaration

    /**
     * Create a new notification instance.
     */
    public function __construct($feature) // Add parameter to constructor
    {
        $this->feature = $feature; // Store the passed value
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
            'icon' => $this->feature['icon'],
            'content' => $this->feature['content'],
            'url' => $this->feature['url'],
        ];
    }
}
