<?php

use App\InsStc;
use App\Models\InsStcAdj;
use App\Models\InsStcDSum;
use App\Models\InsStcMLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            $table->renameColumn('user_1_id', 'user_id');
            $table->renameColumn('sv_temps', 'sv_values');

            $table->integer('formula_id')->nullable();                          // 👍🏽
            $table->enum('sv_used', ['d_sum', 'm_log'])->nullable();   // 👍🏽 from submission
            $table->boolean('is_applied')->default(false);
            $table->json('target_values')->nullable();                          // 👍🏽
            $table->json('hb_values')->nullable();                              // 👍🏽
            $table->json('svp_values')->nullable();                             // 👍🏽
            $table->enum('integrity', ['stable', 'modified', 'none'])->nullable();  // from submission treat d_sum and m_log differently
        });

        $dSums = InsStcDSum::all();

        foreach ($dSums as $dSum) {
            $formula_id = 412;
            $dSum->formula_id = $formula_id;

            $targets = [78, 73, 68, 63, 58, 53, 48, 43];
            $dSum->target_values = json_encode($targets);

            $x = [];
            $z = [];

            $x = json_decode($dSum->sv_values, true);
            foreach ($x as $y) {
                $z[] = (int) round($y, 0);
            }
            $dSum->sv_values = json_encode($z);

            $hb_values = [
                (int) round($dSum->section_1, 0),
                (int) round($dSum->section_2, 0),
                (int) round($dSum->section_3, 0),
                (int) round($dSum->section_4, 0),
                (int) round($dSum->section_5, 0),
                (int) round($dSum->section_6, 0),
                (int) round($dSum->section_7, 0),
                (int) round($dSum->section_8, 0),
            ];
            $dSum->hb_values = json_encode($hb_values);

            if ($dSum->ins_stc_machine_id == 5) {
                $dSum->is_applied = true;
            }

            $m_log = InsStcMLog::where('created_at', '<', $dSum->created_at)
                ->where('ins_stc_machine_id', $dSum->ins_stc_machine_id)
                ->where('position', $dSum->position)
                ->where('created_at', '>=', $dSum->created_at->subHour())
                ->orderBy('created_at', 'desc')
                ->first();

            $sv_values = [];
            if ($m_log) {

                for ($i = 1; $i <= 8; $i++) {
                    $key = "sv_r_$i";
                    if (isset($m_log[$key])) {
                        if ($m_log[$key] > 0) {
                            $sv_values[$i - 1] = $m_log[$key];
                        } else {
                            $sv_values = [];
                            break;
                        }
                    }
                }
            }

            $dSum->sv_used = $sv_values ? 'm_log' : 'd_sum';

            $sv_values = $sv_values ?: json_decode($dSum->sv_values, true);

            $svp_values = [];
            if ($sv_values) {
                $svp_values = InsStc::calculateSVP($hb_values, $sv_values, $formula_id);
                foreach ($svp_values as $key => $value) {
                    $svp_values[$key] = $value['absolute'];
                }
            }

            if ($svp_values) {
                $dSum->svp_values = json_encode($svp_values);
            }

            $dSumBefore = InsStcDSum::where('created_at', '<', $dSum->created_at)
                ->where('ins_stc_machine_id', $dSum->ins_stc_machine_id)
                ->where('position', $dSum->position)
                ->where('created_at', '>=', $dSum->created_at->subHours(6))
                ->orderBy('created_at', 'desc')
                ->first();

            $adj = false;
            if ($dSumBefore) {
                $adj = InsStcAdj::where('ins_stc_d_sum_id', $dSumBefore->id)->first();
            }

            $svpb = [];
            if ($adj) {
                $svpb = [
                    $adj->sv_p_1,
                    $adj->sv_p_2,
                    $adj->sv_p_3,
                    $adj->sv_p_4,
                    $adj->sv_p_5,
                    $adj->sv_p_6,
                    $adj->sv_p_7,
                    $adj->sv_p_8,
                ];
            }

            if (! $svpb) {
                $svpb = json_decode($dSum->sv_values, true);
            }

            if ($svp_values && $svpb) {
                // Assume the arrays are the same length for comparison
                $isStable = true;

                foreach ($svp_values as $key => $value) {
                    if (! isset($svpb[$key]) || abs($value - $svpb[$key]) > 2) {
                        $isStable = false;
                        break;
                    }
                }

                if ($isStable) {
                    $dSum->integrity = 'stable';
                } else {
                    $dSum->integrity = 'modified';
                }
            } else {
                $dSum->integrity = 'none';
            }

            $dSum->save();
        }

        Schema::table('ins_stc_d_sums', function (Blueprint $table) {
            $table->integer('formula_id')->nullable(false)->change();

            $table->dropForeign(['user_2_id']);
            $table->dropColumn([
                'user_2_id',
                'preheat',
                'postheat',
                'section_1',
                'section_2',
                'section_3',
                'section_4',
                'section_5',
                'section_6',
                'section_7',
                'section_8',
            ]);

            Schema::dropIfExists('ins_stc_adjs');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
