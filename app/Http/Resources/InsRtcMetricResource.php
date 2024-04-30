<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsRtcMetricResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'line'          => $this->ins_rtc_device->line,
            'dt_client'     => $this->dt_client,
            'recipe_id'     => $this->ins_rtc_recipe_id,
            'act_left'      => $this->act_left,
            'act_right'     => $this->act_right,
            'std_mid'       => $this->ins_rtc_recipe->std_mid,
            'is_correcting' => $this->is_correcting,
        ];
    }
}