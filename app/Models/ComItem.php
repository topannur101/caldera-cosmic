<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'mod',
        'mod_id',
        'user_id',
        'parent_id',
        'content',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ComFile::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(ComItem::class, 'parent_id');
    }

    public function parseContent()
    {
        $pattern = '/@(\w+)/';

        return preg_replace_callback($pattern, function($matches) {
            $username = $matches[1];
            $user = User::where('emp_id', $username)->first();
    
            if ($user) {
                return '<span title="'.$user->emp_id.'" class="text-neutral-400 dark:text-neutral-600">@' . $user->name . '</span>';
            }
    
            return '@' . $username; // If the user doesn't exist, return the original text
        }, e($this->content));
    }

    public function saveFile($file)
    {   
        $id     = $this->id;
        $time   = Carbon::now()->format('YmdHis');
        $rand   = Str::random(10);
        $ext    = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $name   = $file->hashName();

        // check is_image
        $mimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileMimeType = $file->getMimeType();
        $is_image = in_array($fileMimeType, $mimeTypes) ? true : false;

        Storage::put('/public/com-files/', $file);

        return ComFile::create([
            'com_item_id'   => $id,
            'name'          => $name,
            'client_name'   => $file->getClientOriginalName(),
            'size'          => $file->getSize(),
            'ext'           => $ext ? $ext : '?',  
            'is_image'      => $is_image,
        ]);
    }
}
