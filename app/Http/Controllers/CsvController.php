<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use League\Csv\Writer;
use App\Models\InsRtcMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;

class CsvController extends Controller
{
    public function insRtcMetrics(Request $request)
    {
        if (! Auth::user() ) {
            abort(403);
        }
        
        $start = Carbon::parse($request['start_at']);
        $end = Carbon::parse($request['end_at'])->addDay();

        $metrics = InsRtcMetric::whereBetween('dt_client', [$start, $end])->orderBy('dt_client', 'DESC')->get();

        $headers = [
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
        ];

        $callback = function() use($metrics, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach($metrics as $metric) {

                $row['line']            = $metric->ins_rtc_clump->ins_rtc_device->line ?? '';
                $row['clump_id']        = $metric->ins_rtc_clump_id ?? '';
                $row['recipe_id']       = $metric->ins_rtc_clump->ins_rtc_recipe->id ?? '' ;
                $row['recipe_name']     = $metric->ins_rtc_clump->ins_rtc_recipe->name ?? '';
                $row['std_mid']         = $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '';
                $row['is_correcting']   = $metric->is_correcting ? 'ON' : 'OFF';
                $row['action_left']     = $metric->action_left ?? '' == 'thin' ? __('Tipis') : ($metric->action_left ?? '' == 'thick' ? __('Tebal') : '');
                $row['sensor_left']     = $metric->sensor_left ?? '';
                $row['action_right']    = $metric->action_right ?? '' == 'thin' ? __('Tipis') : ($metric->action_right ?? '' == 'thick' ? __('Tebal') : '');
                $row['sensor_right']    = $metric->sensor_right ?? '';
                $row['dt_client']       = $metric->dt_client;      

                fputcsv($file, [
                    $row['line'],
                    $row['clump_id'],
                    $row['recipe_id'],
                    $row['recipe_name'],
                    $row['std_mid'],
                    $row['is_correcting'],
                    $row['action_left'],
                    $row['sensor_left'],
                    $row['action_right'],
                    $row['sensor_right'],
                    $row['dt_client']
                ]);
            }
            fclose($file);
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan_RTC') . '_' . date('Y-m-d_Hs') . '.csv';

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);

    }
}
