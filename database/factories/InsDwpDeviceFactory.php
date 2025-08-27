<?php

namespace Database\Factories;

use App\Models\InsDwpDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsDwpDevice>
 */
class InsDwpDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'DWP Station ' . fake()->randomElement(['Alpha', 'Beta', 'Gamma', 'Delta', 'Echo', 'Foxtrot']),
            'ip_address' => '192.168.1.' . fake()->unique()->numberBetween(100, 199),
            'config' => $this->generateLineConfig(),
            'is_active' => fake()->boolean(85), // 85% chance of being active
        ];
    }

    /**
     * Generate realistic line configuration
     */
    private function generateLineConfig(): array
    {
        $lineCount = fake()->numberBetween(1, 4);
        $config = [];
        
        $stationLetter = fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
        
        for ($i = 1; $i <= $lineCount; $i++) {
            $config[] = [
                'line' => $stationLetter . $i,
                'addr_counter' => 1000 + (($i - 1) * 2), // Counter addresses: 1000, 1002, 1004, etc.
                'addr_reset' => 2000 + (($i - 1) * 2),   // Reset addresses: 2000, 2002, 2004, etc.
            ];
        }
        
        return $config;
    }

    /**
     * Indicate that the device is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Generate device with specific number of lines.
     */
    public function withLines(int $lineCount): static
    {
        return $this->state(function (array $attributes) use ($lineCount) {
            $stationLetter = fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
            $config = [];
            
            for ($i = 1; $i <= $lineCount; $i++) {
                $config[] = [
                    'line' => $stationLetter . $i,
                    'addr_counter' => 1000 + (($i - 1) * 2),
                    'addr_reset' => 2000 + (($i - 1) * 2),
                ];
            }
            
            return [
                'config' => $config,
            ];
        });
    }

    /**
     * Generate device with specific station letter.
     */
    public function station(string $letter): static
    {
        return $this->state(function (array $attributes) use ($letter) {
            $config = collect($attributes['config'] ?? $this->generateLineConfig())
                ->map(function ($line, $index) use ($letter) {
                    $line['line'] = $letter . ($index + 1);
                    return $line;
                })
                ->toArray();
            
            return [
                'name' => "DWP Station {$letter}",
                'config' => $config,
            ];
        });
    }
}