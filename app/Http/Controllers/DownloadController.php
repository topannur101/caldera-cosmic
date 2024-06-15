<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\InsLdcHide;
use App\Models\InsRtcMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DownloadController extends Controller
{   
  
    public function insRtcMetrics(Request $request)
    {
        if (!Auth::user()) {
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
    
        $fileName = __('Wawasan') . ' ' . __('RTC') . '_'. __('Mentah') . '_' . date('Y-m-d_Hs') . '.xlsx';
    
        $callback = function() use ($metrics, $headers) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
    
            // Add header row
            foreach ($headers as $index => $header) {
                $column = chr(65 + $index); // Convert index to column letter
                $sheet->setCellValue($column . '1', $header);
            }
    
            // Add data rows
            $rowIndex = 2;
            foreach ($metrics as $metric) {
                $sheet->setCellValue('A' . $rowIndex, $metric->ins_rtc_clump->ins_rtc_device->line ?? '');
                $sheet->setCellValue('B' . $rowIndex, $metric->ins_rtc_clump_id ?? '');
                $sheet->setCellValue('C' . $rowIndex, $metric->ins_rtc_clump->ins_rtc_recipe->id ?? '');
                $sheet->setCellValue('D' . $rowIndex, $metric->ins_rtc_clump->ins_rtc_recipe->name ?? '');
                $sheet->setCellValue('E' . $rowIndex, $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '');
                $sheet->setCellValue('F' . $rowIndex, $metric->is_correcting ? 'ON' : 'OFF');
                $sheet->setCellValue('G' . $rowIndex, $metric->action_left == 'thin' ? __('Tipis') : ($metric->action_left == 'thick' ? __('Tebal') : ''));
                $sheet->setCellValue('H' . $rowIndex, $metric->push_left ?? '');
                $sheet->setCellValue('I' . $rowIndex, $metric->sensor_left ?? '');
                $sheet->setCellValue('J' . $rowIndex, $metric->action_right == 'thin' ? __('Tipis') : ($metric->action_right == 'thick' ? __('Tebal') : ''));
                $sheet->setCellValue('K' . $rowIndex, $metric->push_right ?? '');
                $sheet->setCellValue('L' . $rowIndex, $metric->sensor_right ?? '');
                $sheet->setCellValue('M' . $rowIndex, $metric->dt_client);
                $rowIndex++;
            }
    
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };
    
        return response()->stream($callback, 200, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }
    
    
   
    public function insRtcClumps(Request $request)
    {
        if (!Auth::user()) {
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
            ->selectRaw('MIN(ins_rtc_metrics.dt_client) as start_time')
            ->selectRaw('MAX(ins_rtc_metrics.dt_client) as end_time')
            ->selectRaw('TIMESTAMPDIFF(SECOND, MIN(ins_rtc_metrics.dt_client), MAX(ins_rtc_metrics.dt_client)) as duration_seconds')
            ->selectRaw('CASE WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 6 AND 13 THEN "1" WHEN HOUR(MIN(ins_rtc_metrics.dt_client)) BETWEEN 14 AND 21 THEN "2" ELSE "3" END AS shift')
            ->selectRaw('ROUND(AVG(ins_rtc_metrics.sensor_left), 2) as avg_left')
            ->selectRaw('ROUND(AVG(ins_rtc_metrics.sensor_right), 2) as avg_right')
            ->selectRaw('ROUND(STDDEV(ins_rtc_metrics.sensor_left), 2) as sd_left')
            ->selectRaw('ROUND(STDDEV(ins_rtc_metrics.sensor_right), 2) as sd_right')
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
    
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        // Add header row
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index); // Convert index to column letter
            $sheet->setCellValue($column . '1', $header);
        }
    
        // Add data rows
        $rowIndex = 2;
        foreach ($clumps as $clump) {
            $sheet->setCellValue('A' . $rowIndex, $clump->id);
            $sheet->setCellValue('B' . $rowIndex, $clump->line);
            $sheet->setCellValue('C' . $rowIndex, $clump->shift);
            $sheet->setCellValue('D' . $rowIndex, $clump->recipe_id);
            $sheet->setCellValue('E' . $rowIndex, $clump->recipe_name);
            $sheet->setCellValue('F' . $rowIndex, $clump->std_mid);
            $sheet->setCellValue('G' . $rowIndex, $clump->correcting_rate > 0.8 ? 'ON' : 'OFF');
            $sheet->setCellValue('H' . $rowIndex, $clump->avg_left);
            $sheet->setCellValue('I' . $rowIndex, $clump->avg_right);
            $sheet->setCellValue('J' . $rowIndex, $clump->sd_left);
            $sheet->setCellValue('K' . $rowIndex, $clump->sd_right);
            $sheet->setCellValue('L' . $rowIndex, '');
            $sheet->setCellValue('M' . $rowIndex, '');
            $sheet->setCellValue('N' . $rowIndex, $clump->duration_seconds);
            $sheet->setCellValue('O' . $rowIndex, $clump->start_time);
            $rowIndex++;
        }
    
        $fileName = __('Wawasan') . ' ' . __('RTC') . '_'. __('Gilingan') . '_' . date('Y-m-d_Hs') . '.xlsx';
    
        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
        ]);
    }
    
 
    public function insLdcHides(Request $request)
    {
        $start = Carbon::parse($request['start_at']);
        $end = Carbon::parse($request['end_at'])->addDay();
    
        $hidesQuery = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id');
    
        if (!$request->is_workdate) {
            $hidesQuery->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $hidesQuery->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }
    
        switch ($request->ftype) {
            case 'code':
                $hidesQuery->where('ins_ldc_hides.code', 'LIKE', '%' . $request['fquery'] . '%');
                break;
            case 'style':
                $hidesQuery->where('ins_ldc_groups.style', 'LIKE', '%' . $request['fquery'] . '%');
                break;
            case 'line':
                $hidesQuery->where('ins_ldc_groups.line', 'LIKE', '%' . $request['fquery'] . '%');
                break;
            case 'material':
                $hidesQuery->where('ins_ldc_groups.material', 'LIKE', '%' . $request['fquery'] . '%');
                break;
            case 'emp_id':
                $hidesQuery->where('users.emp_id', 'LIKE', '%' . $request['fquery'] . '%');
                break;
            
            default:
                $hidesQuery->where(function (Builder $query) use($request) {
                    $query
                        ->orWhere('ins_ldc_hides.code', 'LIKE', '%' . $request['fquery'] . '%')
                        ->orWhere('ins_ldc_groups.style', 'LIKE', '%' . $request['fquery'] . '%')
                        ->orWhere('ins_ldc_groups.line', 'LIKE', '%' . $request['fquery'] . '%')
                        ->orWhere('ins_ldc_groups.material', 'LIKE', '%' . $request['fquery'] . '%')
                        ->orWhere('users.emp_id', 'LIKE', '%' . $request['fquery'] . '%');
                });
                break;
        }
    
        if (!$request->is_workdate) {
            $hidesQuery->orderBy('ins_ldc_hides.updated_at', 'DESC');
        } else {
            $hidesQuery->orderBy('ins_ldc_groups.workdate', 'DESC');
        }
    
        $hides = $hidesQuery->get();
    
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
    
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        // Add header row
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index); // Convert index to column letter
            $sheet->setCellValue($column . '1', $header);
        }
    
        // Add data rows
        $rowIndex = 2;
        foreach ($hides as $hide) {
            $sheet->setCellValue('A' . $rowIndex, $hide->updated_at);
            $sheet->setCellValue('B' . $rowIndex, $hide->code);
            $sheet->setCellValue('C' . $rowIndex, $hide->area_vn);
            $sheet->setCellValue('D' . $rowIndex, $hide->area_ab);
            $sheet->setCellValue('E' . $rowIndex, $hide->area_qt);
            $sheet->setCellValue('F' . $rowIndex, $hide->grade);
            $sheet->setCellValue('G' . $rowIndex, $hide->shift);
            $sheet->setCellValue('H' . $rowIndex, $hide->ins_ldc_group->workdate);
            $sheet->setCellValue('I' . $rowIndex, $hide->ins_ldc_group->style);
            $sheet->setCellValue('J' . $rowIndex, $hide->ins_ldc_group->line);
            $sheet->setCellValue('K' . $rowIndex, $hide->ins_ldc_group->material ?? '');
            $sheet->setCellValue('L' . $rowIndex, $hide->user->emp_id);
            $sheet->setCellValue('M' . $rowIndex, $hide->user->name);
            $rowIndex++;
        }
    
        $fileName = __('Wawasan') . ' ' . __('LDC') . '_'. __('Kulit') . '_' . date('Y-m-d_His') . '.xlsx';
    
        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
        ]);
    }
    
    
}
