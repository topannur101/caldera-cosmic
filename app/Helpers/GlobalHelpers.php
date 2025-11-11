<?php

namespace App\Helpers;

class GlobalHelpers {
    public function getMedian(array $array): float|int|null
    {
        // 1. Filter out non-numeric values.
        //    Using array_values to reset keys (0, 1, 2...)
        $numericArray = array_values(array_filter($array, 'is_numeric'));
        
        $count = count($numericArray);

        // 2. Return null if no numbers were found (cleaner than 0)
        if ($count === 0) {
            return null;
        }

        // 3. IMPORTANT: Use SORT_NUMERIC to avoid alphabetical sorting
        sort($numericArray, SORT_NUMERIC);

        // 4. Get the middle index
        //    (e.g., 5 items -> index 2; 6 items -> index 2)
        $middle = floor(($count - 1) / 2);

        // 5. Calculate median
        if ($count % 2) {
            // Odd number of items: return the middle one
            $median = $numericArray[$middle];
        } else {
            // Even number of items: return the average of the two middle ones
            $median = ($numericArray[$middle] + $numericArray[$middle + 1]) / 2;
        }

        // 6. Return the exact value (float or int), not the rounded one
        return $median;
    }
}


