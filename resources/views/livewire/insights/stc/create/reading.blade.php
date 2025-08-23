<?php

use App\Caldera;
use App\InsStc;
use App\InsStcPush;
use App\Models\InsStcDevice;
use App\Models\InsStcDLog;
use App\Models\InsStcDSum;
use App\Models\InsStcMachine;
use App\Models\InsClmRecord;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use ModbusTcpClient\Network\NonBlockingClient;

new class extends Component {
    use WithFileUploads;

    public array $machines = [];

    public array $logs = [];

    public array $chart_logs = [];

    public string $device_code = "";

    public $file;

    public array $d_sum = [
        "started_at" => "",
        "ended_at" => "",
        "speed" => "",
        "sequence" => "",
        "position" => "",
        "sv_values" => [],
        "sv_used" => "d_sum",
        "sv_used_friendly" => "",
        "formula_id" => 412,
        "is_applied" => "",
        "target_values" => [],
        "hb_values" => [],
        "svp_values" => [],
        "svp_values_rel" => ["", "", "", "", "", "", "", ""],
        "ins_stc_machine_id" => "",
        "ins_stc_device_id" => "",
    ];

    public string $latency = "";

    public string $duration = "";

    public int $duration_min = 0;

    public string $sv_error_msg = "";

    public function mount()
    {
        $this->machines = InsStcMachine::whereNot("code", "like", "test%")
            ->orderBy("line")
            ->get()
            ->toArray();
        $this->checkRecents();
        $this->d_sum["sv_used_friendly"] = __("SV manual");
    }

    public function updated($property)
    {
        // check m_log
        $check_m_log_props = ["d_sum.ins_stc_machine_id", "d_sum.position"];
        if (in_array($property, $check_m_log_props)) {
            $this->check_m_log_sv();
            $this->calculatePrediction();
        }

        // check file upload
        if ($property == "file") {
            // $this->resetErrorBag();
            $this->validate([
                "file" => "file|mimes:csv|max:1024",
            ]);

            try {
                $this->extractData();
            } catch (Exception $e) {
                $this->js('console.log("' . $e->getMessage() . '")');
            }

            $this->calculatePrediction();
        }
    }

    private function check_m_log_sv()
    {
        $this->d_sum["sv_values"] = [];
        $this->d_sum["sv_used"] = "d_sum";
        $this->d_sum["sv_friendly"] = "";

        $machine_id = $this->d_sum["ins_stc_machine_id"];
        $position = $this->d_sum["position"];

        if ($machine_id && $position) {
            try {
                $machine = InsStcMachine::find($machine_id);
                $ip = $machine->ip_address;
                $port = 503;
                $unit_id = 1;
                $sv_r_request = InsStc::buildRegisterRequest($position . "_sv_r", $ip, $port, $unit_id);
                $sv_r = [];

                if (strpos($ip, "127.") !== 0) {
                    $sv_r_response = (new NonBlockingClient(["readTimeoutSec" => 2]))->sendRequests($sv_r_request);
                    $sv_r_data = $sv_r_response->getData();
                    $sv_r = array_values($sv_r_data);
                } else {
                    $this->js('toast("' . __("SV tidak tersedia pada mesin dan posisi yang dipilih.") . '", { type: "info" })');
                }

                if ($sv_r) {
                    $allAboveZero = true;
                    foreach ($sv_r as $value) {
                        if ($value <= 0) {
                            $allAboveZero = false;
                            $this->js(
                                'toast("' . __("SV dari mesin dan posisi yang dipilih tidak sah karena mengandung nilai kurang dari atau sama dengan 0.") . '", { type: "info" })',
                            );
                            break;
                        }
                    }

                    if ($allAboveZero) {
                        $this->d_sum["sv_values"] = $sv_r;
                        $this->d_sum["sv_used"] = "m_log";
                        $this->d_sum["sv_used_friendly"] = __("SV otomatis");
                        $this->js('toast("' . __("SV dari mesin dan posisi yang dipilih berhasil diambil.") . '", { type: "info" })');
                    }
                }
            } catch (\Throwable $th) {
                $this->sv_error_msg = $th->getMessage();
                $this->js('$dispatch("open-modal", "retrieve-sv-error")');
            }
        }
    }

    private function extractData()
    {
        // Configuration constants - moved to the top for easy modification
        $skipRows = 3;
        $tempColumn = 3;
        $maxLogs = 100;
        $minRequiredLogs = 20;
        $excelEpochOffset = 25569; // Days between Unix epoch and Excel epoch
        $secondsPerDay = 86400;
        $minTemp = 0;
        $maxTemp = 99;
        $stdDevThreshold = 2.0; // Standard deviation threshold to consider fluctuation significant
        $minTempLimit = 32;
        $maxTempLimit = 42;

        try {
            // Check if file exists
            $filePath = $this->file->getPathname();
            if (! file_exists($filePath)) {
                throw new \Exception(__("Berkas tidak ditemukan"));
            }

            // Read and parse CSV file
            $csvData = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($csvData === false) {
                throw new \Exception(__("Gagal membaca berkas CSV"));
            }

            $rows = array_map("str_getcsv", $csvData);

            // Skip header rows
            $rows = array_slice($rows, $skipRows);

            // Process valid rows
            $logs = [];
            $uniqueTimestamps = []; // Track unique timestamps
            foreach ($rows as $row) {
                // Validate row data
                if (! isset($row[0], $row[1], $row[$tempColumn]) || empty($row[0]) || ! is_numeric($row[1]) || ! is_numeric($row[$tempColumn])) {
                    continue;
                }

                // Convert Excel timestamp to Unix timestamp
                $excelTimestamp = (float) $row[1];

                // fix chart issue with 3 decimal places rounding
                $excelTimestamp = round($excelTimestamp, 4);

                // Skip duplicate timestamps
                if (in_array($excelTimestamp, $uniqueTimestamps)) {
                    continue;
                }

                $uniqueTimestamps[] = $excelTimestamp;
                $unixTimestamp = ($excelTimestamp - $excelEpochOffset) * $secondsPerDay;

                // Format date
                $takenAt = Carbon::createFromTimestamp($unixTimestamp)->format("Y-m-d H:i");

                // Normalize temperature within valid range
                $temperature = max($minTemp, min($maxTemp, (float) $row[$tempColumn]));

                $logs[] = [
                    "taken_at" => $takenAt,
                    "temp" => $temperature,
                    "timestamp" => $excelTimestamp,
                ];
            }

            // Sort logs chronologically
            usort($logs, fn ($a, $b) => $a["timestamp"] <=> $b["timestamp"]);

            // Limit to maximum number of logs
            $logs = array_slice($logs, 0, $maxLogs);

            // Check if we have enough data
            if (count($logs) < $minRequiredLogs) {
                $this->showError(__("Tidak cukup data yang sah ditemukan"));
                return null;
            }

            // Get total number of logs
            $totalLogs = count($logs);
            // Start with half the logs
            $halfIndex = intval($totalLogs / 2);

            // Initialize array to store end temperatures from different calculations
            $endTempResults = [];

            // APPROACH 1: Check decreasing window sizes from half logs down to 2
            for ($windowSize = $halfIndex; $windowSize >= 2; $windowSize--) {
                // Get the last $windowSize temperatures
                $lastTemps = array_slice(array_column($logs, "temp"), -$windowSize);
                // Calculate end temperature for this window
                $endTemp = $this->calculateEndTemp($lastTemps, $stdDevThreshold, $minTempLimit, $maxTempLimit);
                // Store the result
                $endTempResults[] = $endTemp;
            }

            // APPROACH 2: Sliding window of 10 from halfway point to end
            $windowSize = 10;
            // Only proceed if we have enough logs for this approach
            if ($totalLogs >= $halfIndex + $windowSize) {
                for ($startIndex = $halfIndex; $startIndex <= $totalLogs - $windowSize; $startIndex++) {
                    // Get the window of temperatures
                    $windowTemps = array_slice(array_column($logs, "temp"), $startIndex, $windowSize);
                    // Calculate end temperature for this window
                    $endTemp = $this->calculateEndTemp($windowTemps, $stdDevThreshold, $minTempLimit, $maxTempLimit);
                    // Store the result
                    $endTempResults[] = $endTemp;
                }
            }

            // Find the maximum end temperature from both approaches
            $endTemp = ! empty($endTempResults) ? max($endTempResults) : $minTempLimit;

            $filteredLogs = [];
            $rejectedLogs = [];

            // First pass: filter based on position and temperature
            foreach ($logs as $index => $log) {
                // Keep first half logs or second half logs with sufficient temperature
                if ($index < $halfIndex || $log["temp"] > $endTemp) {
                    $filteredLogs[] = $log;
                } else {
                    $rejectedLogs[] = $log;
                }
            }

            // Add the first 2 rejected logs to our filtered results
            $firstRejected = array_slice($rejectedLogs, 0, 2);
            $filteredLogs = array_merge($filteredLogs, $firstRejected);

            if (count($filteredLogs) < 20) {
                $this->js('toast("' . __("Baris data yang sah kurang dari 20") . '", { type: "danger" })');
            }

            $temps = array_map(fn ($item) => $item["temp"], $filteredLogs);
            $medians = InsStc::getMediansBySection($temps);

            $validator = Validator::make(
                [
                    "started_at" => $filteredLogs[0]["taken_at"],
                    "ended_at" => $filteredLogs[array_key_last($filteredLogs)]["taken_at"],
                    "preheat" => $medians["preheat"],
                    "section_1" => $medians["section_1"],
                    "section_2" => $medians["section_2"],
                    "section_3" => $medians["section_3"],
                    "section_4" => $medians["section_4"],
                    "section_5" => $medians["section_5"],
                    "section_6" => $medians["section_6"],
                    "section_7" => $medians["section_7"],
                    "section_8" => $medians["section_8"],
                    "postheat" => $medians["postheat"],
                ],
                [
                    "started_at" => "required|date",
                    "ended_at" => "required|date|after:started_at",
                    "preheat" => "required|numeric|min:1|max:99",
                    "section_1" => "required|numeric|min:1|max:99",
                    "section_2" => "required|numeric|min:1|max:99",
                    "section_3" => "required|numeric|min:1|max:99",
                    "section_4" => "required|numeric|min:1|max:99",
                    "section_5" => "required|numeric|min:1|max:99",
                    "section_6" => "required|numeric|min:1|max:99",
                    "section_7" => "required|numeric|min:1|max:99",
                    "section_8" => "required|numeric|min:1|max:99",
                    "postheat" => "nullable|numeric|min:1|max:99",
                ],
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first();
                $this->js('toast("' . $error . '", { type: "danger" })');
                $this->reset(["file"]);
            } else {
                $this->logs = $filteredLogs;
                $validatedData = $validator->validated();
                $started_at = Carbon::parse($validatedData["started_at"]);
                $ended_at = Carbon::parse($validatedData["ended_at"]);
                $this->d_sum["started_at"] = $validatedData["started_at"];
                $this->d_sum["ended_at"] = $validatedData["ended_at"];
                $this->d_sum["hb_values"][0] = $validatedData["section_1"];
                $this->d_sum["hb_values"][1] = $validatedData["section_2"];
                $this->d_sum["hb_values"][2] = $validatedData["section_3"];
                $this->d_sum["hb_values"][3] = $validatedData["section_4"];
                $this->d_sum["hb_values"][4] = $validatedData["section_5"];
                $this->d_sum["hb_values"][5] = $validatedData["section_6"];
                $this->d_sum["hb_values"][6] = $validatedData["section_7"];
                $this->d_sum["hb_values"][7] = $validatedData["section_8"];
                $this->duration = $started_at->diff($ended_at)->format("%H:%I:%S");
                $this->duration_min = (int) round($started_at->diffInMinutes($ended_at));
                $this->latency = InsStc::duration($validatedData["ended_at"], Carbon::now(), "short");

                // prepare for HMI charts
                $chart_logs = array_map(function ($item) {
                    return (int) round($item["temp"]);
                }, $filteredLogs);

                $chart_length = 60;

                if (count($chart_logs) < $chart_length) {
                    $chart_logs = array_pad($chart_logs, $chart_length, 0);
                } elseif (count($chart_logs) > $chart_length) {
                    $chart_logs = array_slice($chart_logs, 0, $chart_length);
                }

                $this->chart_logs = $chart_logs;
            }
        } catch (\Exception $e) {
            $this->showError(__("Galat saat memproses data: ") . $e->getMessage());
            return null;
        }
    }

    private function showError(string $message): void
    {
        $this->js('toast("' . $message . '", { type: "danger" })');
    }

    private function calculateEndTemp(array $temperatures, float $stdDevThreshold, float $minTempLimit, float $maxTempLimit): float
    {
        // Calculate mean
        $count = count($temperatures);
        $sum = array_sum($temperatures);
        $mean = $sum / $count;

        // Calculate standard deviation
        $variance = 0;
        foreach ($temperatures as $temp) {
            $variance += pow($temp - $mean, 2);
        }
        $stdDev = sqrt($variance / $count);

        // If standard deviation is high, use max temperature + 1
        // Otherwise use a default value (38) which was in the original code
        $endTemp = $minTempLimit;
        if ($stdDev < $stdDevThreshold) {
            $endTemp = ceil(max($temperatures));
        }

        $cappedEndTemp = max($minTempLimit, min($endTemp, $maxTempLimit));

        return $cappedEndTemp;
    }

    private function resetPrediction()
    {
        $this->d_sum["svp_values"] = [];
        $this->d_sum["svp_values_rel"] = ["", "", "", "", "", "", "", ""];
    }

    public function calculatePrediction()
    {
        $this->resetPrediction();
        $this->validateBeforePredict();

        // Get machine-specific limits if machine is selected
        $svp_highs = null;
        $svp_lows = null;

        if (! empty($this->d_sum["ins_stc_machine_id"])) {
            $machine = InsStcMachine::find($this->d_sum["ins_stc_machine_id"]);
            if ($machine) {
                $svp_highs = $machine->section_limits_high;
                $svp_lows = $machine->section_limits_low;
            }
        }

        $svp_values = InsStc::calculateSVP($this->d_sum["hb_values"], $this->d_sum["sv_values"], $this->d_sum["formula_id"], $svp_highs, $svp_lows);
        $this->d_sum["svp_values"] = array_map(function ($item) {
            return $item["absolute"];
        }, $svp_values);
        $this->d_sum["svp_values_rel"] = array_map(function ($item) {
            return $item["relative"];
        }, $svp_values);
    }

    private function validateBeforePredict()
    {
        $this->validate([
            "d_sum.hb_values" => "required|array|size:8",
            "d_sum.hb_values.*" => "required|numeric|min:30|max:99",
            "d_sum.sv_values" => "required|array|size:8",
            "d_sum.sv_values.*" => "required|numeric|min:30|max:99",
            "d_sum.formula_id" => "required|in:411,412,421",
        ]);
    }

    private function validateAfterPredict()
    {
        $this->validate([
            "d_sum.svp_values" => "required|array|size:8",
            "d_sum.svp_values.*" => "required|numeric|min:30|max:99",
        ]);
    }

    public function send()
    {
        $this->validate([
            "d_sum.sequence" => ["required", "integer", "min:1", "max:2"],
            "d_sum.speed" => ["required", "numeric", "min:0.1", "max:0.9"],
            "d_sum.ins_stc_machine_id" => ["required", "integer", "exists:ins_stc_machines,id"],
            "d_sum.position" => ["required", "in:upper,lower"],
            "device_code" => ["required", "exists:ins_stc_devices,code"],
        ]);

        $this->validateBeforePredict();
        $this->validateAfterPredict();

        $is_applied = $this->push();

        $this->save($is_applied);
        $this->checkRecents();

        if ($is_applied) {
            $this->js('toast("' . __("Berhasil") . '", { description: "' . __("Data tersimpan dan mesin telah disetel dengan SV prediksi") . '", type: "success" })');
        } else {
            $this->js(
                'toast("' .
                    __("Berhasil (tanpa penyetelan)") .
                    '", { description: "' .
                    __("Data tersimpan namun tidak dilakukan penyetelan pada mesin. Periksa console.") .
                    '", type: "success" })',
            );
        }

        $this->reset(["logs", "chart_logs", "device_code", "file", "d_sum", "latency", "duration", "duration_min"]);
    }

    private function save(bool $is_applied)
    {
        $d_sum = new InsStcDsum();
        Gate::authorize("manage", $d_sum);

        $d_sum_before = InsStcDSum::where("created_at", "<", Carbon::now())
            ->where("ins_stc_machine_id", $this->d_sum["ins_stc_machine_id"])
            ->where("position", $this->d_sum["position"])
            ->where("created_at", ">=", Carbon::now()->subHours(6))
            ->orderBy("created_at", "desc")
            ->first();

        $svp_before = $d_sum_before ? json_decode($d_sum_before->svp_values, true) : [];
        $sv_now = $this->d_sum["sv_values"];

        $integrity = "none";

        if (count($sv_now) === count($svp_before) && count($sv_now) === 8) {
            $is_stable = true;

            foreach ($sv_now as $key => $value) {
                if (! isset($svp_before[$key]) || abs($value - $svp_before[$key]) > 2) {
                    $is_stable = false;
                    break;
                }
            }

            if ($is_stable) {
                $integrity = "stable";
            } else {
                $integrity = "modified";
            }
        }

        // Calculate AT values
        $at_values = $this->calculateAtValues();

        $device = InsStcDevice::where("code", $this->device_code)->first();

        $d_sum->fill([
            "ins_stc_device_id" => $device->id,
            "ins_stc_machine_id" => $this->d_sum["ins_stc_machine_id"],
            "user_id" => Auth::user()->id,
            "started_at" => $this->d_sum["started_at"],
            "ended_at" => $this->d_sum["ended_at"],

            "speed" => $this->d_sum["speed"],
            "sequence" => $this->d_sum["sequence"],
            "position" => $this->d_sum["position"],
            "sv_values" => json_encode($this->d_sum["sv_values"]),
            "formula_id" => $this->d_sum["formula_id"],
            "sv_used" => $this->d_sum["sv_used"],
            "target_values" => json_encode(InsStc::$target_values),
            "hb_values" => json_encode($this->d_sum["hb_values"]),
            "svp_values" => json_encode($this->d_sum["svp_values"]),
            "at_values" => json_encode($at_values),
            "integrity" => $integrity,
            "is_applied" => $is_applied,
        ]);

        $d_sum->save();

        // is_applied dan integrity nya belum
        foreach ($this->logs as $log) {
            InsStcDLog::create([
                "ins_stc_d_sum_id" => $d_sum->id,
                "taken_at" => $log["taken_at"],
                "temp" => $log["temp"],
            ]);
        }
    }

    private function calculateAtValues(): array
    {
        // Initialize AT values array [previous_at, current_at, delta_at]
        $at_values = [0.0, 0.0, 0.0];

        try {
            // Element 0: Get AT from previous d_sum (same machine and position)
            $previous_d_sum = InsStcDSum::where("ins_stc_machine_id", $this->d_sum["ins_stc_machine_id"])
                ->where("position", $this->d_sum["position"])
                ->where("created_at", "<", Carbon::now())
                ->orderBy("created_at", "desc")
                ->first();

            if ($previous_d_sum) {
                // Use the model's accessor method and format to 1 decimal place
                $at_values[0] = round((float) $previous_d_sum->current_at, 1);
            }
        } catch (Exception $e) {
            // Log the error and keep default value 0.0 for element 0
            \Log::info("AT calculation: Failed to get previous d_sum AT", [
                "machine_id" => $this->d_sum["ins_stc_machine_id"],
                "position" => $this->d_sum["position"],
                "error" => $e->getMessage(),
            ]);
        }

        try {
            // Element 1: Get latest temperature from ins_clm_records (within 1 hour)
            $latest_clm_record = InsClmRecord::where("created_at", ">=", Carbon::now()->subHour())
                ->orderBy("created_at", "desc")
                ->first();

            if ($latest_clm_record && $latest_clm_record->temperature > 0) {
                // Format to 1 decimal place
                $at_values[1] = round((float) $latest_clm_record->temperature, 1);
            } else {
                \Log::info("AT calculation: No recent CLM record found or temperature <= 0", [
                    "found_record" => ! ! $latest_clm_record,
                    "temperature" => $latest_clm_record->temperature ?? "N/A",
                ]);
            }
        } catch (Exception $e) {
            // Log the error and keep default value 0.0 for element 1
            \Log::info("AT calculation: Failed to get current ambient temperature", [
                "error" => $e->getMessage(),
            ]);
        }

        try {
            // Element 2: Calculate delta AT with safeguards
            if ($at_values[0] > 0 && $at_values[1] > 0) {
                $delta = $at_values[1] - $at_values[0];

                // Apply safeguard: if delta is too aggressive (> 10 or < -10), set to 0
                if ($delta > 10 || $delta < -10) {
                    $at_values[2] = 0.0;
                    \Log::info("AT calculation: Delta AT too aggressive, set to 0", [
                        "previous_at" => $at_values[0],
                        "current_at" => $at_values[1],
                        "calculated_delta" => $delta,
                    ]);
                } else {
                    // Format to 1 decimal place
                    $at_values[2] = round($delta, 1);
                }
            } else {
                \Log::info("AT calculation: Delta set to 0 due to invalid previous or current AT", [
                    "previous_at" => $at_values[0],
                    "current_at" => $at_values[1],
                ]);
            }
            // If either element 0 or 1 is <= 0, delta remains 0.0 (already initialized)
        } catch (Exception $e) {
            // Log the error and keep default value 0.0 for element 2
            \Log::info("AT calculation: Failed to calculate delta AT", [
                "error" => $e->getMessage(),
            ]);
        }

        // Always log the final AT values for debugging
        \Log::info("AT calculation completed", [
            "machine_id" => $this->d_sum["ins_stc_machine_id"],
            "position" => $this->d_sum["position"],
            "at_values" => $at_values,
        ]);

        return $at_values;
    }

    private function push(): bool
    {
        $machine = InsStcMachine::find($this->d_sum["ins_stc_machine_id"]);
        $push = new InsStcPush();
        $zones = [
            (int) round(($this->d_sum["hb_values"][0] + $this->d_sum["hb_values"][1]) / 2, 0),
            (int) round(($this->d_sum["hb_values"][2] + $this->d_sum["hb_values"][3]) / 2, 0),
            (int) round(($this->d_sum["hb_values"][4] + $this->d_sum["hb_values"][5]) / 2, 0),
            (int) round(($this->d_sum["hb_values"][6] + $this->d_sum["hb_values"][7]) / 2, 0),
        ];

        $is_applied = false;

        try {
            $push->send("section_hb", $machine->ip_address, $this->d_sum["position"], $this->d_sum["hb_values"]);

            $push->send("zone_hb", $machine->ip_address, $this->d_sum["position"], $zones);

            $push->send("section_svp", $machine->ip_address, $this->d_sum["position"], $this->d_sum["svp_values"]);

            $push->send("apply_svw", $machine->ip_address, $this->d_sum["position"], [true]);

            $push->send("chart_hb", $machine->ip_address, $this->d_sum["position"], $this->chart_logs);

            $push->send("info_duration", $machine->ip_address, $this->d_sum["position"], [$this->duration_min]);

            $speed = (int) ($this->d_sum["speed"] * 10);
            $push->send("info_speed", $machine->ip_address, $this->d_sum["position"], [$speed]);

            $code = (int) preg_replace("/[^0-9]/", "", $this->device_code);
            $push->send("info_device_code", $machine->ip_address, $this->d_sum["position"], [$code]);

            $nameArray = Caldera::encodeLittleEndian16(Auth::user()->name, 6);
            $push->send("info_operator", $machine->ip_address, $this->d_sum["position"], $nameArray);

            $now = Carbon::now();

            $year = (int) $now->format("Y");
            $push->send("info_year", $machine->ip_address, $this->d_sum["position"], [$year]);

            $month_date = (int) $now->format("md");
            $push->send("info_month_date", $machine->ip_address, $this->d_sum["position"], [$month_date]);

            $time = (int) $now->format("Hi");
            $push->send("info_time", $machine->ip_address, $this->d_sum["position"], [$time]);

            $is_applied = true;
        } catch (Exception $e) {
            $this->js('console.log("' . $e->getMessage() . '")');
        } finally {
            return $is_applied;
        }
    }

    private function checkRecents()
    {
        foreach ($this->machines as $key => $machine) {
            $recentExists = InsStcDSum::where("position", "upper")
                ->where("ins_stc_machine_id", $machine["id"])
                ->where("created_at", ">=", Carbon::now()->subHour())
                ->latest("created_at")
                ->count();

            $this->machines[$key]["upper_uploaded"] = (bool) $recentExists ? true : false;

            $recentExists = InsStcDSum::where("position", "lower")
                ->where("ins_stc_machine_id", $machine["id"])
                ->where("created_at", ">=", Carbon::now()->subHour())
                ->latest("created_at")
                ->count();

            $this->machines[$key]["lower_uploaded"] = (bool) $recentExists ? true : false;
        }
    }
};

?>

<div>
    <div wire:key="modals">
        <x-modal name="reading-review" maxWidth="2xl">
            <livewire:insights.stc.create.reading-review />
        </x-modal>
        <x-modal name="retrieve-sv-error" maxWidth="sm">
            <div class="text-center pt-6">
                <i class="icon-triangle-alert text-4xl"></i>
                <h2 class="mt-3 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    {{ __("SV tidak dapat diambil") }}
                </h2>
            </div>
            <div class="p-6 flex flex-col gap-y-3 text-sm">
                <p>
                    {{ __("SV tidak dapat diambil pada mesin dan posisi yang kamu pilih dengan alasan:.") }}
                </p>
                <hr class="border-neutral-300 dark:border-neutral-600" />
                <p class="text-xs text-neutral-500 font-mono">
                    {{ $sv_error_msg }}
                </p>
                <hr class="border-neutral-300 dark:border-neutral-600" />
                <p>
                    {{ __("Kamu dapat menunjukan pesan ini ke penanggung jawab sistem untuk diinvestigasi lebih lanjut atau isi SV secara manual.") }}
                </p>
                <div class="mt-6 flex justify-end">
                    <x-primary-button type="button" x-on:click="$dispatch('close')">
                        {{ __("Paham") }}
                    </x-primary-button>
                </div>
            </div>
        </x-modal>
    </div>
    <div class="flex items-center justify-center mb-6 gap-2 text-xs">
        @foreach ($machines as $machine)
            <div class="btn-group">
                <x-text-button
                    type="button"
                    class="px-1 bg-caldy-600 {{ $machine['upper_uploaded'] ? 'bg-opacity-80 text-white' : 'bg-opacity-15 dark:bg-opacity-10 text-caldy-700'}}"
                    x-on:click="$wire.set('d_sum.position', 'upper', false); $wire.set('d_sum.ins_stc_machine_id', '{{ $machine['id'] }}');"
                >
                    {{ $machine["line"] . " △" }}
                </x-text-button>
                <x-text-button
                    type="button"
                    class="px-1 bg-caldy-600 {{ $machine['lower_uploaded'] ? 'bg-opacity-80 text-white' : 'bg-opacity-15 dark:bg-opacity-10 text-caldy-700'}}"
                    x-on:click="$wire.set('d_sum.position', 'lower', false); $wire.set('d_sum.ins_stc_machine_id', '{{ $machine['id'] }}');"
                >
                    {{ $machine["line"] . " ▽" }}
                </x-text-button>
            </div>
        @endforeach
    </div>
    <div class="relative bg-white dark:bg-neutral-800 shadow sm:rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 divide-y md:divide-x md:divide-y-0 divide-neutral-200 dark:text-white dark:divide-neutral-700">
            <div class="p-6">
                <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">
                    <i class="icon-rectangle-horizontal mr-3 text-neutral-500"></i>
                    {{ __("Mesin") }}
                </h1>
                <div class="grid grid-cols-2 gap-x-3 mb-6">
                    <div>
                        <label for="d-log-sequence" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Urutan") }}</label>
                        <x-select class="w-full" id="d-log-sequence" wire:model="d_sum.sequence">
                            <option value=""></option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </x-select>
                    </div>
                    <div>
                        <label for="d-log-speed" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Kecepatan") }}</label>
                        <x-text-input-suffix suffix="RPM" id="d-log-speed" wire:model="d_sum.speed" type="number" step=".01" autocomplete="off" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-x-3 mb-6">
                    <div>
                        <label for="d-log-machine_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Line") }}</label>
                        <x-select class="w-full" id="d-log-machine_id" wire:model.live="d_sum.ins_stc_machine_id">
                            <option value="0"></option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine["id"] }}">{{ $machine["line"] }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label for="d-log-position" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Posisi") }}</label>
                        <x-select class="w-full" id="d-log-position" wire:model.live="d_sum.position">
                            <option value=""></option>
                            <option value="upper">{{ "△ " . __("Atas") }}</option>
                            <option value="lower">{{ "▽ " . __("Bawah") }}</option>
                        </x-select>
                    </div>
                </div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">
                    <span>{{ __("SV") }}</span>
                    @if ($d_sum["sv_used"] == "m_log")
                        <i class="icon-lock ms-2"></i>
                    @endif
                </label>
                @if ($d_sum["sv_used"] == "m_log")
                    <div class="grid grid-cols-8">
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.0" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.1" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.2" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.3" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.4" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.5" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.6" />
                        <x-text-input-t class="text-center" disabled wire:model="d_sum.sv_values.7" />
                    </div>
                @else
                    <div class="grid grid-cols-8">
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.0" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.1" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.2" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.3" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.4" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.5" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.6" />
                        <x-text-input-t class="text-center" placeholder="0" wire:model="d_sum.sv_values.7" />
                    </div>
                @endif
            </div>
            <div class="p-6">
                <div class="flex justify-between">
                    <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">
                        <i class="icon-credit-card mr-3 text-neutral-500"></i>
                        {{ __("Alat ukur") }}
                    </h1>
                    <div>
                        <input wire:model="file" type="file" class="hidden" x-ref="file" />
                        <x-secondary-button type="button" x-on:click="$refs.file.click()">{{ __("Unggah") }}</x-secondary-button>
                    </div>
                </div>
                <div class="mb-6">
                    <label for="d-log-device_code" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Kode") }}</label>
                    <x-text-input id="d-log-device_code" wire:model="device_code" type="text" placeholder="Scan atau ketik..." />
                </div>
                <div class="grid grid-cols-2 gap-x-3 mb-6">
                    <div>
                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Latensi") }}</label>
                        <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled wire:model="latency"></x-text-input-t>
                    </div>
                    <div>
                        <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Durasi") }}</label>
                        <x-text-input-t placeholder="{{ __('Menunggu...') }}" disabled wire:model="duration"></x-text-input-t>
                    </div>
                </div>
                <label class="flex justify-between px-3 mb-2 uppercase text-xs text-neutral-500">
                    <div>{{ __("HB") }}</div>
                    @if ($logs)
                        <x-text-button
                            x-on:click.prevent="$dispatch('open-modal', 'reading-review'); $dispatch('reading-review', { logs: '{{ json_encode($logs) }}', sv_temps: '{{ json_encode($d_sum['sv_values']) }}' })"
                            class="uppercase text-xs text-neutral-500"
                            type="button"
                        >
                            <i class="icon-eye mr-1"></i>
                            {{ __("Tinjau") }}
                        </x-text-button>
                    @endif
                </label>
                <div class="grid grid-cols-8">
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.0" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.1" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.2" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.3" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.4" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.5" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.6" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.hb_values.7" />
                </div>
            </div>
            <div class="p-6">
                <div class="flex justify-between">
                    <h1 class="grow text-xl text-neutral-900 dark:text-neutral-100 mb-6">
                        <i class="icon-radical mr-3 text-neutral-500"></i>
                        {{ __("Prediksi") }}
                    </h1>
                    <div>
                        <x-secondary-button type="button" wire:click="calculatePrediction">{{ __("Hitung") }}</x-secondary-button>
                    </div>
                </div>
                <div class="mb-6">
                    <label for="adj-formula_id" class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Formula") }}</label>
                    <x-select class="w-full" id="adj-formula_id" wire:model.live="d_sum.formula_id" disabled>
                        <option value="0"></option>
                        <option value="411">{{ __("v4.1.1 - Diff aggresive") }}</option>
                        <option value="412">{{ __("v4.1.2 - Diff delicate") }}</option>
                        <option value="421">{{ __("v4.2.1 - Ratio") }}</option>
                    </x-select>
                </div>
                <div class="mb-6">
                    <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("Referensi SV") }}</label>
                    <x-text-input-t wire:model="d_sum.sv_used_friendly" placeholder="{{ __('Menunggu...') }}" disabled></x-text-input-t>
                </div>
                <label class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __("SVP") }}</label>
                <div class="grid grid-cols-8">
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.0" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.1" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.2" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.3" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.4" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.5" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.6" />
                    <x-text-input-t class="text-center" placeholder="0" disabled wire:model="d_sum.svp_values.7" />
                </div>
                <div class="grid grid-cols-8 text-neutral-500 text-xs text-center">
                    <div>{{ $d_sum["svp_values_rel"][0] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][1] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][2] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][3] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][4] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][5] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][6] }}</div>
                    <div>{{ $d_sum["svp_values_rel"][7] }}</div>
                </div>
            </div>
        </div>
        <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
        <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
    </div>
    <div class="flex justify-between px-6">
        <div class="flex gap-x-3">
            @if ($errors->any())
                <i class="icon-circle-alert text-red-500"></i>
                <x-input-error :messages="$errors->first()" />
            @endif
        </div>
        <x-primary-button type="button" wire:click="send">{{ __("Kirim") }}</x-primary-button>
    </div>
</div>
