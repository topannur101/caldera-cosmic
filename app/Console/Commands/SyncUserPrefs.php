<?php

namespace App\Console\Commands;

use App\Models\Pref;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserPrefs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-user-prefs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user session parameters to preferences table';

    /**
     * Execute the console command.
     */
    // Define the session parameters you want to sync
    protected $sessionParams = [
        'inv_circs_params',
        'inv_items_params',
        'inv_areas_param',
    ];

    public function handle()
    {
        $this->info('Starting user preferences sync...');

        // Get active users with their most recent session
        $activeUsers = $this->getActiveUsers();

        $syncedCount = 0;
        $deletedCount = 0;

        foreach ($activeUsers as $user) {
            $sessionData = $this->getSessionData($user->payload);

            foreach ($this->sessionParams as $paramName) {
                if (array_key_exists($paramName, $sessionData)) {
                    // Parameter exists in session
                    $newValue = $sessionData[$paramName];
                    $updated = $this->updateOrCreatePreference($user->user_id, $paramName, $newValue);
                    if ($updated) {
                        $syncedCount++;
                    }
                } else {
                    // Parameter doesn't exist in session, delete from prefs
                    $deleted = $this->deletePreference($user->user_id, $paramName);
                    if ($deleted) {
                        $deletedCount++;
                    }
                }
            }
        }

        $this->info("Preferences sync completed. Synced: {$syncedCount}, Deleted: {$deletedCount}");
    }

    private function getActiveUsers()
    {
        return DB::table('sessions')
            ->select('user_id', 'payload', 'last_activity')
            ->whereNotNull('user_id')
            ->where('last_activity', '>', now()->subMinutes(30)->timestamp) // Consider active if last activity within 30 minutes
            ->orderBy('user_id')
            ->orderBy('last_activity', 'desc')
            ->get()
            ->groupBy('user_id')
            ->map(function ($sessions) {
                return $sessions->first(); // Get the most recent session for each user
            });
    }

    private function getSessionData($payload)
    {
        // Laravel sessions are serialized, need to unserialize
        $data = base64_decode($payload);

        // Handle Laravel's session serialization format
        if (strpos($data, 'a:') === 0) {
            // PHP serialize format
            $sessionData = unserialize($data);
        } else {
            // Try to decode as Laravel's default format
            $sessionData = unserialize(base64_decode($data));
        }

        return $sessionData ?? [];
    }

    private function updateOrCreatePreference($userId, $paramName, $newValue)
    {
        $existingPref = Pref::where('user_id', $userId)
            ->where('name', $paramName)
            ->first();

        $newValueJson = json_encode($newValue);

        if ($existingPref) {
            // Compare with existing data using strict comparison
            if ($existingPref->data !== $newValueJson) {
                $existingPref->update(['data' => $newValueJson]);
                $this->line("Updated {$paramName} for user {$userId}");

                return true;
            }
        } else {
            // Create new preference record
            Pref::create([
                'user_id' => $userId,
                'name' => $paramName,
                'data' => $newValueJson,
            ]);
            $this->line("Created {$paramName} for user {$userId}");

            return true;
        }

        return false;
    }

    private function deletePreference($userId, $paramName)
    {
        $deleted = Pref::where('user_id', $userId)
            ->where('name', $paramName)
            ->delete();

        if ($deleted) {
            $this->line("Deleted {$paramName} for user {$userId}");

            return true;
        }

        return false;
    }
}
