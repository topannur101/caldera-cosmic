<?php

namespace App;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Caldera
{
    public static function manageCollection(Collection $collection, string $name, int $maxItems = 20): Collection
    {
        $now = Carbon::now()->toDateTimeString();
    
        // Check if the style exists in the collection
        $exists = $collection->firstWhere('name', $name);
    
        if ($exists) {
            // Update the updated_at field
            $collection = $collection->map(function ($item) use ($name, $now) {
                if ($item['name'] === $name) {
                    $item['updated_at'] = $now;
                }
                return $item;
            });
        } else {
            // Add the new style to the collection
            $collection->push(['name' => $name, 'updated_at' => $now]);
        }
    
        // Ensure the collection doesn't exceed the maximum number of items
        if ($collection->count() > $maxItems) {
            // Sort the collection by updated_at in ascending order (oldest first)
            $collection = $collection->sortBy('updated_at');
    
            // Remove the oldest item
            $collection->shift();
        }
    
        // Sort back by updated_at in descending order (newest first)
        return $collection->sortByDesc('updated_at')->values();
    }
}
