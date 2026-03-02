<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsIbmsAuth extends Model
{
    protected $table = 'ins_ip_blend_auths';

    protected $fillable = [
        'user_id',
        'actions',
    ];

    protected $casts = [
        'actions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function countActions()
    {
        $actions = json_decode($this->actions ?? '{}', true);
        return count($actions);
    }

    public function hasAction(string $action)
    {
        return in_array($action, $this->actions);
    }

    public function canManageDevices()
    {
        return $this->hasAction('device-manage');
    }
}
