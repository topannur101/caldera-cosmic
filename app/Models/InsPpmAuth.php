<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsPpmAuth extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actions',
    ];

    protected $casts = [
        'actions' => 'array',
    ];

    /**
     * Get the user that owns this authorization
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user has specific action permission
     */
    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions ?? []);
    }

    /**
     * Get auth record for a specific user
     */
    public static function forUser(int $userId): ?static
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Check if a user has PPM permissions
     */
    public static function userHasPermission(int $userId, string $action): bool
    {
        // User ID 1 is always superuser
        if ($userId === 1) {
            return true;
        }

        $auth = static::forUser($userId);
        return $auth ? $auth->hasAction($action) : false;
    }

    /**
     * Available actions for PPM module
     */
    public static function availableActions(): array
    {
        return [
            'product-manage' => 'Manage products',
            'component-manage' => 'Manage components',
            'line-running-manage' => 'Manage line running',
        ];
    }
}
