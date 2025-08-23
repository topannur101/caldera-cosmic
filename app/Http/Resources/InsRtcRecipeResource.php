<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsRtcRecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'og_rs' => $this->og_rs,
            'std_min' => $this->std_min,
            'std_max' => $this->std_max,
            'std_mid' => $this->std_mid,

        ];
    }
}
