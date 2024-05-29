<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use League\Csv\Writer;
use App\Models\InsRtcMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class CsvController extends Controller
{
    public function insRtcMetrics(Request $request)
    {
        $start = Carbon::parse($request['start_at']);
        $end = Carbon::parse($request['end_at'])->addDay();

        $metrics = InsRtcMetric::whereBetween('dt_client', [$start, $end])->orderBy('dt_client', 'DESC');

        $items = $metrics->get();

        // Create CSV file using league/csv
        $csv = Writer::createFromString('');
        $csv->insertOne([
            __('Line'), 
            __('ID Gilingan'),
            __('ID Resep'),
            __('Nama resep'),
            __('Standar tengah'),
            __('Koreksi Oto.'),
            __('Kiri tindakan'), 
            __('Kiri terukur'), 
            __('Kanan tindakan'),
            __('Kanan terukur'),
            __('Waktu'), 
        ]); 

        foreach ($items as $item) {
            $action_left = '';
            switch ($item->action_left) {
                case 'thin':
                    $action_left = __('Tipis');
                    break;
                case 'thick':
                $action_left = __('Tebal');
                break;
            }

            $action_right = '';
            switch ($item->action_right) {
                case 'thin':
                    $action_right = __('Tipis');
                    break;
                case 'thick':
                    $action_right = __('Tebal');
                    break;
            }

            $csv->insertOne([
                $item->ins_rtc_clump->ins_rtc_device->line, 
                $item->ins_rtc_clump_id,
                $item->ins_rtc_clump->ins_rtc_recipe->id,
                $item->ins_rtc_clump->ins_rtc_recipe->name,
                $item->ins_rtc_clump->ins_rtc_recipe->std_mid,
                $item->is_correcting ? 'ON' : 'OFF',
                $action_left, 
                $item->sensor_left, 
                $action_right,
                $item->sensor_right,
                $item->dt_client, 
            ]); // Add data rows
        }
        

        // Generate CSV file and return as a download
        $fileName = __('Wawasan_RTC') . '_' . date('Y-m-d_Hs') . '.csv';

        return Response::stream(
            function () use ($csv) {
                echo $csv->toString();
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ],
        );

    }
}
