<?php

namespace App\Http\Controllers;

use App\InvQuery;
use App\Models\InsLdcHide;
use App\Models\InsRtcMetric;
use App\Models\InsStcDLog;
use App\Models\InvArea;
use App\Models\InvCirc;
use App\Models\InvItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DownloadController extends Controller
{
    public function insStcDLogs(Request $request, $token)
    {
        // Validate the token
        if ($token !== session()->get('ins_stc_d_logs_token')) {
            abort(403);
        }

        // Clear the token
        session()->forget('ins_stc_d_logs_token');

        $id = session()->get('ins_stc_d_logs_id');

        $ins_stc_d_logs_query = InsStcDLog::where('ins_stc_d_sum_id', $id)
            ->orderBy('taken_at');

        return response()->streamDownload(function () use ($ins_stc_d_logs_query) {
            // Open output stream
            $handle = fopen('php://output', 'w');

            // Add CSV header row
            fputcsv($handle, [
                'd_sum_id', 'taken_at', 'temp',
            ]);

            // Stream each record to avoid loading all records into memory at once
            $ins_stc_d_logs_query->chunk(100, function ($d_logs) use ($handle) {
                foreach ($d_logs as $d_log) {

                    fputcsv($handle, [
                        $d_log->ins_stc_d_sum_id ?? '',
                        $d_log->taken_at ?? '',
                        $d_log->temp ?? '',
                    ]);
                }

                // Flush the output buffer to send data to the browser
                ob_flush();
                flush();
            });

            // Close the output stream
            fclose($handle);
        }, 'ins_stc_d_logs.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function invCircs(Request $request, $token)
    {
        // Validate the token
        if ($token !== session()->get('inv_circs_token')) {
            abort(403);
        }

        // Clear the token
        session()->forget('inv_circs_token');

        // Check if stock_id is present in the request
        if ($request->has('stock_id')) {
            $stock_id = $request['stock_id'];

            $inv_circs_query = InvCirc::with([
                'inv_stock.inv_item',
                'inv_stock.inv_item.inv_loc',
                'inv_stock.inv_item.inv_tags',
                'inv_stock.inv_item.inv_area',
                'inv_stock',
                'inv_stock.inv_curr',
                'user',
                'eval_user',
            ])
                ->where('inv_stock_id', $stock_id)
                ->limit(500);

        } else {
            // Get circs parameters from session
            $inv_circs_params = session('inv_circs_params', []);

            // Extract parameters
            $q = $inv_circs_params['q'] ?? '';
            $sort = $inv_circs_params['sort'] ?? '';
            $area_ids = $inv_circs_params['area_ids'] ?? [];
            $circ_eval_status = $inv_circs_params['circ_eval_status'] ?? [];
            $circ_types = $inv_circs_params['circ_types'] ?? [];
            $date_fr = $inv_circs_params['date_fr'] ?? '';
            $date_to = $inv_circs_params['date_to'] ?? '';
            $user_id = $inv_circs_params['user_id'] ?? 0;
            $remarks = $inv_circs_params['remarks'] ?? ['', ''];

            $inv_circs_query = InvCirc::with([
                'inv_stock.inv_item',
                'inv_stock.inv_item.inv_loc',
                'inv_stock.inv_item.inv_tags',
                'inv_stock.inv_item.inv_area',
                'inv_stock',
                'inv_stock.inv_curr',
                'user',
                'eval_user',
            ])
                ->whereHas('inv_item', function ($query) use ($q, $area_ids) {
                    $query->where(function ($subQuery) use ($q) {
                        $subQuery->where('name', 'like', "%$q%")
                            ->orWhere('code', 'like', "%$q%")
                            ->orWhere('desc', 'like', "%$q%");
                    })->whereIn('inv_area_id', $area_ids);
                })
                ->whereIn('eval_status', $circ_eval_status)
                ->whereIn('type', $circ_types);

            if ($date_fr && $date_to) {
                $fr = Carbon::parse($date_fr)->startOfDay();
                $to = Carbon::parse($date_to)->endOfDay();
                $inv_circs_query->whereBetween('updated_at', [$fr, $to]);
            }

            if ($user_id) {
                $inv_circs_query->where('user_id', $user_id);
            }

            if ($remarks[0]) {
                $inv_circs_query->where('remarks', 'like', "%{$remarks[0]}%");
            }

            if ($remarks[1]) {
                $inv_circs_query->where('eval_remarks', 'like', "%{$remarks[0]}%");
            }

            switch ($sort) {
                case 'updated':
                    $inv_circs_query->orderByDesc('updated_at');
                    break;
                case 'qty_low':
                    $inv_circs_query->orderBy('qty_relative');
                    break;
                case 'qty_high':
                    $inv_circs_query->orderByDesc('qty_relative');
                    break;
                case 'amount_low':
                    $inv_circs_query->orderBy('amount');
                    break;
                case 'amount_high':
                    $inv_circs_query->orderByDesc('amount');
                    break;
            }
        }

        return response()->streamDownload(function () use ($inv_circs_query) {
            // Open output stream
            $handle = fopen('php://output', 'w');

            // Add CSV header row
            fputcsv($handle, [
                'item_id', 'item_name', 'item_desc', 'item_code', 'item_location',
                'item_tag_0', 'item_tag_1', 'item_tag_2', 'item_area',
                'stock_id', 'stock_unit_price', 'stock_curr', 'stock_uom',
                'circ_type', 'circ_qty_relative', 'circ_unit_price', 'circ_amount',
                'circ_user_emp_id', 'circ_user_name', 'circ_remarks',
                'circ_eval_user_emp_id', 'circ_eval_user_name', 'circ_eval_remarks',
                'circ_eval_status', 'circ_is_delegated', 'circ_created_at', 'circ_updated_at',
            ]);

            // Stream each record to avoid loading all records into memory at once
            $inv_circs_query->chunk(100, function ($circs) use ($handle) {
                foreach ($circs as $circ) {
                    $location = '';
                    if ($circ->inv_stock->inv_item->inv_loc) {
                        $location = $circ->inv_stock->inv_item->inv_loc->parent.'-'.$circ->inv_stock->inv_item->inv_loc->bin;
                    }
                    $tags = $circ->inv_stock->inv_item->inv_tags->pluck('name')->toArray();

                    fputcsv($handle, [
                        $circ->inv_stock->inv_item->id ?? '',
                        $circ->inv_stock->inv_item->name ?? '',
                        $circ->inv_stock->inv_item->desc ?? '',
                        $circ->inv_stock->inv_item->code ?? '',
                        $location,
                        $tags[0] ?? '',
                        $tags[1] ?? '',
                        $tags[2] ?? '',
                        $circ->inv_stock->inv_item->inv_area->name ?? '',
                        $circ->inv_stock->id ?? '',
                        $circ->inv_stock->unit_price ?? '',
                        $circ->inv_stock->inv_curr->name ?? '',
                        $circ->inv_stock->uom ?? '',
                        $circ->type,
                        $circ->qty_relative,
                        $circ->unit_price,
                        $circ->amount,
                        $circ->user->emp_id,
                        $circ->user->name,
                        $circ->remarks,
                        $circ->eval_user?->emp_id,
                        $circ->eval_user?->name,
                        $circ->eval_remarks,
                        $circ->eval_status,
                        $circ->is_delegated,
                        $circ->created_at,
                        $circ->updated_at,
                    ]);
                }

                // Flush the output buffer to send data to the browser
                ob_flush();
                flush();
            });

            // Close the output stream
            fclose($handle);
        }, 'inventory_circs.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function invItems(Request $request, $token)
    {
        // Validate the token
        if ($token !== session()->get('inv_items_token')) {
            abort(403);
        }

        // Clear the token
        session()->forget('inv_items_token');

        // Get search parameters from session
        $sessionParams = session('inv_items_params', []);

        return response()->streamDownload(function () use ($sessionParams) {
            // Open output stream
            $handle = fopen('php://output', 'w');

            // Add CSV header row
            fputcsv($handle, [
                'id', 'name', 'desc', 'code', 'location',
                'tag_0', 'tag_1', 'tag_2',
                'curr_0', 'up_0', 'uom_0', 'qty_0', 'amt_0', 'qmin_0', 'qmax_0',
                'curr_1', 'up_1', 'uom_1', 'qty_1', 'amt_1', 'qmin_1', 'qmax_1',
                'curr_2', 'up_2', 'uom_2', 'qty_2', 'amt_2', 'qmin_2', 'qmax_2',
                'created_at', 'updated_at', 'last_withdrawal', 'last_deposit',
                'is_active', 'area_name',
            ]);

            $query = InvQuery::fromSessionParams($sessionParams, 'items')->buildForExport();

            // Stream each record to avoid loading all records into memory at once
            $query->chunk(100, function ($items) use ($handle) {
                foreach ($items as $item) {
                    $location = '';
                    if ($item->inv_loc) {
                        $location = $item->inv_loc->parent.'-'.$item->inv_loc->bin;
                    }

                    $tag_0 = '';
                    $tag_1 = '';
                    $tag_2 = '';
                    $i = 0;

                    $tags = $item->inv_tags()->take(3)->get();
                    foreach ($tags as $tag) {
                        $tagVar = 'tag_'.$i;
                        $$tagVar = $tag->name;
                        $i++;
                    }

                    $curr_0 = '';
                    $up_0 = '';
                    $uom_0 = '';
                    $qty_0 = '';
                    $amt_0 = '';
                    $qmin_0 = '';
                    $qmax_0 = '';

                    $curr_1 = '';
                    $up_1 = '';
                    $uom_1 = '';
                    $qty_1 = '';
                    $amt_1 = '';
                    $qmin_1 = '';
                    $qmax_1 = '';

                    $curr_2 = '';
                    $up_2 = '';
                    $uom_2 = '';
                    $qty_2 = '';
                    $amt_2 = '';
                    $qmin_2 = '';
                    $qmax_2 = '';

                    $i = 0;

                    $stocks = $item->inv_stocks()->where('is_active', true)->take(3)->get();
                    // dd($stocks);

                    foreach ($stocks as $stock) {

                        $currVar = 'curr_'.$i;
                        $$currVar = $stock->inv_curr->name;

                        $upVar = 'up_'.$i;
                        $$upVar = $stock->unit_price;

                        $uomVar = 'uom_'.$i;
                        $$uomVar = $stock->uom;

                        $qtyVar = 'qty_'.$i;
                        $$qtyVar = $stock->qty;

                        $amtVar = 'amt_'.$i;
                        $$amtVar = $stock->amount_main;

                        $qminVar = 'qmin_'.$i;
                        $$qminVar = $stock->qty_min;

                        $qmaxVar = 'qmax_'.$i;
                        $$qmaxVar = $stock->qty_max;

                        $i++;
                    }

                    fputcsv($handle, [
                        $item->id ?? '',
                        $item->name ?? '',
                        $item->desc ?? '',
                        $item->code ?? '',
                        $location,
                        $tag_0,
                        $tag_1,
                        $tag_2,

                        $curr_0,
                        $up_0,
                        $uom_0,
                        $qty_0,
                        $amt_0,
                        $qmin_0,
                        $qmax_0,

                        $curr_1,
                        $up_1,
                        $uom_1,
                        $qty_1,
                        $amt_1,
                        $qmin_1,
                        $qmax_1,

                        $curr_2,
                        $up_2,
                        $uom_2,
                        $qty_2,
                        $amt_2,
                        $qmin_2,
                        $qmax_2,

                        $item->created_at,
                        $item->updated_at,
                        $item->last_withdrawal,
                        $item->last_deposit,
                        $item->is_active,
                        $item->inv_area->name,

                    ]);
                }

                // // Flush the output buffer to send data to the browser
                ob_flush();
                flush();
            });

            // Close the output stream
            fclose($handle);
        }, 'inventory_items.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function invItemsBackup(Request $request, $token)
    {
        // Validate the token
        if ($token !== session()->get('inv_items_token')) {
            abort(403);
        }

        // Clear the token
        session()->forget('inv_items_backup_token');

        $area_id = $request['area_id'];
        $inv_item = new InvItem;
        $inv_item->inv_area_id = $area_id;

        Gate::authorize('download', $inv_item);

        $area = InvArea::find($area_id);

        return response()->streamDownload(function () use ($area_id) {
            // Open output stream
            $handle = fopen('php://output', 'w');

            // Add CSV header row
            fputcsv($handle, [
                'id', 'name', 'desc', 'code', 'location',
                'tag_0', 'tag_1', 'tag_2',
                'curr_0', 'up_0', 'uom_0', 'qty_0', 'amount_main_0', 'qty_min_0', 'qty_max_0',
                'curr_1', 'up_1', 'uom_1', 'qty_1', 'amount_main_1', 'qty_min_1', 'qty_max_1',
                'curr_2', 'up_2', 'uom_2', 'qty_2', 'amount_main_2', 'qty_min_2', 'qty_max_2',
            ]);

            // Build the same query as in the Livewire component
            $query = InvItem::with([
                'inv_loc',
                'inv_tags',
                'inv_stocks',
                'inv_stocks.inv_curr',
            ])->where('inv_area_id', $area_id);

            // Stream each record to avoid loading all records into memory at once
            $query->chunk(100, function ($items) use ($handle) {
                foreach ($items as $item) {
                    $location = '';
                    if ($item->inv_loc) {
                        $location = $item->inv_loc->parent.'-'.$item->inv_loc->bin;
                    }

                    $tag_0 = '';
                    $tag_1 = '';
                    $tag_2 = '';
                    $i = 0;

                    $tags = $item->inv_tags()->take(3)->get();
                    foreach ($tags as $tag) {
                        $tagVar = 'tag_'.$i;
                        $$tagVar = $tag->name;
                        $i++;
                    }

                    $curr_0 = '';
                    $up_0 = '';
                    $uom_0 = '';
                    $curr_1 = '';
                    $up_1 = '';
                    $uom_1 = '';
                    $curr_2 = '';
                    $up_2 = '';
                    $uom_2 = '';
                    $i = 0;

                    $stocks = $item->inv_stocks()->where('is_active', true)->take(3)->get();
                    foreach ($stocks as $stock) {
                        $currVar = 'curr_'.$i;
                        $$currVar = $stock->inv_curr->name;

                        $upVar = 'up_'.$i;
                        $$upVar = $stock->unit_price;

                        $uomVar = 'uom_'.$i;
                        $$uomVar = $stock->uom;
                    }

                    fputcsv($handle, [
                        $item->id ?? '',
                        $item->name ?? '',
                        $item->desc ?? '',
                        $item->code ?? '',
                        $location,
                        $tag_0,
                        $tag_1,
                        $tag_2,
                        $curr_0,
                        $up_0,
                        $uom_0,
                        $curr_1,
                        $up_1,
                        $uom_1,
                        $curr_2,
                        $up_2,
                        $uom_2,
                    ]);
                }

                // // Flush the output buffer to send data to the browser
                ob_flush();
                flush();
            });

            // Close the output stream
            fclose($handle);
        }, 'inventory_items_'.$area->name.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function invStocks(Request $request, $token)
    {
        // Validate the token
        if ($token !== session()->get('inv_stocks_token')) {
            abort(403);
        }

        // Clear the token
        session()->forget('inv_stocks_token');

        // Get search parameters from session
        $sessionParams = session('inv_items_params', []);

        return response()->streamDownload(function () use ($sessionParams) {
            // Open output stream
            $handle = fopen('php://output', 'w');

            // Add CSV header row
            fputcsv($handle, [
                'stock_id', 'stock_unit_price', 'stock_curr', 'stock_uom', 'stock_qty',
                'stock_updated_at', 'stock_amount_main', 'stock_qty_min', 'stock_qty_max',
                'item_id', 'item_name', 'item_desc', 'item_code', 'item_location',
                'item_tag_0', 'item_tag_1', 'item_tag_2',
                'item_created_at', 'item_updated_at',
                'item_last_deposit', 'item_last_withdrawal',
                'item_area', 'item_photo',
            ]);

            // Build query using InvStockQuery
            $query = InvQuery::fromSessionParams($sessionParams, 'stocks')->buildForExport();

            // Stream each record to avoid loading all records into memory at once
            $query->chunk(100, function ($stocks) use ($handle) {
                foreach ($stocks as $stock) {
                    $location = '';
                    if ($stock->inv_item->inv_loc) {
                        $location = $stock->inv_item->inv_loc->parent.'-'.$stock->inv_item->inv_loc->bin;
                    }

                    $tags = $stock->inv_item->inv_tags->pluck('name')->toArray();

                    fputcsv($handle, [
                        $stock->id ?? '',
                        $stock->unit_price ?? '',
                        $stock->inv_curr->name ?? '',
                        $stock->uom ?? '',
                        $stock->qty ?? '',
                        $stock->updated_at ?? '',
                        $stock->amount_main ?? '',
                        $stock->qty_min ?? '',
                        $stock->qty_max ?? '',

                        $stock->inv_item->id ?? '',
                        $stock->inv_item->name ?? '',
                        $stock->inv_item->desc ?? '',
                        $stock->inv_item->code ?? '',

                        $location,
                        $tags[0] ?? '',
                        $tags[1] ?? '',
                        $tags[2] ?? '',

                        $stock->inv_item->created_at ?? '',
                        $stock->inv_item->updated_at ?? '',
                        $stock->inv_item->last_deposit ?? '',
                        $stock->inv_item->last_withdrawal ?? '',
                        $stock->inv_item->inv_area->name ?? '',
                        $stock->inv_item->photo ?? '',
                    ]);
                }

                // Flush the output buffer to send data to the browser
                ob_flush();
                flush();
            });

            // Close the output stream
            fclose($handle);
        }, 'inventory_stocks.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function insRtcMetrics(Request $request)
    {
        if (! Auth::user()) {
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
            __('Std Min'),
            __('Std Teng'),
            __('Std Maks'),
            __('Koreksi oto.'),
            __('Kiri tindakan'),
            __('Kiri tekan'),
            __('Kiri terukur'),
            __('Kanan tindakan'),
            __('Kanan tekan'),
            __('Kanan terukur'),
            __('Waktu'),
        ];

        $callback = function () use ($metrics, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($metrics as $metric) {

                $row['line'] = $metric->ins_rtc_clump->ins_rtc_device->line ?? '';
                $row['clump_id'] = $metric->ins_rtc_clump_id ?? '';
                $row['recipe_id'] = $metric->ins_rtc_clump->ins_rtc_recipe->id ?? '';
                $row['recipe_name'] = $metric->ins_rtc_clump->ins_rtc_recipe->name ?? '';
                $row['std_min'] = $metric->ins_rtc_clump->ins_rtc_recipe->std_min ?? '';
                $row['std_mid'] = $metric->ins_rtc_clump->ins_rtc_recipe->std_mid ?? '';
                $row['std_max'] = $metric->ins_rtc_clump->ins_rtc_recipe->std_max ?? '';
                $row['is_correcting'] = $metric->is_correcting ? 'ON' : 'OFF';
                $row['action_left'] = $metric->action_left == 'thin' ? __('Tipis') : ($metric->action_left == 'thick' ? __('Tebal') : '');
                $row['push_left'] = $metric->push_left ?? '';
                $row['sensor_left'] = $metric->sensor_left ?? '';
                $row['action_right'] = $metric->action_right == 'thin' ? __('Tipis') : ($metric->action_right == 'thick' ? __('Tebal') : '');
                $row['push_right'] = $metric->push_right ?? '';
                $row['sensor_right'] = $metric->sensor_right ?? '';
                $row['dt_client'] = $metric->dt_client;

                fputcsv($file, [
                    $row['line'],
                    $row['clump_id'],
                    $row['recipe_id'],
                    $row['recipe_name'],
                    $row['std_min'],
                    $row['std_mid'],
                    $row['std_max'],
                    $row['is_correcting'],
                    $row['action_left'],
                    $row['push_left'],
                    $row['sensor_left'],
                    $row['action_right'],
                    $row['push_right'],
                    $row['sensor_right'],
                    $row['dt_client'],
                ]);
            }
            fclose($file);
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan').' '.__('RTC').'_'.__('Mentah').'_'.date('Y-m-d_Hs').'.csv';

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);

    }

    public function insRtcClumps(Request $request)
    {
        if (! Auth::user()) {
            abort(403);
        }

        $start = Carbon::parse($request['start_at'])->addHours(6);
        $end = Carbon::parse($request['end_at'])->addDay();
        $fline = $request['fline'];

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

            // Calculate MAE
            ->selectRaw(
                'ROUND(
                CASE 
                    WHEN SUM(ins_rtc_metrics.sensor_left) = 0 THEN 0
                    ELSE AVG(ins_rtc_metrics.sensor_left) - AVG(ins_rtc_recipes.std_mid)
                END, 2) as mae_left',
            )
            ->selectRaw(
                'ROUND(
                CASE
                    WHEN SUM(ins_rtc_metrics.sensor_right) = 0 THEN 0
                    ELSE AVG(ins_rtc_metrics.sensor_right) - AVG(ins_rtc_recipes.std_mid)
                END, 2) as mae_right',
            )

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

        $callback = function () use ($clumps, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($clumps as $clump) {

                $row['clump_id'] = $clump->id;
                $row['line'] = $clump->line;
                $row['shift'] = $clump->shift;
                $row['recipe_id'] = $clump->recipe_id;
                $row['recipe_name'] = $clump->recipe_name;
                $row['std_mid'] = $clump->std_mid;
                $row['is_correcting'] = $clump->correcting_rate > 0.8 ? 'ON' : 'OFF';
                $row['avg_left'] = $clump->avg_left;
                $row['avg_right'] = $clump->avg_right;
                $row['sd_left'] = $clump->sd_left;
                $row['sd_right'] = $clump->sd_right;
                $row['mae_left'] = $clump->mae_left;
                $row['mae_right'] = $clump->mae_right;
                $row['duration_seconds'] = $clump->duration_seconds;
                $row['start_time'] = $clump->start_time;

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
                    $row['start_time'],
                ]);
            }
            fclose($file);
        };

        // Generate CSV file and return as a download
        $fileName = __('Wawasan').' '.__('RTC').'_'.__('Gilingan').'_'.date('Y-m-d_Hs').'.csv';

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    public function insLdcHides(Request $request)
    {
        $start = Carbon::parse($request['start_at']);
        $end = Carbon::parse($request['end_at'])->endOfDay();

        $hidesQuery = InsLdcHide::join('ins_ldc_groups', 'ins_ldc_hides.ins_ldc_group_id', '=', 'ins_ldc_groups.id')
            ->join('users', 'ins_ldc_hides.user_id', '=', 'users.id')
            ->select(
                'ins_ldc_hides.*',
                'ins_ldc_hides.updated_at as hide_updated_at',
                'ins_ldc_groups.workdate as group_workdate',
                'ins_ldc_groups.style as group_style',
                'ins_ldc_groups.line as group_line',
                'ins_ldc_groups.material as group_material',
                'users.emp_id as user_emp_id',
                'users.name as user_name');

        if (! $request->is_workdate) {
            $hidesQuery->whereBetween('ins_ldc_hides.updated_at', [$start, $end]);
        } else {
            $hidesQuery->whereBetween('ins_ldc_groups.workdate', [$start, $end]);
        }

        switch ($request->ftype) {
            case 'code':
                $hidesQuery->where('ins_ldc_hides.code', 'LIKE', '%'.$request->fquery.'%');
                break;
            case 'style':
                $hidesQuery->where('ins_ldc_groups.style', 'LIKE', '%'.$request->fquery.'%');
                break;
            case 'line':
                $hidesQuery->where('ins_ldc_groups.line', 'LIKE', '%'.$request->fquery.'%');
                break;
            case 'material':
                $hidesQuery->where('ins_ldc_groups.material', 'LIKE', '%'.$request->fquery.'%');
                break;
            case 'emp_id':
                $hidesQuery->where('users.emp_id', 'LIKE', '%'.$request->fquery.'%');
                break;

            default:
                $hidesQuery->where(function (Builder $query) use ($request) {
                    $query
                        ->orWhere('ins_ldc_hides.code', 'LIKE', '%'.$request->fquery.'%')
                        ->orWhere('ins_ldc_groups.style', 'LIKE', '%'.$request->fquery.'%')
                        ->orWhere('ins_ldc_groups.line', 'LIKE', '%'.$request->fquery.'%')
                        ->orWhere('ins_ldc_groups.material', 'LIKE', '%'.$request->fquery.'%')
                        ->orWhere('users.emp_id', 'LIKE', '%'.$request->fquery.'%');
                });
                break;
        }

        if (! $request->is_workdate) {
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
            __('NIK'),
            __('Nama'),
        ];

        $callback = function () use ($hides, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($hides as $hide) {
                $row['updated_at'] = $hide->hide_updated_at;
                $row['code'] = $hide->code;
                $row['area_vn'] = $hide->area_vn;
                $row['area_ab'] = $hide->area_ab;
                $row['area_qt'] = $hide->area_qt;
                $row['grade'] = $hide->grade;
                $row['shift'] = $hide->shift;
                $row['workdate'] = $hide->group_workdate;
                $row['style'] = $hide->group_style;
                $row['line'] = $hide->group_line;
                $row['material'] = $hide->group_material ?? '';
                $row['emp_id'] = $hide->user_emp_id;
                $row['name'] = $hide->user_name;

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
                    $row['name'],
                ]);
            }
            fclose($file);
        };

        $fileName = __('Wawasan').' '.__('LDC').'_'.__('Kulit').'_'.date('Y-m-d_His').'.csv';

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
