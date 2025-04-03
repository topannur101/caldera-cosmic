<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckNotification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('notif_id') && auth()->check()) {
            $notif_id = $request->notif_id;
            $notification = auth()->user()->notifications()->whereNull('read_at')->find($notif_id);
            
            if ($notification) {
                $notification->markAsRead();
                $ackCount = auth()->user()->unreadNotifications->count();
                session()->put('ackCount', $ackCount);
            }
        }

        return $next($request);
    }
}
