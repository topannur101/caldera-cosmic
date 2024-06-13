<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\InsLdcHide;
use App\Models\InsRtcMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

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
            __('Koreksi oto.'),
            __('Kiri tindakan'), 
            __('Kiri tekan'), 
            __('Kiri terukur'), 
            __('Kanan tindakan'),
            __('Kanan tekan'), 
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
                $row['action_left']     = $metric->action_left == 'thin' ? __('Tipis') : ($metric->action_left ==  'thick' ? __('Tebal') : '');
                $row['push_left']       = $metric->push_left ?? '';
                $row['sensor_left']     = $metric->sensor_left ?? '';
                $row['action_right']     = $metric->action_right == 'thin' ? __('Tipis') : ($metric->action_right ==  'thick' ? __('Tebal') : '');
                $row['push_right']       = $metric->push_right ?? '';
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
                    $row['push_left'],
                    $row['sensor_left'],
                    $row['action_right'],
                    $row['push_right'],
                    $row['sensor_right'],
                    $row['dt_client']
                ]);
            }
            fclose($file);
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan') . ' ' . __('RTC') . '_'. __('Mentah') . '_' . date('Y-m-d_Hs') . '.csv';

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);

    }

    public function insRtcClumps(Request $request)
    {
        if (! Auth::user() ) {
            abort(403);
        }
        
        $start  = Carbon::parse($request['start_at'])->addHours(6);
        $end    = $start->copy()->addHours(24);
        $fline  = $request['fline'];

        $clumps = DB::table('ins_rtc_metrics')
            ->join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
            ->join('ins_rtc_devices', 'ins_rtc_devices.id', '=', 'ins_rtc_clumps.ins_rtc_device_id')
            ->join('ins_rtc_recipes', 'ins_rtc_recipes.id', '=', 'ins_rtc_clumps.ins_rtc_recipe_id')
            ->select('ins_rtc_devices.line', 'ins_rtc_clumps.id as id', 'ins_rtc_recipes.name as recipe_name', 'ins_rtc_recipes.id as recipe_id', 'ins_rtc_recipes.std_mid as std_mid')
            // Waktu mulai batch
            ->selectRaw('MIN(ins_rtc_metrics.dt_client) as start_time')
            // Waktu akhir batch
            ->selectRaw('MAX(ins_rtc_metrics.dt_client) as end_time')
            // Hitung durasi batch
            ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration_seconds')
            // Assign Shift 1, 2, 3
            ->selectRaw('CASE WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 6 AND 13 THEN "1" WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 14 AND 21 THEN "2" ELSE "3" END AS shift')

            // Calculate mean (avg_left and avg_right)
            ->selectRaw('ROUND(AVG(ins_rtc_metrics.sensor_left), 2) as avg_left')
            ->selectRaw('ROUND(AVG(ins_rtc_metrics.sensor_right), 2) as avg_right')

            // Calculate standard deviation number (dn_left and dn_right)
            ->selectRaw('ROUND(STDDEV(ins_rtc_metrics.sensor_left), 2) as sd_left')
            ->selectRaw('ROUND(STDDEV(ins_rtc_metrics.sensor_right), 2) as sd_right')

            // Untuk menghitung presentase trigger nyala brp kali dalam %
            ->selectRaw('ROUND(SUM(CASE WHEN ins_rtc_metrics.is_correcting = 1 THEN 1 ELSE 0 END) / COUNT(*), 2) as correcting_rate')
            ->where('ins_rtc_metrics.sensor_left', '>', 0)
            ->where('ins_rtc_metrics.sensor_right', '>', 0)
            ->whereBetween('ins_rtc_metrics.dt_client', [$start, $end]);

        if ($fline) {
            $clumps->where('ins_rtc_devices.line', $fline);
        }

        $clumps->groupBy('ins_rtc_clumps.id', 'ins_rtc_devices.line', 'ins_rtc_recipes.name', 'ins_rtc_recipes.id', 'ins_rtc_recipes.std_mid')->orderBy('end_time', 'desc');

        $clumps = $clumps->get();

        $headers = [
            __('ID Gilingan'), 
            __('Line'),
            __('Shift'),
            __('ID Resep'),
            __('Nama resep'),
            __('Standar tengah'),
            __('Koreksi oto.'),
            __('AVG ki'), 
            __('AVG ka'), 
            __('SD ki'), 
            __('SD ka'), 
            __('MAE ki'), 
            __('MAE ka'), 
            __('Durasi'),
            __('Mulai'),
        ];

        $callback = function() use($clumps, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach($clumps as $clump) {

                $row['clump_id']            = $clump->id;
                $row['line']                = $clump->line;
                $row['shift']               = $clump->shift;
                $row['recipe_id']           = $clump->recipe_id;
                $row['recipe_name']         = $clump->recipe_name;
                $row['std_mid']             = $clump->std_mid;
                $row['is_correcting']       = $clump->correcting_rate > 0.8 ? 'ON' : 'OFF';
                $row['avg_left']            = $clump->avg_left;
                $row['avg_right']           = $clump->avg_right;
                $row['sd_left']             = $clump->sd_left;
                $row['sd_right']            = $clump->sd_right;
                $row['mae_left']            = '';
                $row['mae_right']           = '';
                $row['duration_seconds']    = $clump->duration_seconds;
                $row['start_time']          = $clump->start_time; 

                fputcsv($file, [
                    $row['clump_id'],
                    $row['line'],
                    $row['shift'],
                    $row['recipe_id'],
                    $row['recipe_name'],
                    $row['std_mid'],
                    $row['is_correcting'],
                    $row['avg_left'],
                    $row['avg_right'],
                    $row['sd_left'],
                    $row['sd_right'],
                    $row['mae_left'],
                    $row['mae_right'],
                    $row['duration_seconds'],
                    $row['start_time']
                ]);
            }
            fclose($file);
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan') . ' ' . __('RTC') . '_'. __('Gilingan') . '_' . date('Y-m-d_Hs') . '.csv';

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    public function insLdcHides(Request $request)
    {
        if (!Auth::user()) {
            abort(403);
        }

        $start = Carbon::parse($request['start_at']);
        $end = Carbon::parse($request['end_at'])->addDay();

        $hides = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id');

        if (!$request->is_workdate) {
            $hides->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $hides->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        switch ($request->ftype) {
            case 'code':
                $hides->where('ins_ldc_hides.code', 'LIKE', '%' . $request->fquery . '%');
                break;
            case 'style':
                $hides->where('ins_ldc_groups.style', 'LIKE', '%' . $request->fquery . '%');
                break;
            case 'emp_id':
                $hides->where('users.emp_id', 'LIKE', '%' . $request->fquery . '%');
                break;
            default:
                $hides->where(function (Builder $query) use ($request) {
                    $query
                        ->orWhere('ins_ldc_hides.code', 'LIKE', '%' . $request->fquery . '%')
                        ->orWhere('ins_ldc_groups.style', 'LIKE', '%' . $request->fquery . '%')
                        ->orWhere('users.emp_id', 'LIKE', '%' . $request->fquery . '%');
                });
                break;
        }

        if (!$request->is_workdate) {
            $hides->orderBy('ins_ldc_hides.updated_at', 'DESC');
        } else {
            $hides->orderBy('ins_ldc_groups.workdate', 'DESC');
        }

        $hides = $hides->get();

        $headers = [
            __('Diperbarui'), 
            __('Kode'),
            __('VN'),
            __('AB'),
            __('QT'),
            __('G'),
            __('S'),
            __('WO'),
            __('Style'),
            __('Line'),
            __('Material'),
            __('IDK'),
            __('Nama'),
        ];

        $callback = function() use($hides, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach($hides as $hide) {
                $row['updated_at'] = $hide->updated_at;
                $row['code'] = $hide->code;
                $row['area_vn'] = $hide->area_vn;
                $row['area_ab'] = $hide->area_ab;
                $row['area_qt'] = $hide->area_qt;
                $row['grade'] = $hide->grade;
                $row['shift'] = $hide->shift;
                $row['workdate'] = $hide->ins_ldc_group->workdate;
                $row['style'] = $hide->ins_ldc_group->style;
                $row['line'] = $hide->ins_ldc_group->line;
                $row['material'] = $hide->ins_ldc_group->material ?? '';
                $row['emp_id'] = $hide->user->emp_id;
                $row['name'] = $hide->user->name;

                fputcsv($file, [
                    $row['updated_at'],
                    $row['code'],
                    $row['area_vn'],
                    $row['area_ab'],
                    $row['area_qt'],
                    $row['grade'],
                    $row['shift'],
                    $row['workdate'],
                    $row['style'],
                    $row['line'],
                    $row['material'],
                    $row['emp_id'],
                    $row['name']
                ]);
            }
            fclose($file);
        };

        $fileName = __('Wawasan') . ' ' . __('LDC') . '_'. __('Kulit') . '_' . date('Y-m-d_His') . '.csv';

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv; charset=utf-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }
}
