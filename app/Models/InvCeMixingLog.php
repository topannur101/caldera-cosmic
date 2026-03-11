<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvCeMixingLog extends Model
{
    protected $table = 'inv_ce_mixing_logs';

    protected $fillable = [
        'recipe_id',
        'user_id',
        'batch_number',
        'duration',
        'notes',
        'status',
    ];

    /**
     * The recipe associated with this mixing log.
     */
    public function recipe()
    {
        return $this->belongsTo(InvCeRecipe::class, 'recipe_id');
    }

    /**
     * The user who performed the mixing.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
