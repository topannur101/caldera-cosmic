<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'emp_id',
        'password',
        'photo',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function updatePhoto($photo)
    {
        if($photo) {
            if ($this->photo != $photo) {
                $path = storage_path('app/livewire-tmp/'.$photo);        
                // $image = Image::make($path);
            
                // // Resize the image to a maximum height of 600 pixels while maintaining aspect ratio
                // $image->resize(192, 192, function ($constraint) {
                //     $constraint->aspectRatio();
                //     $constraint->upsize();
                // });
                
                // $image->encode('jpg', 70);

                // process photo
                $manager = new ImageManager(new Driver());
                $image = $manager->read($path)
                ->scaleDown(width: 192)
                ->toJpeg(90);


        
                // Set file name and save to disk and save filename to inv_item
                $id     = $this->id;
                $time   = Carbon::now()->format('YmdHis');
                $rand   = Str::random(5);
                $name   = $id.'_'.$time.'_'.$rand.'.jpg';
        
                Storage::put('/public/users/'.$name, $image);
        
                return $this->update([
                    'photo' => $name,
                ]);
            }
        } else {
            return $this->update([
                'photo' => null,
            ]);
        }
    }
    
    public function prefs(): HasMany
    {
        return $this->hasMany(Pref::class);
    }

    public function inv_auths(): HasMany
    {
        return $this->hasMany(InvAuth::class);
    }

    public function authInvArea($id): bool
    {
        return $this->inv_auths->where('inv_area_id', $id)->count() ? true : false;
    }

    public function inv_areas() : BelongsToMany
    {
        return $this->belongsToMany(InvArea::class, 'inv_auths', 'user_id', 'inv_area_id');
    }

    public function invAreaIdsItemCreate(): array
    {
        $ids = [];

        if($this->id === 1) {
            $ids = $this->invAreaIds();          
        } else {
            foreach ($this->inv_auths as $auth) {
                // Decode the "actions" string into an array
                $actions = json_decode($auth['actions'], true);
    
                // Check if "item-create" is present in the actions array
                if (in_array('item-create', $actions)) {
                    $ids[] = $auth['inv_area_id'];
                }
            }
        }
        return $ids;
    }
    
    public function invAreaIdsCircCreate(): array
    {
        $ids = [];

        if($this->id === 1) {
            $ids = $this->invAreaIds();          
        } else {
            foreach ($this->inv_auths as $auth) {
                // Decode the "actions" string into an array
                $actions = json_decode($auth['actions'], true);
    
                // Check if "item-create" is present in the actions array
                if (in_array('circ-create', $actions)) {
                    $ids[] = $auth['inv_area_id'];
                }
            }
        }
        return $ids;
    }
    
    public function invAreaIds(): array
    {
        return $this->id === 1 ? InvArea::all()->pluck('id')->toArray() : $this->inv_areas->pluck('id')->toArray();
    }

    public function kpi_areas() : BelongsToMany
    {
        return $this->belongsToMany(KpiArea::class, 'kpi_auths', 'user_id', 'kpi_area_id');
    }

    public function kpiAreaIds(): array
    {
        return $this->id === 1 ? InvArea::all()->pluck('id')->toArray() : $this->kpi_areas->pluck('id')->toArray();
    }

    public function ins_rtc_auths(): HasMany
    {
        return $this->hasMany(InsRtcAuth::class);
    }

    public function ins_omv_auths(): HasMany
    {
        return $this->hasMany(InsOmvAuth::class);
    }

    public function ins_rdc_auths(): HasMany
    {
        return $this->hasMany(InsRdcAuth::class);
    }

    public function ins_ldc_auths(): HasMany
    {
        return $this->hasMany(InsLdcAuth::class);
    }

    public function ins_stc_auths(): HasMany
    {
        return $this->hasMany(InsStcAuth::class);
    }
}
