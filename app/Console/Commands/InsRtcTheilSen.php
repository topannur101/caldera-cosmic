<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InsRtcTheilSen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ins-rtc-theil-sen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        function theilSenEstimator($x, $y)
        {
            $n = count($x);
            $slopes = [];

            // Calculate slopes between all pairs of points
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($x[$i] != $x[$j]) {  // Prevent division by zero
                        $slopes[] = ($y[$j] - $y[$i]) / ($x[$j] - $x[$i]);
                    }
                }
            }

            // Compute the median slope
            sort($slopes);
            $medianSlope = $slopes[intval(count($slopes) / 2)];

            // Compute the intercept
            $intercepts = [];
            for ($i = 0; $i < $n; $i++) {
                $intercepts[] = $y[$i] - $medianSlope * $x[$i];
            }

            sort($intercepts);
            $medianIntercept = $intercepts[intval(count($intercepts) / 2)];

            return [$medianSlope, $medianIntercept];
        }

        // Sample data
        $data = [
            ['3.03', '2024-05-16 08:18:07'],
            ['3.08', '2024-05-16 08:18:05'],
            ['3.10', '2024-05-16 08:17:58'],
            ['3.22', '2024-05-16 08:17:56'],
            ['3.22', '2024-05-16 08:17:51'],
            ['3.22', '2024-05-16 08:17:47'],
            ['2.98', '2024-05-16 08:17:42'],
            ['3.04', '2024-05-16 08:17:40'],
            ['3.11', '2024-05-16 08:17:37'],
            ['3.16', '2024-05-16 08:17:34'],
            ['3.16', '2024-05-16 08:17:32'],
        ];

        // Convert data to x and y arrays
        $x = [];
        $y = [];
        foreach ($data as $row) {
            $y[] = floatval($row[0]);
            $x[] = strtotime($row[1]);
        }

        // Normalize the x values by subtracting the minimum timestamp
        $minX = min($x);
        $x = array_map(function ($value) use ($minX) {
            return $value - $minX;
        }, $x);

        // Calculate the Theil-Sen estimator
        [$slope, $intercept] = theilSenEstimator($x, $y);

        echo "Slope: $slope\n";
        echo "Intercept: $intercept\n";
    }
}
