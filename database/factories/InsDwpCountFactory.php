<?php

namespace Database\Factories;

use App\Models\InsDwpCount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsDwpCount>
 */
class InsDwpCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'line' => fake()->randomElement(['A1', 'A2', 'B1', 'B2', 'B3', 'G1', 'G2']),
            'cumulative' => fake()->numberBetween(1000, 50000),
            'incremental' => fake()->numberBetween(1, 100),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Generate count for a specific line.
     */
    public function forLine(string $line): static
    {
        return $this->state(fn (array $attributes) => [
            'line' => strtoupper(trim($line)),
        ]);
    }

    /**
     * Generate count with specific incremental value.
     */
    public function withIncremental(int $incremental): static
    {
        return $this->state(fn (array $attributes) => [
            'incremental' => $incremental,
        ]);
    }

    /**
     * Generate count with specific cumulative value.
     */
    public function withCumulative(int $cumulative): static
    {
        return $this->state(fn (array $attributes) => [
            'cumulative' => $cumulative,
        ]);
    }

    /**
     * Generate count at specific time.
     */
    public function at(Carbon|string $timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp),
        ]);
    }

    /**
     * Generate high activity count (large incremental).
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'incremental' => fake()->numberBetween(50, 200),
        ]);
    }

    /**
     * Generate medium activity count.
     */
    public function mediumActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'incremental' => fake()->numberBetween(20, 80),
        ]);
    }

    /**
     * Generate low activity count.
     */
    public function lowActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'incremental' => fake()->numberBetween(1, 30),
        ]);
    }

    /**
     * Generate night/weekend activity (reduced).
     */
    public function nightActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'incremental' => fake()->numberBetween(1, 15),
        ]);
    }

    /**
     * Generate count during work hours with realistic patterns.
     */
    public function workHours(): static
    {
        return $this->state(function (array $attributes) {
            $hour = fake()->numberBetween(8, 17); // 8 AM to 5 PM
            $minute = fake()->numberBetween(0, 59);
            
            $baseDate = $attributes['created_at'] ?? fake()->dateTimeBetween('-30 days', 'now');
            $workDate = Carbon::parse($baseDate)->setTime($hour, $minute);
            
            return [
                'created_at' => $workDate,
                'incremental' => fake()->numberBetween(20, 150),
            ];
        });
    }

    /**
     * Generate count sequence maintaining cumulative logic.
     */
    public function sequence(int $previousCumulative = null): static
    {
        return $this->state(function (array $attributes) use ($previousCumulative) {
            $incremental = $attributes['incremental'];
            $cumulative = $previousCumulative ? $previousCumulative + $incremental : fake()->numberBetween(1000, 10000);
            
            return [
                'cumulative' => $cumulative,
            ];
        });
    }

    /**
     * Generate count after counter reset.
     */
    public function afterReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'cumulative' => $attributes['incremental'], // Cumulative equals incremental after reset
        ]);
    }
}