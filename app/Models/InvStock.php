<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'inv_item_id',
        'inv_curr_id',
        'qty',
        'uom',
        'unit_price',
        'is_active',
        'inv_item_id',
        'inv_curr_id'
    ];

    public function inv_item()
    {
        return $this->belongsTo(InvItem::class);
    }

    public function inv_curr()
    {
        return $this->belongsTo(InvCurr::class);
    }

    public function updateByCirc(string $eval, array $circ, string $remarks): array
    {
        $status = [
            'success' => false,
            'message' => __('Tidak diketahui'),
            'stock_qty' => 0
        ];
        
        try {

            $circ = InvCirc::find($circ['id']);

            $circEval = Gate::inspect('eval', $circ);
            if ($circEval->denied())
            {
                throw new \Exception(__('Kamu tidak memiliki wewenang untuk mengevaluasi sirkulasi ini'));
            };

            switch ($eval) {
                case 'approve':
    
                    if ($circ->eval_status !== 'pending')
                    {
                        throw new \Exception(__('Sirkulasi sudah dievaluasi'));
                    }

                    $item = InvItem::find($circ['inv_stock']['inv_item_id']);
                    
                    $qty_initial = $this->qty;
                    $qty_relative = $circ['qty_relative'];
                    $qty_end = null;

                    switch ($circ['type']) {
                        case 'deposit':
                            $qty_end = $qty_initial + $qty_relative;
                            $item->last_deposit = now();
                            break;
                        
                        case 'withdrawal':
                            $qty_end = $qty_initial - $qty_relative;
                            $item->last_withdrawal = now();
                            break;
                    }

                    if($qty_end === null && ($circ['type'] === 'deposit' || $circ['type'] === 'withdrawal'))
                    {
                        throw new \Exception(__('Terjadi galat ketika menghitung qty akhir barang'));
                    }

                    if($qty_end < 0)
                    {
                        throw new \Exception(__('Pengambilan melebihi qty stok'));
                    }

                    $circ->update([
                        'eval_user_id'  => Auth::user()->id,
                        'eval_status'   => 'approved', 
                        'eval_remarks'  => $remarks
                    ]);

                    switch ($circ['type']) {
                        case 'deposit':
                        case 'withdrawal':
                            $this->update([
                                'qty' => $qty_end,
                                'is_active' => true
                            ]);
        
                            $item->is_active = true;
                            $item->save();
    
                            $status = [
                                'success' => true,
                                'message' => __('Sirkulasi disetujui dan stok barang diperbarui'),
                                'stock_qty' => $qty_end
                            ];
                            break;

                        case 'capture':
                            $status = [
                                'success' => true,
                                'message' => __('Sirkulasi disetujui'),
                                'stock_qty' => $qty_end
                            ];
                    }
                    
                    break;
                
                case 'reject':
                    if ($circ->eval_status !== 'pending')
                    {
                        throw new \Exception(__('Sirkulasi sudah dievaluasi'));
                    }

                    $circ->update([
                        'eval_user_id'  => Auth::user()->id,
                        'eval_status'   => 'rejected', 
                        'eval_remarks'  => $remarks
                    ]);

                    $status = [
                        'success' => true,
                        'message' => __('Sirkulasi ditolak'),
                        'stock_qty' => 0
                    ];

                    break;
            }

        } catch (\Exception $th) {
            $status = [
                'success' => false,
                'message' => $th->getMessage()
            ];
        }

        return $status;
    }
}
