<?php

use App\Models\InsRdcTest;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public int $rdc_test_id;

    public array $test = [
        "ins_rubber_batch" => [
            "code" => "",
            "code_alt" => "",
            "rdc_eval" => "",
            "rdc_eval_human" => "",
            "model" => "",
            "color" => "",
            "mcs" => "",
            "rdc_test_count" => "",
        ],

        "ins_rdc_machine" => [
            "number" => "",
            "name" => "",
        ],

        "user" => [
            "emp_id" => "",
            "name" => "",
        ],

        "queued_at" => "",
        "updated_at" => "",

        "s_max_low" => "",
        "s_max_high" => "",
        "s_min_low" => "",
        "s_min_high" => "",

        "tc10_low" => "",
        "tc10_high" => "",
        "tc50_low" => "",
        "tc50_high" => "",
        "tc90_low" => "",
        "tc90_high" => "",

        "type" => "",
        "s_max" => "",
        "s_min" => "",
        "tc10" => "",
        "tc50" => "",
        "tc90" => "",
        "eval" => "",
    ];

    public function mount()
    {
        if ($this->rdc_test_id) {
            $this->show(view: "rdc", rdc_test_id: $this->rdc_test_id);
        }
    }

    #[On("batch-show")]
    public function show(string $view = "", int $rdc_test_id = 0)
    {
        if ($view !== "rdc") {
            return;
        }

        $this->customReset();

        $test = InsRdcTest::with(["ins_rubber_batch", "user", "ins_rdc_machine"])->find($rdc_test_id);
        $testArray = $test->toArray();

        if ($testArray) {
            foreach ($testArray as $key => $value) {
                // Check if the key exists in $this->test
                if (array_key_exists($key, $this->test)) {
                    // Now check if the value is an array (for nested properties)
                    if (is_array($value) && is_array($this->test[$key])) {
                        // Iterate through the nested array and assign values
                        foreach ($value as $subKey => $subValue) {
                            if (array_key_exists($subKey, $this->test[$key])) {
                                $this->test[$key][$subKey] = $subValue;
                            }
                        }
                    } else {
                        // If it's not a nested array, just set the value directly
                        $this->test[$key] = $value;
                    }
                }
            }

            $evalHuman = $test->evalHuman();

            $this->test["updated_at"] = Carbon::parse($this->test["updated_at"])
                ->setTimezone("Asia/Jakarta")
                ->format("Y-m-d H:i:s");
            $this->test["eval_human"] = $evalHuman;
        } else {
            $this->customReset();
        }
    }

    public function customReset()
    {
        $this->reset(["rdc_test_id", "test"]);
    }
};
?>

<div>
    <div class="flex w-full justify-between p-3 border border-neutral-300 dark:border-neutral-700 rounded-full">
        <div class="flex items-center text-xs uppercase text-neutral-500 dark:text-neutral-400 divide-x divide-neutral-300 dark:divide-neutral-700">
            <div class="px-2 text-neutral-900 dark:text-white">{{ __("Rheometer") }}</div>
            <div class="px-2">{{ $test["ins_rubber_batch"]["model"] }}</div>
            <div class="px-2">{{ $test["ins_rubber_batch"]["color"] }}</div>
            <div class="px-2">{{ $test["ins_rubber_batch"]["mcs"] }}</div>
        </div>
        <div>
            <x-pill class="block uppercase" color="{{ $test['eval'] === 'queue' ? 'yellow' : ($test['eval'] === 'pass' ? 'green' : ($test['eval'] === 'fail' ? 'red' : '')) }}">
                {{ $test["eval_human"] }}
            </x-pill>
        </div>
    </div>
    <div class="grid grid-cols-2 mt-8">
        <div>
            <div>
                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                    {{ __("Kode alt") . ": " }}
                </span>
                <span>
                    {{ $test["ins_rubber_batch"]["code_alt"] }}
                </span>
            </div>
            <div>
                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                    {{ __("Mesin") . ": " }}
                </span>
                <span>
                    {{ $test["ins_rdc_machine"]["number"] . ". " . $test["ins_rdc_machine"]["name"] }}
                </span>
            </div>
            <div>
                <span class="text-neutral-500 dark:text-neutral-400 text-sm">
                    {{ __("Penguji") . ": " }}
                </span>
                <span>
                    {{ $test["user"]["name"] . " (" . $test["user"]["emp_id"] . ")" }}
                </span>
            </div>
        </div>
        <div>
            <table class="table-auto">
                <tr>
                    <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                        {{ __("Tipe") . ": " }}
                    </td>
                    <td class="font-mono">
                        {{ $test["type"] }}
                    </td>
                </tr>
                <tr>
                    <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                        {{ __("Antri") . ": " }}
                    </td>
                    <td class="font-mono">
                        {{ $test["queued_at"] }}
                    </td>
                </tr>
                <tr>
                    <td class="text-neutral-500 dark:text-neutral-400 text-sm pr-4">
                        {{ __("Selesai") . ": " }}
                    </td>
                    <td class="font-mono">
                        {{ $test["updated_at"] }}
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <table class="table table-sm text-sm mt-6 text-center">
        <tr class="text-xs uppercase text-neutral-500 dark:text-neutral-400 border-b border-neutral-300 dark:border-neutral-700">
            <td></td>
            <td>{{ __("S Maks") }}</td>
            <td>{{ __("S Min") }}</td>
            <td>{{ __("TC10") }}</td>
            <td>{{ __("TC50") }}</td>
            <td>{{ __("TC90") }}</td>
        </tr>
        <tr>
            <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("Standar") }}</td>
            <td>{{ $test["s_max_low"] . " - " . $test["s_max_high"] }}</td>
            <td>{{ $test["s_min_low"] . " - " . $test["s_min_high"] }}</td>
            <td>{{ $test["tc10_low"] . " - " . $test["tc10_high"] }}</td>
            <td>{{ $test["tc50_low"] . " - " . $test["tc50_high"] }}</td>
            <td>{{ $test["tc90_low"] . " - " . $test["tc90_high"] }}</td>
        </tr>
        <tr>
            <td class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ __("Hasil") }}</td>
            <td>{{ $test["s_max"] }}</td>
            <td>{{ $test["s_min"] }}</td>
            <td>{{ $test["tc10"] }}</td>
            <td>{{ $test["tc50"] }}</td>
            <td>{{ $test["tc90"] }}</td>
        </tr>
    </table>
</div>
