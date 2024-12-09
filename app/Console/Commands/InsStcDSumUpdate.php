<?php

namespace App\Console\Commands;

use App\InsStc;
use App\Models\InsStcDLog;
use App\Models\InsStcDSum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class InsStcDSumUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-stc-d-sum-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Look for empty sections value on d_sums and update accordingly';

    /**
     * Execute the console command.
     */
    public function handle()
    {        
        $dsums = InsStcDSum::query()
        ->where('section_1', 0)
        ->where('section_2', 0)
        ->where('section_3', 0)
        ->where('section_4', 0)
        ->where('section_5', 0)
        ->where('section_6', 0)
        ->where('section_7', 0)
        ->where('section_8', 0)
        ->get();

        foreach ($dsums as $dsum) {
            $logs = InsStcDLog::where('ins_stc_d_sum_id', $dsum->id)->get()->toArray();
            $temps = array_map(fn($item) => $item['temp'], $logs);
            $medians = InsStc::getMediansBySection($temps);

            $dsum->update([
                'section_1' => $medians['section_1'],
                'section_2' => $medians['section_2'],
                'section_3' => $medians['section_3'],
                'section_4' => $medians['section_4'],
                'section_5' => $medians['section_5'],
                'section_6' => $medians['section_6'],
                'section_7' => $medians['section_7'],
                'section_8' => $medians['section_8'],
            ]);

            $this->info($dsum->id . ' updated');        
        }

        !$dsums->count() ? $this->info('Are you nuts?') : $this->info ('All done, sir.');
    }
}
