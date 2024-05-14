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
            'recipe_id'     => $this->ins_rtc_clump->ins_rtc_recipe_id,
            'line'          => $this->ins_rtc_clump->ins_rtc_device->line,
            'dt_client'     => $this->dt_client,

            'sensor_left'   => $this->sensor_left,
            'sensor_right'  => $this->sensor_right,
            'std_mid'       => $this->ins_rtc_clump->ins_rtc_recipe->std_mid ?? 0,
            'is_correcting' => $this->is_correcting,
        ];
    }
}