<?php

use App\Models\InvCeAuth;
use App\Models\InvCeChemical;
use App\Models\InvCeMixingLog;
use App\Models\InvCeRecipe;
use App\Models\InvCeStock;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public string $cookieKey = 'invce_mixing_auth';

    // Recipe selection
    public array $recipes = [];

    public ?int $recipe_id_left = null;
    public ?int $recipe_id_right = null;

    public array $selectedRecipeLeft = [];
    public array $selectedRecipeRight = [];

    // RFID Auth
    public array $auth = [
        'status' => '',
        'rf_code' => '',
        'name' => '',
        'emp_id' => '',
        'is_active' => 0,
        'area' => '',
        'resource_type' => '',
        'resource_id' => 0,
    ];

    public bool $isAuthenticated = false;
    public string $rfidError = '';

    // Left Head - Chemical A
    public string $item_code_left_a = '';
    public string $chemical_name_left_a = '';
    public array  $lot_numbers_left_a = [];
    public string $stock_id_left_a = '';
    public string $lot_number_left_a = '';
    public string $exp_date_left_a = '';
    public string $weight_target_left_a = '';
    public string $weight_actual_left_a = '';

    // Left Head - Chemical B
    public string $item_code_left_b = '';
    public string $chemical_name_left_b = '';
    public array  $lot_numbers_left_b = [];
    public string $stock_id_left_b = '';
    public string $lot_number_left_b = '';
    public string $exp_date_left_b = '';
    public string $weight_target_left_b = '';
    public string $weight_actual_left_b = '';
    public string $percentage_left = '';

    // Right Head - Chemical A
    public string $item_code_right_a = '';
    public string $chemical_name_right_a = '';
    public array  $lot_numbers_right_a = [];
    public string $stock_id_right_a = '';
    public string $lot_number_right_a = '';
    public string $exp_date_right_a = '';
    public string $weight_target_right_a = '';
    public string $weight_actual_right_a = '';

    // Right Head - Chemical B
    public string $item_code_right_b = '';
    public string $chemical_name_right_b = '';
    public array  $lot_numbers_right_b = [];
    public string $stock_id_right_b = '';
    public string $lot_number_right_b = '';
    public string $exp_date_right_b = '';
    public string $weight_target_right_b = '';
    public string $weight_actual_right_b = '';
    public string $percentage_right = '';

    // Per-head states: form → running → completed/failed
    public bool $leftStarted = false;
    public bool $rightStarted = false;

    public int $durationSeconds = 600;
    public int $durationMinutes = 10;

    public bool $completedLeft = false;
    public bool $completedRight = false;
    public string $completeErrorLeft = '';
    public string $completeErrorRight = '';
    public array $completedStockLeft = [];
    public array $completedStockRight = [];

    public bool $failedLeft = false;
    public bool $failedRight = false;

    public function mount(): void
    {
        $this->recipes = InvCeRecipe::query()
            ->with(['chemical:id,item_code,name', 'hardener:id,item_code,name'])
            ->where('is_active', true)
            ->orderBy('line')
            ->orderBy('model')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'line'            => $r->line,
                'model'           => $r->model,
                'area'            => $r->area,
                'chemical_code'   => $r->chemical?->item_code ?? '',
                'chemical_name'   => $r->chemical?->name ?? '',
                'hardener_code'   => $r->hardener?->item_code ?? '',
                'hardener_name'   => $r->hardener?->name ?? '',
                'hardener_ratio'  => $r->hardener_ratio,
                'output_code'     => $r->output_code,
                'potlife'         => $r->potlife,
                'target_weight'   => $r->additional_settings['target_weight'] ?? null,
                'up_dev'          => $r->additional_settings['up_dev'] ?? null,
                'low_dev'         => $r->additional_settings['low_dev'] ?? null,
            ])
            ->toArray();

        // convert minutes to seconds for timer
        $this->durationSeconds = $this->durationMinutes * 60;
    }

    public function updatedDurationMinutes(int $value): void
    {
        $this->durationSeconds = $value * 60;
    }

    // ──── Recipe selection handlers ────

    public function updatedRecipeIdLeft(?int $value): void
    {
        if (! $value) {
            $this->selectedRecipeLeft = [];
            $this->clearChemicalFieldsLeft();
            return;
        }

        $recipe = collect($this->recipes)->firstWhere('id', $value);
        if (! $recipe) { $this->selectedRecipeLeft = []; return; }

        $this->selectedRecipeLeft = $recipe;
        $this->item_code_left_a     = $recipe['chemical_code'];
        $this->chemical_name_left_a = $recipe['chemical_name'];
        $this->item_code_left_b     = $recipe['hardener_code'];
        $this->chemical_name_left_b = $recipe['hardener_name'];
        $this->percentage_left      = (string) $recipe['hardener_ratio'];

        if (isset($recipe['target_weight']) && $recipe['target_weight'] !== null) {
            $ratio = (float) $recipe['hardener_ratio'] / 100;
            $wB = round((float) $recipe['target_weight'] * $ratio, 2);
            $wA = round((float) $recipe['target_weight'], 2);
            $this->weight_target_left_a = (string) $wA;
            $this->weight_target_left_b = (string) $wB;
        }

        $this->lot_numbers_left_a = $this->getLotNumbersByItemCode($recipe['chemical_code']);
        $this->lot_numbers_left_b = $this->getLotNumbersByItemCode($recipe['hardener_code']);
        $this->stock_id_left_a = $this->stock_id_left_b = '';
        $this->lot_number_left_a = $this->lot_number_left_b = '';
        $this->exp_date_left_a = $this->exp_date_left_b = '';
    }

    public function updatedRecipeIdRight(?int $value): void
    {
        if (! $value) {
            $this->selectedRecipeRight = [];
            $this->clearChemicalFieldsRight();
            return;
        }

        $recipe = collect($this->recipes)->firstWhere('id', $value);
        if (! $recipe) { $this->selectedRecipeRight = []; return; }

        $this->selectedRecipeRight = $recipe;
        $this->item_code_right_a     = $recipe['chemical_code'];
        $this->chemical_name_right_a = $recipe['chemical_name'];
        $this->item_code_right_b     = $recipe['hardener_code'];
        $this->chemical_name_right_b = $recipe['hardener_name'];
        $this->percentage_right      = (string) $recipe['hardener_ratio'];

        if (isset($recipe['target_weight']) && $recipe['target_weight'] !== null) {
            $ratio = (float) $recipe['hardener_ratio'] / 100;
            $wB = round((float) $recipe['target_weight'] * $ratio, 2);
            $wA = round((float) $recipe['target_weight'], 2);
            $this->weight_target_right_a = (string) $wA;
            $this->weight_target_right_b = (string) $wB;
        }

        $this->lot_numbers_right_a = $this->getLotNumbersByItemCode($recipe['chemical_code']);
        $this->lot_numbers_right_b = $this->getLotNumbersByItemCode($recipe['hardener_code']);
        $this->stock_id_right_a = $this->stock_id_right_b = '';
        $this->lot_number_right_a = $this->lot_number_right_b = '';
        $this->exp_date_right_a = $this->exp_date_right_b = '';
    }

    private function clearChemicalFieldsLeft(): void
    {
        $this->item_code_left_a = $this->chemical_name_left_a = '';
        $this->item_code_left_b = $this->chemical_name_left_b = '';
        $this->percentage_left  = '';
        $this->lot_numbers_left_a = $this->lot_numbers_left_b = [];
        $this->stock_id_left_a = $this->stock_id_left_b = '';
        $this->lot_number_left_a = $this->lot_number_left_b = '';
        $this->exp_date_left_a = $this->exp_date_left_b = '';
        $this->weight_target_left_a = $this->weight_target_left_b = '';
        $this->weight_actual_left_a = $this->weight_actual_left_b = '';
    }

    private function clearChemicalFieldsRight(): void
    {
        $this->item_code_right_a = $this->chemical_name_right_a = '';
        $this->item_code_right_b = $this->chemical_name_right_b = '';
        $this->percentage_right  = '';
        $this->lot_numbers_right_a = $this->lot_numbers_right_b = [];
        $this->stock_id_right_a = $this->stock_id_right_b = '';
        $this->lot_number_right_a = $this->lot_number_right_b = '';
        $this->exp_date_right_a = $this->exp_date_right_b = '';
        $this->weight_target_right_a = $this->weight_target_right_b = '';
        $this->weight_actual_right_a = $this->weight_actual_right_b = '';
    }

    private function getLotNumbersByItemCode(string $item_code): array
    {
        if ($item_code === '') return [];

        return InvCeStock::query()
            ->join('inv_ce_chemicals', 'inv_ce_stock.inv_ce_chemical_id', '=', 'inv_ce_chemicals.id')
            ->where('inv_ce_chemicals.item_code', $item_code)
            ->where('inv_ce_stock.quantity', '>', 0)
            ->orderBy('inv_ce_stock.expiry_date')
            ->select('inv_ce_stock.id', 'inv_ce_stock.lot_number', 'inv_ce_stock.expiry_date')
            ->get()
            ->map(fn($s) => [
                'id'          => (string) $s->id,
                'lot_number'  => $s->lot_number,
                'expiry_date' => $s->expiry_date ? \Carbon\Carbon::parse($s->expiry_date)->format('Y-m-d') : '',
            ])
            ->toArray();
    }

    public function updatedStockIdLeftA(string $value): void
    {
        $found = collect($this->lot_numbers_left_a)->firstWhere('id', $value);
        $this->lot_number_left_a = $found['lot_number'] ?? '';
        $this->exp_date_left_a   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdLeftB(string $value): void
    {
        $found = collect($this->lot_numbers_left_b)->firstWhere('id', $value);
        $this->lot_number_left_b = $found['lot_number'] ?? '';
        $this->exp_date_left_b   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdRightA(string $value): void
    {
        $found = collect($this->lot_numbers_right_a)->firstWhere('id', $value);
        $this->lot_number_right_a = $found['lot_number'] ?? '';
        $this->exp_date_right_a   = $found['expiry_date'] ?? '';
    }

    public function updatedStockIdRightB(string $value): void
    {
        $found = collect($this->lot_numbers_right_b)->firstWhere('id', $value);
        $this->lot_number_right_b = $found['lot_number'] ?? '';
        $this->exp_date_right_b   = $found['expiry_date'] ?? '';
    }

    // ──── RFID Auth ────

    public function searchTTCode(string $code): void
    {
        $code = trim($code);
        $this->rfidError = '';

        if ($code === '') {
            Cookie::queue(Cookie::forget($this->cookieKey));
            $this->auth = ['status' => '', 'rf_code' => '', 'name' => '', 'emp_id' => '', 'is_active' => 0, 'area' => '', 'resource_type' => '', 'resource_id' => 0];
            $this->isAuthenticated = false;
            return;
        }

        $authRfid = InvCeAuth::query()->with('user')->where('rf_code', $code)->first();
        $authUser = $authRfid?->user;

        if ($authUser) {
            $this->auth = [
                'status' => 'found',
                'rf_code' => $authRfid->rf_code,
                'name' => $authUser->name,
                'emp_id' => $authUser->emp_id,
                'is_active' => (int) ($authUser->is_active ?? 0),
                'area' => $authRfid->area,
                'resource_type' => $authRfid->resource_type,
                'resource_id' => $authRfid->resource_id,
            ];
            $this->isAuthenticated = true;
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        } else {
            $this->auth = [
                'status' => 'not_found', 'rf_code' => $code, 'name' => '', 'emp_id' => '',
                'is_active' => 0, 'area' => '', 'resource_type' => '', 'resource_id' => 0,
            ];
            $this->isAuthenticated = false;
            $this->rfidError = 'RFID tidak terdaftar';
            Cookie::queue($this->cookieKey, json_encode($this->auth), 60 * 24);
        }
    }

    // ──── Set weight from scale ────

    public function setWeight(string $field, string $value): void
    {
        $allowed = [
            'weight_actual_left_a',
            'weight_actual_left_b',
            'weight_actual_right_a',
            'weight_actual_right_b',
        ];

        if (!in_array($field, $allowed, true)) return;

        $sanitized = (string) round(abs((float) $value), 2);
        $this->{$field} = $sanitized;
    }

    // ──── Start per-head (locks form, enters timer phase) ────

    public function startLeft(): void
    {
        if (!$this->isAuthenticated || !$this->recipe_id_left) return;
        if ($this->stock_id_left_a === '' || $this->stock_id_left_b === '') return;
        if ($this->weight_actual_left_a === '' || $this->weight_actual_left_b === '') return;
        $this->leftStarted = true;
    }

    public function startRight(): void
    {
        if (!$this->isAuthenticated || !$this->recipe_id_right) return;
        if ($this->stock_id_right_a === '' || $this->stock_id_right_b === '') return;
        if ($this->weight_actual_right_a === '' || $this->weight_actual_right_b === '') return;
        $this->rightStarted = true;
    }

    // ──── Timer completion / failure logic (from process-timer) ────

    private function createMixingLog(array $recipe, string $status, string $notes = ''): void
    {
        try {
            InvCeMixingLog::create([
                'recipe_id'    => $recipe['id'] ?? 0,
                'user_id'      => auth()->id() ?? 0,
                'batch_number' => 'MIX-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'duration'     => gmdate('H:i:s', $this->durationSeconds),
                'notes'        => $notes,
                'status'       => $status,
            ]);
        } catch (\Exception $e) {
            // fail silently
        }
    }

    private function decreaseInputStock(string $itemCode, string $lotNumber, float $weightUsed): array
    {
        if ($itemCode === '' || $weightUsed <= 0) return [];

        $stock = InvCeStock::query()
            ->join('inv_ce_chemicals', 'inv_ce_stock.inv_ce_chemical_id', '=', 'inv_ce_chemicals.id')
            ->where('inv_ce_chemicals.item_code', $itemCode)
            ->when($lotNumber !== '', fn($q) => $q->where('inv_ce_stock.lot_number', $lotNumber))
            ->orderBy('inv_ce_stock.expiry_date')
            ->select('inv_ce_stock.*')
            ->first();

        if (!$stock) return ['warning' => "Stock not found for item_code={$itemCode} lot={$lotNumber}"];

        $before = (float) $stock->quantity;
        $after  = max(0, $before - $weightUsed);
        $stock->quantity = $after;
        if ($after <= 0) $stock->status = 'empty';
        $stock->save();

        return [
            'item_code'  => $itemCode,
            'lot_number' => $lotNumber,
            'before'     => $before,
            'used'       => $weightUsed,
            'after'      => $after,
        ];
    }

    private function buildHeadData(string $side): array
    {
        if ($side === 'left') {
            return [
                'chemical_a' => [
                    'item_code'     => $this->item_code_left_a,
                    'chemical_name' => $this->chemical_name_left_a,
                    'lot_number'    => $this->lot_number_left_a,
                    'exp_date'      => $this->exp_date_left_a,
                    'weight_target' => $this->weight_target_left_a,
                    'weight_actual' => $this->weight_actual_left_a,
                ],
                'chemical_b' => [
                    'item_code'     => $this->item_code_left_b,
                    'chemical_name' => $this->chemical_name_left_b,
                    'lot_number'    => $this->lot_number_left_b,
                    'exp_date'      => $this->exp_date_left_b,
                    'weight_target' => $this->weight_target_left_b,
                    'weight_actual' => $this->weight_actual_left_b,
                ],
                'percentage' => $this->percentage_left,
            ];
        }

        return [
            'chemical_a' => [
                'item_code'     => $this->item_code_right_a,
                'chemical_name' => $this->chemical_name_right_a,
                'lot_number'    => $this->lot_number_right_a,
                'exp_date'      => $this->exp_date_right_a,
                'weight_target' => $this->weight_target_right_a,
                'weight_actual' => $this->weight_actual_right_a,
            ],
            'chemical_b' => [
                'item_code'     => $this->item_code_right_b,
                'chemical_name' => $this->chemical_name_right_b,
                'lot_number'    => $this->lot_number_right_b,
                'exp_date'      => $this->exp_date_right_b,
                'weight_target' => $this->weight_target_right_b,
                'weight_actual' => $this->weight_actual_right_b,
            ],
            'percentage' => $this->percentage_right,
        ];
    }

    private function saveHeadStock(array $recipe, array $head, string $side): array
    {
        $outputCode = $recipe['output_code'] ?? null;
        if (!$outputCode) return ['error' => 'No output code configured.'];

        $chemical = InvCeChemical::firstOrCreate(
            ['item_code' => $outputCode],
            [
                'name'              => $outputCode,
                'uom'               => 'kg',
                'is_active'         => true,
                'status_bom'        => '0',
                'category_chemical' => 'double',
            ]
        );

        $weightA     = (float) ($head['chemical_a']['weight_actual'] ?? 0);
        $weightB     = (float) ($head['chemical_b']['weight_actual'] ?? 0);
        $totalWeight = $weightA + $weightB;

        if ($totalWeight <= 0) return ['error' => 'Weight is zero, nothing to save.'];

        $decreaseA = $this->decreaseInputStock(
            $head['chemical_a']['item_code'] ?? '', $head['chemical_a']['lot_number'] ?? '', $weightA
        );
        $decreaseB = $this->decreaseInputStock(
            $head['chemical_b']['item_code'] ?? '', $head['chemical_b']['lot_number'] ?? '', $weightB
        );

        $lotNumber = trim($head['chemical_a']['lot_number'] ?? '');
        $potlife   = (float) ($recipe['potlife'] ?? 0);
        $expDate   = $potlife > 0
            ? now()->addHours($potlife)->toDateTimeString()
            : now()->addYear()->toDateTimeString();

        $stock = InvCeStock::create([
            'inv_ce_chemical_id' => $chemical->id,
            'quantity'           => $totalWeight,
            'unit_size'          => $totalWeight,
            'unit_uom'           => $chemical->uom ?? 'kg',
            'lot_number'         => $lotNumber ?: null,
            'expiry_date'        => $expDate,
            'planning_area'      => json_encode([$recipe['area']] ?? []),
            'status'             => 'approved',
            'remarks'            => "Mixed: {$recipe['chemical_code']} + {$recipe['hardener_code']} | Operator: " . ($this->auth['name'] ?? ''),
        ]);

        return [
            'side'        => $side,
            'output_code' => $outputCode,
            'name'        => $chemical->name,
            'quantity'    => $totalWeight,
            'uom'         => $chemical->uom ?? 'kg',
            'stock_id'    => $stock->id,
            'decrease_a'  => $decreaseA,
            'decrease_b'  => $decreaseB,
        ];
    }

    public function completeMixingLeft(): void
    {
        $this->completeErrorLeft = '';
        if (empty($this->selectedRecipeLeft)) { $this->completeErrorLeft = 'No left head data.'; return; }
        $head = $this->buildHeadData('left');
        $result = $this->saveHeadStock($this->selectedRecipeLeft, $head, 'Left');
        if (isset($result['error'])) { $this->completeErrorLeft = $result['error']; return; }
        $this->completedStockLeft = $result;
        $this->completedLeft = true;
        $this->createMixingLog($this->selectedRecipeLeft, 'completed',
            'Left head completed. Output stock #' . ($result['stock_id'] ?? ''));
    }

    public function completeMixingRight(): void
    {
        $this->completeErrorRight = '';
        if (empty($this->selectedRecipeRight)) { $this->completeErrorRight = 'No right head data.'; return; }
        $head = $this->buildHeadData('right');
        $result = $this->saveHeadStock($this->selectedRecipeRight, $head, 'Right');
        if (isset($result['error'])) { $this->completeErrorRight = $result['error']; return; }
        $this->completedStockRight = $result;
        $this->completedRight = true;
        $this->createMixingLog($this->selectedRecipeRight, 'completed',
            'Right head completed. Output stock #' . ($result['stock_id'] ?? ''));
    }

    public function failMixingLeft(): void
    {
        if ($this->failedLeft || $this->completedLeft) return;
        $this->failedLeft = true;
        if (!empty($this->selectedRecipeLeft)) {
            $this->createMixingLog($this->selectedRecipeLeft, 'failed', 'Left head — Emergency Stop triggered.');
        }
    }

    public function failMixingRight(): void
    {
        if ($this->failedRight || $this->completedRight) return;
        $this->failedRight = true;
        if (!empty($this->selectedRecipeRight)) {
            $this->createMixingLog($this->selectedRecipeRight, 'failed', 'Right head — Emergency Stop triggered.');
        }
    }

    public function resetFailLeft(): void  { $this->failedLeft = false; }
    public function resetFailRight(): void { $this->failedRight = false; }

    public function resetLeft(): void
    {
        $this->leftStarted = false;
        $this->completedLeft = false;
        $this->failedLeft = false;
        $this->completeErrorLeft = '';
        $this->completedStockLeft = [];
        $this->recipe_id_left = null;
        $this->selectedRecipeLeft = [];
        $this->clearChemicalFieldsLeft();
        $this->dispatch('resetLeftDone');
    }

    public function resetRight(): void
    {
        $this->rightStarted = false;
        $this->completedRight = false;
        $this->failedRight = false;
        $this->completeErrorRight = '';
        $this->completedStockRight = [];
        $this->recipe_id_right = null;
        $this->selectedRecipeRight = [];
        $this->clearChemicalFieldsRight();
        $this->dispatch('resetRightDone');
    }
};

?>

<x-slot name="header">
    <x-nav-insights-ce-mix></x-nav-insights-ce-mix>
</x-slot>

<div
    class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200"
    x-data="{
        duration: @js($durationSeconds),

        // Scale weight
        latestScaleWeight: '0.000',
        lockedFields: { weight_actual_left_a: false, weight_actual_left_b: false, weight_actual_right_a: false, weight_actual_right_b: false },

        // Auto-lock on stable
        stableThresholdSec: 10,
        stableLastWeight: null,
        stableSince: null,
        stableTimer: null,
        stableCountdown: 0,
        stableTargetField: null,

        setWeight(field) {
            this.lockedFields[field] = true;
            $wire.setWeight(field, this.latestScaleWeight);
            this.clearStableTimer();
        },

        unlockWeight(field) {
            this.lockedFields[field] = false;
            this.clearStableTimer();
        },

        getNextUnlockedField() {
            const fields = ['weight_actual_left_a', 'weight_actual_left_b', 'weight_actual_right_a', 'weight_actual_right_b'];
            return fields.find(f => !this.lockedFields[f]) || null;
        },

        clearStableTimer() {
            if (this.stableTimer) { clearInterval(this.stableTimer); this.stableTimer = null; }
            this.stableCountdown = 0;
            this.stableTargetField = null;
            this.stableSince = null;
        },

        onScaleWeight(weight) {
            this.latestScaleWeight = weight;
            const targetField = this.getNextUnlockedField();

            if (!targetField) {
                this.clearStableTimer();
                return;
            }

            if (weight !== this.stableLastWeight || targetField !== this.stableTargetField) {
                this.stableLastWeight = weight;
                this.clearStableTimer();
                const w = parseFloat(weight);
                if (isNaN(w) || w <= 0) return;
                this.stableSince = Date.now();
                this.stableTargetField = targetField;
                this.stableCountdown = this.stableThresholdSec;
                this.stableTimer = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - this.stableSince) / 1000);
                    this.stableCountdown = Math.max(0, this.stableThresholdSec - elapsed);
                    if (this.stableCountdown <= 0) {
                        this.setWeight(this.stableTargetField);
                        this.clearStableTimer();
                    }
                }, 1000);
            }
        },

        // LEFT HEAD timer
        leftElapsed: 0,
        leftRunning: false,
        leftFinished: false,
        leftInterval: null,
        leftWaiting: false,
        emAFailed: false,

        // RIGHT HEAD timer
        rightElapsed: 0,
        rightRunning: false,
        rightFinished: false,
        rightInterval: null,
        rightWaiting: false,
        emBFailed: false,

        // Computed LEFT
        get leftRemaining() { return Math.max(0, this.duration - this.leftElapsed); },
        get leftProgress()  { return Math.min(100, (this.leftElapsed / this.duration) * 100); },
        get leftTimeDisplay() {
            const m = Math.floor(this.leftRemaining / 60);
            const s = this.leftRemaining % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        },

        // Computed RIGHT
        get rightRemaining() { return Math.max(0, this.duration - this.rightElapsed); },
        get rightProgress()  { return Math.min(100, (this.rightElapsed / this.duration) * 100); },
        get rightTimeDisplay() {
            const m = Math.floor(this.rightRemaining / 60);
            const s = this.rightRemaining % 60;
            return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
        },

        startLeftTimer() {
            if (this.leftFinished || this.leftRunning) return;
            this.leftRunning = true;
            this.leftInterval = setInterval(() => {
                if (this.leftElapsed < this.duration) {
                    this.leftElapsed++;
                } else {
                    this.leftFinished = true;
                    this.leftRunning = false;
                    clearInterval(this.leftInterval);
                }
            }, 1000);
        },

        startRightTimer() {
            if (this.rightFinished || this.rightRunning) return;
            this.rightRunning = true;
            this.rightInterval = setInterval(() => {
                if (this.rightElapsed < this.duration) {
                    this.rightElapsed++;
                } else {
                    this.rightFinished = true;
                    this.rightRunning = false;
                    clearInterval(this.rightInterval);
                }
            }, 1000);
        },

        // Start head: lock form + wait for micon response
        confirmStartLeft() {
            $wire.startLeft().then(() => {
                this.leftWaiting = true;
                if (this.ws && this.wsConnected) {
                    this.ws.send('A:START');
                }
            });
        },

        confirmStartRight() {
            $wire.startRight().then(() => {
                this.rightWaiting = true;
                if (this.ws && this.wsConnected) {
                    this.ws.send('B:START');
                }
            });
        },

        // WebSocket (micon)
        wsUrl: @js(config('rfid.ws_url_node_1')),
        ws: null,
        wsConnected: false,
        wsError: '',
        reconnectAttempt: 0,
        reconnectTimer: null,
        lastRaw: '',

        // Parsed micon states
        miconA: { st: null, ti: null, em: null, mode: null },
        miconB: { st: null, ti: null, em: null, mode: null },
        miconWeight: null,
        miconId: null,

        parseMicon(raw) {
            if (!raw) return;
            this.lastRaw = raw;
            const idM = raw.match(/\[ID:(\d+)\]/);
            if (idM) this.miconId = parseInt(idM[1]);
            const wM = raw.match(/W:\s*([\d.]+)/);
            if (wM) this.miconWeight = parseFloat(wM[1]);

            const aM = raw.match(/A:\[St:(\d+)\s+Ti:(\d+)\s+Em:(\d+)\s+Mode:(\d+)\]/);
            if (aM) this.miconA = { st: parseInt(aM[1]), ti: parseInt(aM[2]), em: parseInt(aM[3]), mode: parseInt(aM[4]) };

            const bM = raw.match(/B:\[St:(\d+)\s+Ti:(\d+)\s+Em:(\d+)\s+Mode:(\d+)\]/);
            if (bM) this.miconB = { st: parseInt(bM[1]), ti: parseInt(bM[2]), em: parseInt(bM[3]), mode: parseInt(bM[4]) };

            this.reactToMicon();
        },

        reactToMicon() {
            // LEFT head driven by A — only react if left head is actually started
            if (this.miconA.st !== null && $wire.leftStarted) {
                if (this.miconA.em === 1) {
                    this.leftWaiting = false;
                    this.leftRunning = false;
                    this.leftFinished = false;
                    this.leftElapsed = 0;
                    if (this.leftInterval) { clearInterval(this.leftInterval); this.leftInterval = null; }
                    if (!this.emAFailed) {
                        this.emAFailed = true;
                        $wire.failMixingLeft();
                    }
                } else if (this.miconA.st === 1 && !this.leftRunning && !this.leftFinished) {
                    if (this.emAFailed) {
                        this.emAFailed = false;
                        $wire.call('resetFailLeft');
                    }
                    if (this.leftWaiting) {
                        this.leftWaiting = false;
                        this.startLeftTimer();
                    }
                } else if (this.miconA.ti === 1 && !this.leftFinished) {
                    this.leftFinished = true;
                    this.leftRunning = false;
                    this.leftWaiting = false;
                    if (this.leftInterval) clearInterval(this.leftInterval);
                }
            }
            // RIGHT head driven by B — only react if right head is actually started
            if (this.miconB.st !== null && $wire.rightStarted) {
                if (this.miconB.em === 1) {
                    this.rightWaiting = false;
                    this.rightRunning = false;
                    this.rightFinished = false;
                    this.rightElapsed = 0;
                    if (this.rightInterval) { clearInterval(this.rightInterval); this.rightInterval = null; }
                    if (!this.emBFailed) {
                        this.emBFailed = true;
                        $wire.failMixingRight();
                    }
                } else if (this.miconB.st === 1 && !this.rightRunning && !this.rightFinished) {
                    if (this.emBFailed) {
                        this.emBFailed = false;
                        $wire.call('resetFailRight');
                    }
                    if (this.rightWaiting) {
                        this.rightWaiting = false;
                        this.startRightTimer();
                    }
                } else if (this.miconB.ti === 1 && !this.rightFinished) {
                    this.rightFinished = true;
                    this.rightRunning = false;
                    this.rightWaiting = false;
                    if (this.rightInterval) clearInterval(this.rightInterval);
                }
            }
        },

        resetLeftTimer() {
            this.leftElapsed = 0;
            this.leftRunning = false;
            this.leftFinished = false;
            this.leftWaiting = false;
            this.emAFailed = false;
            if (this.leftInterval) { clearInterval(this.leftInterval); this.leftInterval = null; }
        },

        resetRightTimer() {
            this.rightElapsed = 0;
            this.rightRunning = false;
            this.rightFinished = false;
            this.rightWaiting = false;
            this.emBFailed = false;
            if (this.rightInterval) { clearInterval(this.rightInterval); this.rightInterval = null; }
        },

        wsConnect() {
            if (!this.wsUrl) { this.wsError = 'NODE_1_WS_URL is not configured'; return; }
            try { this.ws = new WebSocket(this.wsUrl); } catch(e) {
                this.wsError = e?.message ?? 'Failed to connect';
                this.scheduleReconnect(); return;
            }
            this.ws.onopen = () => { this.wsConnected = true; this.wsError = ''; this.reconnectAttempt = 0; };
            this.ws.onmessage = (evt) => { this.parseMicon(typeof evt.data === 'string' ? evt.data.trim() : ''); };
            this.ws.onerror = () => { this.wsError = 'WebSocket error'; this.wsConnected = false; };
            this.ws.onclose = (evt) => {
                this.wsConnected = false;
                this.wsError = evt?.reason ? 'Disconnected: ' + evt.reason : 'Disconnected';
                this.scheduleReconnect();
            };
        },

        scheduleReconnect() {
            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
            const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
            this.reconnectTimer = setTimeout(() => this.wsConnect(), delay);
        },
    }"
    x-init="wsConnect(); window.addEventListener('scale-weight', e => { onScaleWeight(e.detail); });
        Livewire.on('resetLeftDone', () => { resetLeftTimer(); clearStableTimer(); });
        Livewire.on('resetRightDone', () => { resetRightTimer(); clearStableTimer(); });"
>
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- TOP BAR: RFID + WebSocket status                              --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            {{-- RFID Auth Cookie reader --}}
            <div
                x-data="{
                    auth: null,
                    initFromCookie() {
                        try {
                            const raw = document.cookie
                                .split('; ')
                                .find(r => r.startsWith('{{ $cookieKey }}='))
                                ?.split('=').slice(1).join('=');
                            if (raw) this.auth = JSON.parse(decodeURIComponent(raw));
                        } catch (_) {}
                    },
                }"
                x-init="initFromCookie()"
                @rfid-result.window="auth = $event.detail"
                class="flex items-center gap-2"
            >
                <span class="text-neutral-500">RFID Auth:</span>
                <span
                    class="font-medium"
                    :class="{
                        'text-green-600 dark:text-green-400': auth && auth.status === 'found',
                        'text-red-600 dark:text-red-400': auth && auth.status === 'not_found',
                        'text-neutral-500': !auth || !auth.status,
                    }"
                    x-text="auth && auth.status === 'found'
                        ? `${auth.name} (${auth.rf_code})`
                        : auth && auth.status === 'not_found'
                            ? `RFID ${auth.rf_code} not registered`
                            : 'Waiting card'"
                ></span>
            </div>

            <span class="hidden sm:inline text-neutral-300 dark:text-neutral-600">|</span>

            {{-- RFID Websocket --}}
            <div
                wire:ignore
                x-data="{
                    storageKey: 'invce_mixing_last_rfid',
                    url: @js(config('rfid.ws_url_rfid')),
                    ws: null,
                    connected: false,
                    error: '',
                    lastRawMessage: '',
                    code: '',
                    lastProcessedCode: '',
                    lastProcessedAt: 0,
                    wireDebounceTimer: null,
                    reconnectAttempt: 0,
                    reconnectTimer: null,

                    loadSavedCode() {
                        try {
                            const saved = localStorage.getItem(this.storageKey);
                            if (saved) {
                                this.code = String(saved).trim();
                                if (typeof $wire !== 'undefined') $wire.searchTTCode(this.code);
                            }
                        } catch (_) {}
                    },

                    saveCode(code) {
                        try { localStorage.setItem(this.storageKey, code); } catch (_) {}
                    },

                    connect() {
                        if (!this.url) { this.setDisconnected('RFID_WS_URL is empty'); return; }
                        if (window.location?.protocol === 'https:' && this.url.startsWith('ws://')) {
                            this.setDisconnected('Page is HTTPS, WebSocket must be WSS'); return;
                        }
                        try { this.ws = new WebSocket(this.url); } catch (e) {
                            this.setDisconnected(e?.message ?? 'Failed to create WebSocket');
                            this.scheduleReconnect(); return;
                        }
                        this.ws.onopen = () => { this.reconnectAttempt = 0; this.connected = true; this.error = ''; };
                        this.ws.onmessage = (event) => {
                            const raw = typeof event?.data === 'string' ? event.data : '';
                            this.lastRawMessage = raw;
                            let payload = raw;
                            if (raw && (raw.startsWith('{') || raw.startsWith('['))) {
                                try { payload = JSON.parse(raw); } catch (_) {}
                            }
                            let code = '';
                            if (typeof payload === 'string') {
                                code = payload;
                            } else if (payload && typeof payload === 'object') {
                                code = payload.data ?? payload.code ?? payload.tag ?? payload.uid ?? '';
                                if (code === '' && typeof payload.message === 'string') code = payload.message;
                            }
                            code = String(code ?? '').replace(/[\x00-\x1F\x7F]/g, '').trim();
                            if (code !== '') {
                                const now = Date.now();
                                if (code === this.lastProcessedCode && (now - this.lastProcessedAt) < 800) return;
                                this.lastProcessedCode = code;
                                this.lastProcessedAt = now;
                                this.code = code;
                                this.saveCode(code);
                                try {
                                    if (typeof $wire !== 'undefined') {
                                        if (this.wireDebounceTimer) clearTimeout(this.wireDebounceTimer);
                                        this.wireDebounceTimer = setTimeout(() => { $wire.searchTTCode(code); }, 150);
                                    }
                                } catch (_) {}
                            }
                        };
                        this.ws.onerror = () => { this.setDisconnected('WebSocket error'); };
                        this.ws.onclose = (evt) => {
                            const reason = evt?.reason ? `Disconnected: ${evt.reason}` : 'Disconnected';
                            this.setDisconnected(reason);
                            this.scheduleReconnect();
                        };
                    },
                    setDisconnected(message) { this.connected = false; if (message) this.error = String(message); },
                    scheduleReconnect() {
                        if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
                        const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
                        this.reconnectTimer = setTimeout(() => this.connect(), delay);
                    },
                }"
                x-init="connect(); $watch('code', value => { if(value) loadSavedCode() })"
                class="flex items-center gap-2"
            >
                <span class="text-neutral-500">RFID WS:</span>
                <span :class="connected ? 'text-green-500 font-medium' : 'text-red-500 font-medium'" x-text="connected ? 'Connected' : 'Disconnected'"></span>
            </div>

            <span class="hidden sm:inline text-neutral-300 dark:text-neutral-600">|</span>

            {{-- Weight Websocket --}}
            <div
                wire:ignore
                x-data="{
                    url: 'ws://127.0.0.1:8767/',
                    ws: null,
                    connected: false,
                    error: '',
                    reconnectAttempt: 0,
                    reconnectTimer: null,

                    setConnected() { this.connected = true; this.error = ''; this.reconnectAttempt = 0; },
                    setDisconnected(message) { this.connected = false; if (message) this.error = String(message); },

                    handleWeightMessage(raw) {
                        const idMatch = raw.match(/\[ID:(\d+)\]/);
                        const beratMatch = raw.match(/Berat:\s*([\d.]+)/);
                        if (idMatch && beratMatch && idMatch[1] === '1') { //id_weight
                            const w = parseFloat(beratMatch[1]);
                            if (!Number.isNaN(w)) {
                                window.dispatchEvent(new CustomEvent('scale-weight', { detail: Number(w).toFixed(3) }));
                            }
                        }
                    },

                    connect() {
                        if (!this.url) { this.setDisconnected('Weight WS URL is empty'); return; }
                        if (window.location?.protocol === 'https:' && this.url.startsWith('ws://')) {
                            this.setDisconnected('Page is HTTPS, WebSocket must be WSS'); return;
                        }
                        try { this.ws = new WebSocket(this.url); } catch (e) {
                            this.setDisconnected(e?.message ?? 'Failed to create WebSocket');
                            this.scheduleReconnect(); return;
                        }
                        this.ws.onopen = () => { this.setConnected(); };
                        this.ws.onmessage = (event) => {
                            const raw = typeof event?.data === 'string' ? event.data : '';
                            this.handleWeightMessage(raw);
                        };
                        this.ws.onerror = () => { this.setDisconnected('WebSocket error'); };
                        this.ws.onclose = (evt) => {
                            const reason = evt?.reason ? `Disconnected: ${evt.reason}` : 'Disconnected';
                            this.setDisconnected(reason);
                            this.scheduleReconnect();
                        };
                    },
                    scheduleReconnect() {
                        if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
                        const delay = Math.min(10000, 1000 * Math.pow(2, this.reconnectAttempt++));
                        this.reconnectTimer = setTimeout(() => this.connect(), delay);
                    },
                }"
                x-init="connect()"
                class="flex items-center gap-2"
            >
                <span class="text-neutral-500">Weight WS:</span>
                <span :class="connected ? 'text-green-500 font-medium' : 'text-red-500 font-medium'" x-text="connected ? 'Connected' : 'Disconnected'"></span>
            </div>

            <span class="hidden sm:inline text-neutral-300 dark:text-neutral-600">|</span>

            {{-- Micon WebSocket Status --}}
            <div class="flex items-center gap-2">
                <span class="text-neutral-500">{{ __('Micon') }}:</span>
                <span :class="wsConnected ? 'text-green-500 font-medium' : 'text-red-500 font-medium'" x-text="wsConnected ? 'Connected' : 'Disconnected'"></span>
                <span x-show="wsError" class="text-red-500 text-xs" x-text="wsError"></span>
            </div>

            @if($rfidError)
                <span class="text-red-500 text-xs">{{ $rfidError }}</span>
            @endif
        </div>
    </div>

    {{-- Operator / Plant Info --}}
    <div class="flex gap-3 bg-white dark:bg-neutral-800 shadow sm:rounded-lg p-4 mb-4">
        <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Operator") }}</label>
            @if($isAuthenticated)
                <span class="px-3 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300 rounded-md">{{ $auth['name'] }}</span>
            @else
                <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card") }}</span>
            @endif
        </div>
        <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Plant") }}</label>
            @if($isAuthenticated)
                <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md">Plant {{ $auth['area'] }}</span>
            @else
                <span class="px-3 py-1 bg-neutral-100 dark:bg-neutral-700 rounded-md text-red-500">{{ __("Tap ID Card") }}</span>
            @endif
        </div>
        
        <!-- timer setting -->
        <div>
            <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Mixing Time (min)") }}</label>
            <select wire:model.live="durationMinutes" x-on:change="duration = $wire.durationSeconds" class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                @foreach ([1,5,10,15,20,25,30,45,60] as $min)
                    <option value="{{ $min }}">{{ $min }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Emergency Alert --}}
    <div x-show="miconA.em === 1 || miconB.em === 1" x-transition class="mb-4 p-4 border rounded bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-200 font-semibold flex items-center gap-2">
        🚨 {{ __('Emergency Stop triggered! Mixing has been halted.') }}
        <span x-show="miconA.em === 1" class="ml-2 text-sm font-normal">({{ __('LEFT HEAD') }})</span>
        <span x-show="miconB.em === 1" class="ml-2 text-sm font-normal">({{ __('RIGHT HEAD') }})</span>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- LEFT / RIGHT HEADS — Side by side                             --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- ═══════════════════════ LEFT HEAD ═══════════════════════ --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-xl overflow-hidden border-t-4 border-t-blue-500"
             :class="miconA.em === 1 ? 'ring-2 ring-red-500' : ''">

            {{-- Header --}}
            <div class="flex items-center gap-3 px-5 py-3"
                 :class="@js($completedLeft) ? 'bg-green-500' : (@js($failedLeft) ? 'bg-red-500' : (leftFinished ? 'bg-green-500' : 'bg-blue-500'))">
                <span class="text-white font-bold text-sm tracking-widest uppercase">{{ __('Left Head') }}</span>
                @if(!empty($selectedRecipeLeft))
                    <span class="ml-1 text-white/80 font-mono text-xs">→ {{ $selectedRecipeLeft['output_code'] ?? '' }}</span>
                @endif
                @if($completedLeft)
                    <span class="ml-auto text-white font-bold text-xs">✓ {{ __('Completed') }}</span>
                @elseif($failedLeft)
                    <span class="ml-auto text-white/70 text-xs">🚨 {{ __('Failed') }}</span>
                @else
                    <span class="ml-auto text-white/70 text-xs" x-show="miconA.em === 1">🚨 {{ __('Emergency Stop') }}</span>
                @endif
            </div>

            @if(!$leftStarted && !$completedLeft && !$failedLeft)
                {{-- ──── FORM PHASE ──── --}}
                <div class="p-5 space-y-4">
                    {{-- Recipe Selector --}}
                    <div>
                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Recipe") }}</label>
                        <select wire:model.live="recipe_id_left"
                            class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            <option value="">{{ __("— Select recipe —") }}</option>
                            @foreach ($recipes as $r)
                                <option value="{{ $r['id'] }}">
                                    [{{ $r['line'] }}] {{ $r['model'] }} · {{ $r['area'] }} → {{ $r['output_code'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($selectedRecipeLeft))
                    {{-- Recipe info --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-caldy-50 dark:bg-caldy-900 border border-caldy-200 dark:border-caldy-700 rounded-lg text-sm">
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Chemical (A)") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeLeft['chemical_code'] }}</span>
                            <span class="block text-xs text-neutral-500">{{ $selectedRecipeLeft['chemical_name'] }}</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Hardener (B)") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeLeft['hardener_code'] }}</span>
                            <span class="block text-xs text-neutral-500">{{ $selectedRecipeLeft['hardener_name'] }}</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Ratio B") }}</span>
                            <span class="font-semibold text-caldy-600 dark:text-caldy-400">{{ $selectedRecipeLeft['hardener_ratio'] }}%</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Output Code") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeLeft['output_code'] }}</span>
                            <span class="block text-xs text-neutral-500">Potlife: {{ $selectedRecipeLeft['potlife'] }} hr</span>
                        </div>
                    </div>

                    {{-- Chemical A --}}
                    <div class="pb-4 border-b border-neutral-200 dark:border-neutral-700">
                        <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A (Base)") }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                                <input type="text" readonly class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="item_code_left_a">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                                <select wire:model.live="stock_id_left_a" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                                    <option value="">{{ __("— Select lot —") }}</option>
                                    @foreach ($lot_numbers_left_a as $lot)
                                        <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                                <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="weight_target_left_a">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input id="weight_actual_left_a" type="number" step="0.01" readonly
                                        class="block w-full rounded-md border-gray-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200"
                                        :class="lockedFields.weight_actual_left_a ? 'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 font-semibold' : 'bg-white dark:bg-neutral-700'"
                                        :value="lockedFields.weight_actual_left_a ? $wire.weight_actual_left_a : latestScaleWeight"
                                        wire:model="weight_actual_left_a">
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" x-show="!lockedFields.weight_actual_left_a" @click="setWeight('weight_actual_left_a')" class="px-3 py-1 text-sm rounded bg-caldy-500 hover:bg-caldy-600 text-white cursor-pointer">
                                        {{ __('Set Weight') }}
                                    </button>
                                    <button type="button" x-show="lockedFields.weight_actual_left_a" @click="unlockWeight('weight_actual_left_a')" class="px-3 py-1 text-sm rounded bg-amber-500 hover:bg-amber-600 text-white cursor-pointer">
                                        {{ __('Unlock') }}
                                    </button>
                                    <span class="self-center text-xs text-neutral-400" x-text="'Scale: ' + latestScaleWeight + ' g'"></span>
                                    <span x-show="stableTargetField === 'weight_actual_left_a' && stableCountdown > 0" x-transition class="self-center text-xs font-semibold text-blue-500" x-text="'⏱ ' + stableCountdown + 's'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Chemical B --}}
                    <div>
                        <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B (Hardener)") }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                                <input type="text" readonly class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="item_code_left_b">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                                <select wire:model.live="stock_id_left_b" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                                    <option value="">{{ __("— Select lot —") }}</option>
                                    @foreach ($lot_numbers_left_b as $lot)
                                        <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                                <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="weight_target_left_b">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input id="weight_actual_left_b" type="number" step="0.01" readonly
                                        class="block w-full rounded-md border-gray-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200"
                                        :class="lockedFields.weight_actual_left_b ? 'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 font-semibold' : 'bg-white dark:bg-neutral-700'"
                                        :value="lockedFields.weight_actual_left_b ? $wire.weight_actual_left_b : latestScaleWeight"
                                        wire:model="weight_actual_left_b">
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" x-show="!lockedFields.weight_actual_left_b" @click="setWeight('weight_actual_left_b')" class="px-3 py-1 text-sm rounded bg-caldy-500 hover:bg-caldy-600 text-white cursor-pointer">
                                        {{ __('Set Weight') }}
                                    </button>
                                    <button type="button" x-show="lockedFields.weight_actual_left_b" @click="unlockWeight('weight_actual_left_b')" class="px-3 py-1 text-sm rounded bg-amber-500 hover:bg-amber-600 text-white cursor-pointer">
                                        {{ __('Unlock') }}
                                    </button>
                                    <span class="self-center text-xs text-neutral-400" x-text="'Scale: ' + latestScaleWeight + ' g'"></span>
                                    <span x-show="stableTargetField === 'weight_actual_left_b' && stableCountdown > 0" x-transition class="self-center text-xs font-semibold text-blue-500" x-text="'⏱ ' + stableCountdown + 's'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Validation alerts --}}
                    @php
                        $leftWeightErrors = [];
                        $leftUpDev = (float) ($selectedRecipeLeft['up_dev'] ?? 0);
                        $leftLowDev = (float) ($selectedRecipeLeft['low_dev'] ?? 0);

                        // Lot number required
                        if ($stock_id_left_a === '') {
                            $leftWeightErrors[] = __('Chemical A: Lot number is required');
                        }
                        if ($stock_id_left_b === '') {
                            $leftWeightErrors[] = __('Chemical B: Lot number is required');
                        }

                        // Weight actual required
                        if ($weight_actual_left_a === '') {
                            $leftWeightErrors[] = __('Chemical A: Weight actual is required');
                        }
                        if ($weight_actual_left_b === '') {
                            $leftWeightErrors[] = __('Chemical B: Weight actual is required');
                        }

                        // Weight deviation check
                        if ($weight_target_left_a !== '' && $weight_actual_left_a !== '') {
                            $targetA = (float) $weight_target_left_a;
                            $actualA = (float) $weight_actual_left_a;
                            $lowerA = $targetA - $leftLowDev;
                            $upperA = $targetA + $leftUpDev;
                            if ($actualA < $lowerA) {
                                $leftWeightErrors[] = __('Chemical A: Actual weight (:actual g) is below lower limit (:lower g)', ['actual' => $weight_actual_left_a, 'lower' => round($lowerA, 2)]);
                            } elseif ($actualA > $upperA) {
                                $leftWeightErrors[] = __('Chemical A: Actual weight (:actual g) exceeds upper limit (:upper g)', ['actual' => $weight_actual_left_a, 'upper' => round($upperA, 2)]);
                            }
                        }
                        if ($weight_target_left_b !== '' && $weight_actual_left_b !== '') {
                            $targetB = (float) $weight_target_left_b;
                            $actualB = (float) $weight_actual_left_b;
                            $lowerB = $targetB - $leftLowDev;
                            $upperB = $targetB + $leftUpDev;
                            if ($actualB < $lowerB) {
                                $leftWeightErrors[] = __('Chemical B: Actual weight (:actual g) is below lower limit (:lower g)', ['actual' => $weight_actual_left_b, 'lower' => round($lowerB, 2)]);
                            } elseif ($actualB > $upperB) {
                                $leftWeightErrors[] = __('Chemical B: Actual weight (:actual g) exceeds upper limit (:upper g)', ['actual' => $weight_actual_left_b, 'upper' => round($upperB, 2)]);
                            }
                        }
                        $leftWeightValid = empty($leftWeightErrors);
                        $leftCanStart = $isAuthenticated && $recipe_id_left && $leftWeightValid;
                    @endphp

                    @if(!empty($leftWeightErrors))
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-sm space-y-1">
                        <div class="flex items-center gap-2 font-semibold">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                            {{ __('Weight out of tolerance') }}
                        </div>
                        @foreach($leftWeightErrors as $err)
                            <div class="ml-6 text-xs">{{ $err }}</div>
                        @endforeach
                        <div class="ml-6 text-xs text-neutral-500">{{ __('Tolerance: -:low / +:up', ['low' => $leftLowDev, 'up' => $leftUpDev]) }}</div>
                    </div>
                    @endif

                    {{-- Start button --}}
                    <div class="pt-4">
                        <button type="button"
                            @click="confirmStartLeft()"
                            {{ !$leftCanStart ? 'disabled' : '' }}
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-semibold text-white text-sm transition-colors
                                {{ $leftCanStart ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"></polygon></svg>
                            {{ __('Start Left Mixing') }}
                        </button>
                    </div>
                    @endif
                </div>

            @else
                {{-- ──── TIMER / RESULT PHASE ──── --}}
                <div x-data="{ detailOpen: false }">
                    {{-- Waiting for micon --}}
                    <div x-show="leftWaiting && !leftRunning && !leftFinished" x-transition class="px-5 pt-4 pb-2">
                        <div class="flex items-center gap-3 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300">
                            <svg class="animate-spin h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span class="font-semibold text-sm">{{ __('Waiting for micon response...') }}</span>
                        </div>
                    </div>

                    {{-- Progress + timer --}}
                    <div class="px-5 pt-4 pb-2" x-show="!leftWaiting || leftRunning || leftFinished">
                        <div class="flex items-end justify-between mb-1">
                            <span class="text-3xl font-bold tabular-nums" x-text="leftTimeDisplay"
                                  :class="leftFinished || @js($completedLeft) ? 'text-green-500' : (miconA.em === 1 ? 'text-red-500' : 'text-blue-500')"></span>
                            <span class="text-sm text-neutral-400 mb-1" x-text="Math.round(leftProgress) + '%'"></span>
                        </div>
                        <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-1000"
                                 :class="leftFinished || @js($completedLeft) ? 'bg-green-500' : (miconA.em === 1 ? 'bg-red-500' : 'bg-blue-500')"
                                 :style="'width:' + leftProgress + '%'"></div>
                        </div>
                    </div>

                    {{-- Chemical summary --}}
                    <button type="button" @click="detailOpen = !detailOpen"
                            class="w-full flex items-center justify-between px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors text-left">
                        <div class="flex gap-4 text-sm">
                            <div>
                                <div class="text-xs text-neutral-400 uppercase tracking-wide">A</div>
                                <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $chemical_name_left_a ?: '-' }}</div>
                                <div class="text-xs text-neutral-500">{{ $weight_actual_left_a ?: '-' }} g</div>
                            </div>
                            <div class="self-center text-neutral-300 dark:text-neutral-600 font-bold">+</div>
                            <div>
                                <div class="text-xs text-neutral-400 uppercase tracking-wide">B</div>
                                <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $chemical_name_left_b ?: '-' }}</div>
                                <div class="text-xs text-neutral-500">{{ $weight_actual_left_b ?: '-' }} g · {{ $percentage_left ?: '-' }}%</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-neutral-400 transition-transform" :class="detailOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    {{-- Expandable detail --}}
                    <div x-show="detailOpen" x-collapse class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4 space-y-4 text-sm">
                        <div>
                            <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical A') }}</div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <span class="text-neutral-500">{{ __('Item Code') }}</span><span class="font-mono">{{ $item_code_left_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $lot_number_left_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $exp_date_left_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $weight_target_left_a ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $weight_actual_left_a ?: '-' }} g</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical B') }}</div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <span class="text-neutral-500">{{ __('Item Code') }}</span><span class="font-mono">{{ $item_code_left_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $lot_number_left_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $exp_date_left_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $weight_target_left_b ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $weight_actual_left_b ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Ratio B') }}</span><span>{{ $percentage_left ?: '-' }} %</span>
                            </div>
                        </div>
                    </div>

                    {{-- Failed banner --}}
                    @if($failedLeft)
                    <div class="border-t border-red-200 dark:border-red-700 px-5 py-4 bg-red-50 dark:bg-red-900/30 flex items-center gap-3">
                        <span class="text-2xl">🚨</span>
                        <div>
                            <div class="font-semibold text-red-700 dark:text-red-300">{{ __('Emergency Stop — Mixing Failed') }}</div>
                            <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">{{ __('This mixing has been logged as failed.') }}</div>
                        </div>
                    </div>
                    @endif

                    {{-- Footer: action / result --}}
                    <div x-show="leftFinished || @js($completedLeft) || @js($failedLeft)" x-transition class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4">
                        @if(!$completedLeft && !$failedLeft)
                            @if($completeErrorLeft)
                                <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $completeErrorLeft }}</p>
                            @endif
                            <button wire:click="completeMixingLeft" wire:loading.attr="disabled"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold rounded-lg text-sm transition-colors">
                                <span wire:loading.remove wire:target="completeMixingLeft">{{ __('Save & Complete') }}</span>
                                <span wire:loading wire:target="completeMixingLeft" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    {{ __('Saving...') }}
                                </span>
                            </button>
                        @elseif($completedLeft)
                            <div class="space-y-2">
                                <div class="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-3 py-2">
                                    <div>
                                        <div class="text-xs text-green-600 dark:text-green-400 font-semibold uppercase tracking-wide">{{ __('Output created') }}</div>
                                        <div class="font-mono font-bold text-green-700 dark:text-green-300">{{ $completedStockLeft['output_code'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-500">{{ __('Stock') }} #{{ $completedStockLeft['stock_id'] ?? '' }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-green-600 dark:text-green-400">+{{ $completedStockLeft['quantity'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-500">{{ $completedStockLeft['uom'] ?? '' }}</div>
                                    </div>
                                </div>
                                @foreach([($completedStockLeft['decrease_a'] ?? null), ($completedStockLeft['decrease_b'] ?? null)] as $d)
                                    @if(!empty($d) && !isset($d['warning']))
                                    <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-3 py-2">
                                        <div>
                                            <div class="text-xs text-red-500 font-semibold uppercase tracking-wide">{{ __('Consumed') }}</div>
                                            <div class="font-mono font-medium text-neutral-700 dark:text-neutral-300">{{ $d['item_code'] ?? '' }}</div>
                                            @if(!empty($d['lot_number'])) <div class="text-xs text-neutral-400">{{ __('Lot') }}: {{ $d['lot_number'] }}</div> @endif
                                        </div>
                                        <div class="text-right text-sm">
                                            <div class="text-red-500 font-bold">−{{ $d['used'] ?? '' }}</div>
                                            <div class="text-xs text-neutral-400">{{ $d['before'] ?? '' }} → {{ $d['after'] ?? '' }}</div>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                                <button type="button" wire:click="resetLeft"
                                    class="mt-2 w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    {{ __('New Left Mixing') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════ RIGHT HEAD ═══════════════════════ --}}
        <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-xl overflow-hidden border-t-4 border-t-emerald-500"
             :class="miconB.em === 1 ? 'ring-2 ring-red-500' : ''">

            {{-- Header --}}
            <div class="flex items-center gap-3 px-5 py-3"
                 :class="@js($completedRight) ? 'bg-green-500' : (@js($failedRight) ? 'bg-red-500' : (rightFinished ? 'bg-green-500' : 'bg-emerald-500'))">
                <span class="text-white font-bold text-sm tracking-widest uppercase">{{ __('Right Head') }}</span>
                @if(!empty($selectedRecipeRight))
                    <span class="ml-1 text-white/80 font-mono text-xs">→ {{ $selectedRecipeRight['output_code'] ?? '' }}</span>
                @endif
                @if($completedRight)
                    <span class="ml-auto text-white font-bold text-xs">✓ {{ __('Completed') }}</span>
                @elseif($failedRight)
                    <span class="ml-auto text-white/70 text-xs">🚨 {{ __('Failed') }}</span>
                @else
                    <span class="ml-auto text-white/70 text-xs" x-show="miconB.em === 1">🚨 {{ __('Emergency Stop') }}</span>
                @endif
            </div>

            @if(!$rightStarted && !$completedRight && !$failedRight)
                {{-- ──── FORM PHASE ──── --}}
                <div class="p-5 space-y-4">
                    {{-- Recipe Selector --}}
                    <div>
                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Recipe") }}</label>
                        <select wire:model.live="recipe_id_right"
                            class="w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                            <option value="">{{ __("— Select recipe —") }}</option>
                            @foreach ($recipes as $r)
                                <option value="{{ $r['id'] }}">
                                    [{{ $r['line'] }}] {{ $r['model'] }} · {{ $r['area'] }} → {{ $r['output_code'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(!empty($selectedRecipeRight))
                    {{-- Recipe info --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-caldy-50 dark:bg-caldy-900 border border-caldy-200 dark:border-caldy-700 rounded-lg text-sm">
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Chemical (A)") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeRight['chemical_code'] }}</span>
                            <span class="block text-xs text-neutral-500">{{ $selectedRecipeRight['chemical_name'] }}</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Hardener (B)") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeRight['hardener_code'] }}</span>
                            <span class="block text-xs text-neutral-500">{{ $selectedRecipeRight['hardener_name'] }}</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Ratio B") }}</span>
                            <span class="font-semibold text-caldy-600 dark:text-caldy-400">{{ $selectedRecipeRight['hardener_ratio'] }}%</span>
                        </div>
                        <div>
                            <span class="block uppercase text-xs text-neutral-500 mb-1">{{ __("Output Code") }}</span>
                            <span class="font-mono font-medium">{{ $selectedRecipeRight['output_code'] }}</span>
                            <span class="block text-xs text-neutral-500">Potlife: {{ $selectedRecipeRight['potlife'] }} hr</span>
                        </div>
                    </div>

                    {{-- Chemical A --}}
                    <div class="pb-4 border-b border-neutral-200 dark:border-neutral-700">
                        <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical A (Base)") }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                                <input type="text" readonly class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="item_code_right_a">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                                <select wire:model.live="stock_id_right_a" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                                    <option value="">{{ __("— Select lot —") }}</option>
                                    @foreach ($lot_numbers_right_a as $lot)
                                        <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                                <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="weight_target_right_a">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input id="weight_actual_right_a" type="number" step="0.01" readonly
                                        class="block w-full rounded-md border-gray-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200"
                                        :class="lockedFields.weight_actual_right_a ? 'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 font-semibold' : 'bg-white dark:bg-neutral-700'"
                                        :value="lockedFields.weight_actual_right_a ? $wire.weight_actual_right_a : latestScaleWeight"
                                        wire:model="weight_actual_right_a">
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" x-show="!lockedFields.weight_actual_right_a" @click="setWeight('weight_actual_right_a')" class="px-3 py-1 text-sm rounded bg-caldy-500 hover:bg-caldy-600 text-white cursor-pointer">
                                        {{ __('Set Weight') }}
                                    </button>
                                    <button type="button" x-show="lockedFields.weight_actual_right_a" @click="unlockWeight('weight_actual_right_a')" class="px-3 py-1 text-sm rounded bg-amber-500 hover:bg-amber-600 text-white cursor-pointer">
                                        {{ __('Unlock') }}
                                    </button>
                                    <span class="self-center text-xs text-neutral-400" x-text="'Scale: ' + latestScaleWeight + ' g'"></span>
                                    <span x-show="stableTargetField === 'weight_actual_right_a' && stableCountdown > 0" x-transition class="self-center text-xs font-semibold text-blue-500" x-text="'⏱ ' + stableCountdown + 's'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Chemical B --}}
                    <div>
                        <div class="text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-3">{{ __("Chemical B (Hardener)") }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Item Code") }}</label>
                                <input type="text" readonly class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="item_code_right_b">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Lot Number") }}</label>
                                <select wire:model.live="stock_id_right_b" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200 focus:ring-caldy-500 focus:border-caldy-500">
                                    <option value="">{{ __("— Select lot —") }}</option>
                                    @foreach ($lot_numbers_right_b as $lot)
                                        <option value="{{ $lot['id'] }}">{{ $lot['lot_number'] }} (exp: {{ $lot['expiry_date'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Target (kg)") }}</label>
                                <input type="number" readonly step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-600 text-neutral-800 dark:text-neutral-200 cursor-not-allowed" wire:model="weight_target_right_b">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __("Weight Actual (kg)") }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input id="weight_actual_right_b" type="number" step="0.01" readonly
                                        class="block w-full rounded-md border-gray-300 dark:border-neutral-700 text-neutral-800 dark:text-neutral-200"
                                        :class="lockedFields.weight_actual_right_b ? 'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 font-semibold' : 'bg-white dark:bg-neutral-700'"
                                        :value="lockedFields.weight_actual_right_b ? $wire.weight_actual_right_b : latestScaleWeight"
                                        wire:model="weight_actual_right_b">
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" x-show="!lockedFields.weight_actual_right_b" @click="setWeight('weight_actual_right_b')" class="px-3 py-1 text-sm rounded bg-caldy-500 hover:bg-caldy-600 text-white cursor-pointer">
                                        {{ __('Set Weight') }}
                                    </button>
                                    <button type="button" x-show="lockedFields.weight_actual_right_b" @click="unlockWeight('weight_actual_right_b')" class="px-3 py-1 text-sm rounded bg-amber-500 hover:bg-amber-600 text-white cursor-pointer">
                                        {{ __('Unlock') }}
                                    </button>
                                    <span class="self-center text-xs text-neutral-400" x-text="'Scale: ' + latestScaleWeight + ' g'"></span>
                                    <span x-show="stableTargetField === 'weight_actual_right_b' && stableCountdown > 0" x-transition class="self-center text-xs font-semibold text-blue-500" x-text="'⏱ ' + stableCountdown + 's'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Validation alerts --}}
                    @php
                        $rightWeightErrors = [];
                        $rightUpDev = (float) ($selectedRecipeRight['up_dev'] ?? 0);
                        $rightLowDev = (float) ($selectedRecipeRight['low_dev'] ?? 0);

                        // Lot number required
                        if ($stock_id_right_a === '') {
                            $rightWeightErrors[] = __('Chemical A: Lot number is required');
                        }
                        if ($stock_id_right_b === '') {
                            $rightWeightErrors[] = __('Chemical B: Lot number is required');
                        }

                        // Weight actual required
                        if ($weight_actual_right_a === '') {
                            $rightWeightErrors[] = __('Chemical A: Weight actual is required');
                        }
                        if ($weight_actual_right_b === '') {
                            $rightWeightErrors[] = __('Chemical B: Weight actual is required');
                        }

                        // Weight deviation check
                        if ($weight_target_right_a !== '' && $weight_actual_right_a !== '') {
                            $targetA = (float) $weight_target_right_a;
                            $actualA = (float) $weight_actual_right_a;
                            $lowerA = $targetA - $rightLowDev;
                            $upperA = $targetA + $rightUpDev;
                            if ($actualA < $lowerA) {
                                $rightWeightErrors[] = __('Chemical A: Actual weight (:actual g) is below lower limit (:lower g)', ['actual' => $weight_actual_right_a, 'lower' => round($lowerA, 2)]);
                            } elseif ($actualA > $upperA) {
                                $rightWeightErrors[] = __('Chemical A: Actual weight (:actual g) exceeds upper limit (:upper g)', ['actual' => $weight_actual_right_a, 'upper' => round($upperA, 2)]);
                            }
                        }
                        if ($weight_target_right_b !== '' && $weight_actual_right_b !== '') {
                            $targetB = (float) $weight_target_right_b;
                            $actualB = (float) $weight_actual_right_b;
                            $lowerB = $targetB - $rightLowDev;
                            $upperB = $targetB + $rightUpDev;
                            if ($actualB < $lowerB) {
                                $rightWeightErrors[] = __('Chemical B: Actual weight (:actual g) is below lower limit (:lower g)', ['actual' => $weight_actual_right_b, 'lower' => round($lowerB, 2)]);
                            } elseif ($actualB > $upperB) {
                                $rightWeightErrors[] = __('Chemical B: Actual weight (:actual g) exceeds upper limit (:upper g)', ['actual' => $weight_actual_right_b, 'upper' => round($upperB, 2)]);
                            }
                        }
                        $rightWeightValid = empty($rightWeightErrors);
                        $rightCanStart = $isAuthenticated && $recipe_id_right && $rightWeightValid;
                    @endphp

                    @if(!empty($rightWeightErrors))
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-sm space-y-1">
                        <div class="flex items-center gap-2 font-semibold">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                            {{ __('Weight out of tolerance') }}
                        </div>
                        @foreach($rightWeightErrors as $err)
                            <div class="ml-6 text-xs">{{ $err }}</div>
                        @endforeach
                        <div class="ml-6 text-xs text-neutral-500">{{ __('Tolerance: -:low / +:up', ['low' => $rightLowDev, 'up' => $rightUpDev]) }}</div>
                    </div>
                    @endif

                    {{-- Start button --}}
                    <div class="pt-4">
                        <button type="button"
                            @click="confirmStartRight()"
                            {{ !$rightCanStart ? 'disabled' : '' }}
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-semibold text-white text-sm transition-colors
                                {{ $rightCanStart ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"></polygon></svg>
                            {{ __('Start Right Mixing') }}
                        </button>
                    </div>
                    @endif
                </div>

            @else
                {{-- ──── TIMER / RESULT PHASE ──── --}}
                <div x-data="{ detailOpen: false }">
                    {{-- Waiting for micon --}}
                    <div x-show="rightWaiting && !rightRunning && !rightFinished" x-transition class="px-5 pt-4 pb-2">
                        <div class="flex items-center gap-3 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300">
                            <svg class="animate-spin h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span class="font-semibold text-sm">{{ __('Waiting for micon response...') }}</span>
                        </div>
                    </div>

                    {{-- Progress + timer --}}
                    <div class="px-5 pt-4 pb-2" x-show="!rightWaiting || rightRunning || rightFinished">
                        <div class="flex items-end justify-between mb-1">
                            <span class="text-3xl font-bold tabular-nums" x-text="rightTimeDisplay"
                                  :class="rightFinished || @js($completedRight) ? 'text-green-500' : (miconB.em === 1 ? 'text-red-500' : 'text-emerald-500')"></span>
                            <span class="text-sm text-neutral-400 mb-1" x-text="Math.round(rightProgress) + '%'"></span>
                        </div>
                        <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-1000"
                                 :class="rightFinished || @js($completedRight) ? 'bg-green-500' : (miconB.em === 1 ? 'bg-red-500' : 'bg-emerald-500')"
                                 :style="'width:' + rightProgress + '%'"></div>
                        </div>
                    </div>

                    {{-- Chemical summary --}}
                    <button type="button" @click="detailOpen = !detailOpen"
                            class="w-full flex items-center justify-between px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors text-left">
                        <div class="flex gap-4 text-sm">
                            <div>
                                <div class="text-xs text-neutral-400 uppercase tracking-wide">A</div>
                                <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $chemical_name_right_a ?: '-' }}</div>
                                <div class="text-xs text-neutral-500">{{ $weight_actual_right_a ?: '-' }} g</div>
                            </div>
                            <div class="self-center text-neutral-300 dark:text-neutral-600 font-bold">+</div>
                            <div>
                                <div class="text-xs text-neutral-400 uppercase tracking-wide">B</div>
                                <div class="font-semibold text-neutral-700 dark:text-neutral-200 truncate max-w-[120px]">{{ $chemical_name_right_b ?: '-' }}</div>
                                <div class="text-xs text-neutral-500">{{ $weight_actual_right_b ?: '-' }} g · {{ $percentage_right ?: '-' }}%</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-neutral-400 transition-transform" :class="detailOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    {{-- Expandable detail --}}
                    <div x-show="detailOpen" x-collapse class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4 space-y-4 text-sm">
                        <div>
                            <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical A') }}</div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <span class="text-neutral-500">{{ __('Item Code') }}</span><span class="font-mono">{{ $item_code_right_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $lot_number_right_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $exp_date_right_a ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $weight_target_right_a ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $weight_actual_right_a ?: '-' }} g</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2">{{ __('Chemical B') }}</div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <span class="text-neutral-500">{{ __('Item Code') }}</span><span class="font-mono">{{ $item_code_right_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Lot No.') }}</span><span class="font-mono">{{ $lot_number_right_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Exp Date') }}</span><span>{{ $exp_date_right_b ?: '-' }}</span>
                                <span class="text-neutral-500">{{ __('Target') }}</span><span>{{ $weight_target_right_b ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Actual') }}</span><span class="font-semibold">{{ $weight_actual_right_b ?: '-' }} g</span>
                                <span class="text-neutral-500">{{ __('Ratio B') }}</span><span>{{ $percentage_right ?: '-' }} %</span>
                            </div>
                        </div>
                    </div>

                    {{-- Failed banner --}}
                    @if($failedRight)
                    <div class="border-t border-red-200 dark:border-red-700 px-5 py-4 bg-red-50 dark:bg-red-900/30 flex items-center gap-3">
                        <span class="text-2xl">🚨</span>
                        <div>
                            <div class="font-semibold text-red-700 dark:text-red-300">{{ __('Emergency Stop — Mixing Failed') }}</div>
                            <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">{{ __('This mixing has been logged as failed.') }}</div>
                        </div>
                    </div>
                    @endif

                    {{-- Footer: action / result --}}
                    <div x-show="rightFinished || @js($completedRight) || @js($failedRight)" x-transition class="border-t border-neutral-100 dark:border-neutral-700 px-5 py-4">
                        @if(!$completedRight && !$failedRight)
                            @if($completeErrorRight)
                                <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $completeErrorRight }}</p>
                            @endif
                            <button wire:click="completeMixingRight" wire:loading.attr="disabled"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold rounded-lg text-sm transition-colors">
                                <span wire:loading.remove wire:target="completeMixingRight">{{ __('Save & Complete') }}</span>
                                <span wire:loading wire:target="completeMixingRight" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    {{ __('Saving...') }}
                                </span>
                            </button>
                        @elseif($completedRight)
                            <div class="space-y-2">
                                <div class="flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-3 py-2">
                                    <div>
                                        <div class="text-xs text-green-600 dark:text-green-400 font-semibold uppercase tracking-wide">{{ __('Output created') }}</div>
                                        <div class="font-mono font-bold text-green-700 dark:text-green-300">{{ $completedStockRight['output_code'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-500">{{ __('Stock') }} #{{ $completedStockRight['stock_id'] ?? '' }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-green-600 dark:text-green-400">+{{ $completedStockRight['quantity'] ?? '' }}</div>
                                        <div class="text-xs text-neutral-500">{{ $completedStockRight['uom'] ?? '' }}</div>
                                    </div>
                                </div>
                                @foreach([($completedStockRight['decrease_a'] ?? null), ($completedStockRight['decrease_b'] ?? null)] as $d)
                                    @if(!empty($d) && !isset($d['warning']))
                                    <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-3 py-2">
                                        <div>
                                            <div class="text-xs text-red-500 font-semibold uppercase tracking-wide">{{ __('Consumed') }}</div>
                                            <div class="font-mono font-medium text-neutral-700 dark:text-neutral-300">{{ $d['item_code'] ?? '' }}</div>
                                            @if(!empty($d['lot_number'])) <div class="text-xs text-neutral-400">{{ __('Lot') }}: {{ $d['lot_number'] }}</div> @endif
                                        </div>
                                        <div class="text-right text-sm">
                                            <div class="text-red-500 font-bold">−{{ $d['used'] ?? '' }}</div>
                                            <div class="text-xs text-neutral-400">{{ $d['before'] ?? '' }} → {{ $d['after'] ?? '' }}</div>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                                <button type="button" wire:click="resetRight"
                                    class="mt-2 w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-caldy-500 hover:bg-caldy-600 text-white font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    {{ __('New Right Mixing') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
