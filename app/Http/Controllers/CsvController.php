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

                $action_left = '';
                switch ($metric->action_left) {
                    case 'thin':
                        $action_left = __('Tipis');
                        break;
                    case 'thick':
                    $action_left = __('Tebal');
                    break;
                }
    
                $action_right = '';
                switch ($metric->action_right) {
                    case 'thin':
                        $action_right = __('Tipis');
                        break;
                    case 'thick':
                        $action_right = __('Tebal');
                        break;
                }

                $row['line']            = $metric->ins_rtc_clump->ins_rtc_device->line ?? '';
                $row['clump_id']        = $metric->ins_rtc_clump_id ?? '';
                $row['recipe_id']       = $metric->ins_rtc_clump->ins_rtc_recipe->id ?? '' ;
                $row['recipe_name']     = $metric->ins_rtc_clump->ins_rtc_recipe->name ?? '';
                $row['std_mid']         = $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '';
                $row['is_correcting']   = $metric->is_correcting ? 'ON' : 'OFF';
                $row['action_left']     = $action_left;
                $row['sensor_left']     = $metric->sensor_left ?? '';
                $row['action_right']    = $action_right;
                $row['sensor_right']    = $metric->sensor_right ?? '';
                $row['dt_client']       = $metric->dt_client;      
            }
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan_RTC') . '_' . date('Y-m-d_Hs') . '.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);

    }
}
