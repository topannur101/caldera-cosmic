<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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

            // caldera: validation auth pls
            $circ = InvCirc::find($circ['id']);

            switch ($eval) {
                case 'approve':
                    if ($circ->eval_status !== 'pending')
                    {
                        throw new \Exception(__('Sirkulasi sudah dievaluasi'));
                    }

                    $item = InvItem::find($circ['inv_stock']['inv_item_id']);

                    $qty_a = $this->qty;
                    $qty_b = $circ['qty_relative'];
                    $qty_c = null;

                    switch ($circ['type']) {
                        case 'deposit':
                            $qty_c = $qty_a + $qty_b;
                            break;
                        
                        case 'withdrawal':
                            $qty_c = $qty_a - $qty_b;
                            $item->last_withdrawal = now();
                            break;
                    }

                    if($qty_c === null)
                    {
                        throw new \Exception(__('Terjadi masalah ketika menghitung qty akhir barang'));
                    }

                    if($qty_c < 0)
                    {
                        throw new \Exception(__('Qty akhir barang tidak boleh negatif'));
                    }

                    $this->update([
                        'qty' => $qty_c,
                        'is_active' => true
                    ]);

                    $item->is_active = true;
                    $item->save();

                    
                    $circ->update([
                        'eval_user_id'  => Auth::user()->id,
                        'eval_status'   => 'approved', 
                        'eval_remarks'  => $remarks
                    ]);

                    $status = [
                        'success' => true,
                        'message' => __('Sirkulasi disetujui dan stok barang diperbarui'),
                        'stock_qty' => $qty_c
                    ];

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
