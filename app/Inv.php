<?php

namespace App;

use Carbon\Carbon;
use App\Models\InvCirc;
use App\Models\InvItem;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use DOMDocument;
use DOMXPath;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Str;

class Inv
{
    public static function itemsBuild($areas, $q, $status, $filter, $loc, $tag, $without, $sort, $qty): Builder
    {
        $q = trim($q);
        $inv_items = InvItem::whereIn('inv_items.inv_area_id', $areas)->where(function (Builder $query) use ($q) {
            $query
                ->orWhere('inv_items.name', 'LIKE', '%' . $q . '%')
                ->orWhere('inv_items.desc', 'LIKE', '%' . $q . '%')
                ->orWhere('inv_items.code', 'LIKE', '%' . $q . '%');
        });
        switch ($status) {
            case 'active':
                $inv_items->where('inv_items.is_active', true);
                break;
            case 'inactive':
                $inv_items->where('inv_items.is_active', false);
                break;
        }
        if ($filter) {
            if ($loc) {
                $loc = trim($loc);
                $inv_items
                    ->join('inv_locs', 'inv_items.inv_loc_id', '=', 'inv_locs.id')
                    ->where('inv_locs.name', 'like', '%' . $loc . '%')
                    ->select('inv_items.*', 'inv_locs.name as loc_names');
            }
            if ($tag) {
                $tag = trim($tag);
                $inv_items
                    ->join('inv_item_tags', 'inv_items.id', '=', 'inv_item_tags.inv_item_id')
                    ->join('inv_tags', 'inv_item_tags.inv_tag_id', '=', 'inv_tags.id')
                    ->where('inv_tags.name', 'like', '%' . $tag . '%')
                    ->select('inv_items.*', 'inv_tags.name as tag_names');
            }
            switch ($without) {
                case 'loc':
                    $inv_items->whereNull('inv_items.inv_loc_id');
                    break;
                case 'tags':
                    $inv_items->whereNotIn('id', function ($query) {
                        $query->select('inv_item_id')->from('inv_item_tags');
                    });
                    break;
                case 'photo':
                    $inv_items->whereNull('inv_items.photo');
                    break;
                case 'code':
                    $inv_items->whereNull('inv_items.code');
                    break;
                case 'qty_main_min':
                    $inv_items->where('inv_items.qty_main_min', 0);
                    break;
                case 'qty_main_max':
                    $inv_items->where('inv_items.qty_main_max', 0);
                    break;
            }
        }

        switch ($sort) {
            case 'updated':
                $inv_items->orderByDesc('inv_items.updated_at');
                break;
            case 'created':
                $inv_items->orderByDesc('inv_items.created_at');
                break;
            case 'price_low':
                $inv_items->orderBy('inv_items.price');
                break;
            case 'price_high':
                $inv_items->orderByDesc('inv_items.price');
                break;
            case 'qty_low':
                switch ($qty) {
                    case 'total':
                        $inv_items->selectRaw('*, (inv_items.qty_main + inv_items.qty_used + inv_items.qty_rep) as qty_total')->orderBy('qty_total');
                        break;
                    case 'main':
                        $inv_items->orderBy('inv_items.qty_main');
                        break;
                    case 'used':
                        $inv_items->orderBy('inv_items.qty_used');
                        break;
                    case 'rep':
                        $inv_items->orderBy('inv_items.qty_rep');
                        break;
                }
                break;
            case 'qty_high':
                switch ($qty) {
                    case 'total':
                        $inv_items->selectRaw('*, (inv_items.qty_main + inv_items.qty_used + inv_items.qty_rep) as qty_total')->orderByDesc('qty_total');
                        break;
                    case 'main':
                        $inv_items->orderByDesc('inv_items.qty_main');
                        break;
                    case 'used':
                        $inv_items->orderByDesc('inv_items.qty_used');
                        break;
                    case 'rep':
                        $inv_items->orderByDesc('inv_items.qty_rep');
                        break;
                }
                break;
                break;

            case 'alpha':
                $inv_items->orderBy('inv_items.name');
                break;
        }
        return $inv_items;
    }

    public static function circsBuild($area_ids_clean, $q, $status, $user, $qdirs, $start_at, $end_at, $sort) : Builder
    {
        $circs = InvCirc::join('inv_items', 'inv_circs.inv_item_id', '=', 'inv_items.id')
        ->whereIn('inv_items.inv_area_id', $area_ids_clean)
        ->select('inv_circs.*', 'inv_items.name', 'inv_items.desc', 'inv_items.code');

        if ($q) {
            $circs->where(function (Builder $query) use ($q) {
                $query->orWhere('inv_items.name', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_items.desc', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_items.code', 'LIKE', '%'.$q.'%')
                      ->orWhere('inv_circs.remarks', 'LIKE', '%'.$q.'%');
            });
        }

        $statusMap = [
            'pending'   => 0,
            'approved'  => 1,
            'rejected'  => 2,
        ];
        $statusCodes = [];
        foreach ($status as $i) {
            // Check if the status exists in the mapping
            if (array_key_exists($i, $statusMap)) {
                // Map the status to its corresponding numeric value and add to the new array
                $statusCodes[] = $statusMap[$i];
            }
        }
        if (count($statusCodes)) {
            $circs->whereIn('inv_circs.status', $statusCodes);
        } else {
            $circs->where('inv_circs.status', 9);
        }

        $user = trim($user);
        if($user) {
            $circs->join('users', 'inv_circs.user_id', '=', 'users.id')
            ->select('inv_circs.*', 'users.name as user_names', 'users.emp_id');
            $circs->where(function (Builder $query) use ($user) {
                $query->orWhere('users.name', 'LIKE', '%'.$user.'%')
                ->orWhere('users.emp_id', 'LIKE', '%'.$user.'%');
            });
        }

        if(count($qdirs)) {
            $circs->where(function (Builder $query) use ($qdirs) {
                $query;
                if(in_array('deposit', $qdirs)) {
                    $query->orWhere('inv_circs.qty', '>', 0);
                }
                if(in_array('withdrawal', $qdirs)) {
                    $query->orWhere('inv_circs.qty', '<', 0);
                }
                if(in_array('capture', $qdirs)) {
                    $query->orWhere('inv_circs.qty', 0);
                }
            });
        } else {
            $circs->whereNull('qty');
        }

        if($start_at && $end_at) {

            $start  = Carbon::parse($start_at);
            $end    = Carbon::parse($end_at)->addDay();

            $circs->whereBetween('inv_circs.updated_at', [$start, $end]);

        } else {
            $circs->whereNull('inv_circs.updated_at');
        }        

        switch ($sort) {
            case 'updated':
                $circs->orderByDesc('inv_circs.updated_at');
                break;
            case 'created':
                $circs->orderByDesc('inv_circs.created_at');
                break;            
            case 'amount_low':
                $circs->orderBy('inv_circs.amount');
                break;
            case 'amount_high':
                $circs->orderByDesc('inv_circs.amount');
                break;
            case 'qty_low':
                $circs->orderByRaw('ABS(qty)');
                break;
            case 'qty_high':
                $circs->orderByRaw('ABS(qty) DESC');
                break;        
        }

        return $circs;
    }

    public static function photoSniff($item_code, $ci_session): array
    {
        $status = [
            'success' => false,
            'photo' => '',
            'message' => __('Tak diketahui')
        ];

        try {

            $from_date = Carbon::now()->subYears(5)->format('Y-m-d');
            $to_date = Carbon::now()->format('Y-m-d');

            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://ttconsumable.t2group.co.kr/purchase_request/fetch_data/1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "action=fetch_data&from_date={$from_date}&to_date={$to_date}&status=Complete&item_code={$item_code}&req_id=%25",
                CURLOPT_HTTPHEADER => [
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0",
                    "Accept: application/json, text/javascript, */*; q=0.01",
                    "Accept-Language: en-US,en;q=0.5",
                    "Accept-Encoding: gzip, deflate, br, zstd",
                    "Referer: https://ttconsumable.t2group.co.kr/purchase_request",
                    "Origin: https://ttconsumable.t2group.co.kr",
                    "Sec-GPC: 1",
                    "Connection: keep-alive",
                    "Cookie: loginFrom=T2; ci_session={$ci_session}",
                    "Sec-Fetch-Dest: empty",
                    "Sec-Fetch-Mode: no-cors",
                    "Sec-Fetch-Site: same-origin",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With: XMLHttpRequest",
                    "Priority: u=0",
                    "Pragma: no-cache",
                    "Cache-Control: no-cache"
                ],
                CURLOPT_SSL_VERIFYPEER => false, // Bypass SSL verification
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                throw new Exception($err);                
            }

            // Decode the JSON response
            $data = json_decode($response, true);

            // Extract the request_list string
            $request_list = $data['request_list'] ?? '';

            // Use a regular expression to find the number in the specific position
            preg_match('/<td style=\\"padding:.4rem .4rem\\">(\\d+)<\\/td>/', $request_list, $matches);

            // The number you want is in $matches[1]
            $req_id = $matches[1] ?? null;

            if (!$req_id) {
                throw new Exception(__('PR yang mengandung item code tsb tidak ditemukan'));
            }

            $url = "https://ttconsumable.t2group.co.kr/request_detail/fetch_data?reqID={$req_id}";

            $headers = [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0",
                "Accept: application/json, text/javascript, */*; q=0.01",
                "Accept-Language: en-US,en;q=0.5",
                "Accept-Encoding: gzip, deflate, br, zstd",
                "X-Requested-With: XMLHttpRequest",
                "Sec-GPC: 1",
                "Connection: keep-alive",
                "Referer: https://ttconsumable.t2group.co.kr/purchase_request",
                "Cookie: loginFrom=T2; ci_session={$ci_session}",
                "Sec-Fetch-Dest: empty",
                "Sec-Fetch-Mode: cors",
                "Sec-Fetch-Site: same-origin",
                "Priority: u=0"
            ];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception($ch);
            }

            $img_src = null;
            $data = json_decode($response, true);
            $data_detail = $data['data_detail'] ?? null;

            if ($data_detail) {
                $dom = new DOMDocument;
                libxml_use_internal_errors(true);
                $dom->loadHTML($data_detail);
                libxml_clear_errors();

                $xpath = new DOMXPath($dom);
                $query = "//tr[td[text()='$item_code']]//img";
                $img = $xpath->query($query)->item(0);

                if ($img) {
                    $img_src = $img->getAttribute('src'); 
                }
            }

            if (!$img_src) {
                throw new Exception(__('Gambar tidak ditemukan'));
            }

            curl_close($ch);   

            $options = array(
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            );
            
            $context = stream_context_create($options);
            $content = file_get_contents($img_src, false, $context);

            // Create an instance of ImageManager
            $manager = new ImageManager(new Driver());
            $image = $manager->read($content)
            ->scale(600, 600)
            ->toJpeg(70);

            // Generate the filename
            $time = Carbon::now()->format('YmdHis');
            $rand = Str::random(5);
            $photo = $time . '_' . $rand . '.jpg';

            // Store the image
            $is_stored = Storage::put('/public/inv-items/' . $photo, $image);

            if (!$is_stored) {
                $photo = null;
                throw new Exception(__('Tidak dapat menyimpan foto'));
                
            }

            $status = [
                'success'   => true,
                'photo'     => $photo,
                'message'   => __('Foto berhasil disalin')
            ];          
            

        } catch (\Throwable $th) {
            $status = [
                'success'   => false,
                'photo'     => '',
                'message'   => $th->getMessage()
            ];
        }

        return $status;
    }

    public static function getCiSession($user_name): string
    {
        $ci_session = '';
        
        $url = "https://ttconsumable.t2group.co.kr/?empcd={$user_name}";
        $headers = [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-US,en;q=0.5",
            "Accept-Encoding: gzip, deflate, br, zstd",
            "Sec-GPC: 1",
            "Upgrade-Insecure-Requests: 1",
            "Connection: keep-alive",
            "Sec-Fetch-Dest: document",
            "Sec-Fetch-Mode: navigate",
            "Sec-Fetch-Site: none",
            "Sec-Fetch-User: ?1",
            "Priority: u=0, i"
        ];
        
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookies');
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Handle redirects manually
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); // Save cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // Read cookies
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the output
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        
        $ci_session = null;
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            // Separate headers and body
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($response, 0, $header_size);
        
            echo "Headers:\n" . $headers;
        
            // Extract the ci_session cookie
            if (preg_match('/Set-Cookie: ci_session=([^;]+)/', $headers, $matches)) {
                $ci_session = $matches[1];
            } 
        }

        return $ci_session;
    }
}
