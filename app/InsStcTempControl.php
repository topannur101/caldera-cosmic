<?php

namespace App;

class InsStcTempControl
{
    private const K = 0.5; // Global sensitivity
    private const INFLUENCE_FACTOR = 0; // Adjacent section influence factor
    private const ACCEPTABLE_ERROR = 2.0; // °C acceptable margin
    
    private array $targetValues = [
        77.5, 72.5, 67.5, 62.5, 57.5, 52.5, 47.5, 42.5
    ];

    // Linearity factor is sensitivity for each section
    // Values > 1 mean aggressive, larger SV correction
    // Values < 1 mean delicate, smaller SV correction
    private array $linearityFactors = [
        1.0,  // Section 1 
        1.0,  // Section 2 
        1.0,  // Section 3 
        1.0,  // Section 4 
        1.0,  // Section 5 
        1.0,  // Section 6 
        1.0,  // Section 7 
        1.0   // Section 8
    ];

    /**
     * Set custom linearity factors for sections
     */
    public function setLinearityFactors(array $factors): void
    {
        if (count($factors) !== count($this->targetValues)) {
            throw new \InvalidArgumentException('Must provide linearity factors for all sections');
        }
        $this->linearityFactors = $factors;
    }

    /**
     * Get current linearity factors
     */
    public function getLinearityFactors(): array
    {
        return $this->linearityFactors;
    }

    /**
     * Calculate new set values based on current measurements
     */
    public function calculateNewSetValues(array $currentSetValues, array $measuredValues): array
    {
        $numSections = count($this->targetValues);
        $correctionFactors = [];
        $newSetValues = [];

        // Validate input arrays
        if (count($currentSetValues) !== $numSections || count($measuredValues) !== $numSections) {
            throw new \InvalidArgumentException('Input arrays must match the number of sections');
        }

        // Calculate correction factors for each section
        for ($i = 0; $i < $numSections; $i++) {
            $correctionFactors[$i] = $this->calculateCorrectionFactor(
                $this->targetValues[$i],
                $measuredValues[$i],
                $this->linearityFactors[$i]
            );
        }

        // Calculate new set values considering adjacent influence
        for ($i = 0; $i < $numSections; $i++) {
            $newSetValues[$i] = number_format($this->calculateNewSetValue(
                $currentSetValues[$i],
                $correctionFactors,
                $i,
                $numSections,
                $this->linearityFactors[$i]
            ), 0);
        }

        return $newSetValues;
    }

    /**
     * Calculate correction factor for a single section
     */
    private function calculateCorrectionFactor(
        float $targetValue, 
        float $measuredValue, 
        float $linearityFactor
    ): float {
        // Apply linearity factor to the correction calculation
        return ($targetValue - $measuredValue) * self::K * $linearityFactor;
    }

    /**
     * Calculate new set value for a single section considering adjacent influence
     */
    private function calculateNewSetValue(
        float $currentSetValue,
        array $correctionFactors,
        int $sectionIndex,
        int $numSections,
        float $linearityFactor
    ): float {
        // Base correction for current section
        $newValue = $currentSetValue + $correctionFactors[$sectionIndex];

        // Add weighted influence from adjacent sections
        if ($sectionIndex > 0) {
            // Influence from previous section adjusted by current section's linearity
            $newValue += (self::INFLUENCE_FACTOR * $correctionFactors[$sectionIndex - 1]) / $linearityFactor;
        }

        if ($sectionIndex < $numSections - 1) {
            // Influence from next section adjusted by current section's linearity
            $newValue += (self::INFLUENCE_FACTOR * $correctionFactors[$sectionIndex + 1]) / $linearityFactor;
        }

        return $newValue;
    }

    /**
     * Calibrate linearity factor for a section based on observed changes
     */
    public function calibrateLinearityFactor(
        int $sectionIndex, 
        float $svChange, 
        float $mvChange
    ): float {
        if ($mvChange == 0) {
            throw new \InvalidArgumentException('MV change cannot be zero');
        }

        // Calculate new linearity factor based on observed SV to MV relationship
        $newFactor = abs($svChange / $mvChange);
        
        // Update the linearity factor with some dampening to avoid overcorrection
        $dampening = 0.7; // 70% of new value, 30% of old value
        $this->linearityFactors[$sectionIndex] = 
            ($dampening * $newFactor) + ((1 - $dampening) * $this->linearityFactors[$sectionIndex]);
        
        return $this->linearityFactors[$sectionIndex];
    }

    /**
     * Get detailed analysis of current measurements with linearity information
     */
    public function analyzeTemperatures(array $measuredValues): array
    {
        $analysis = [];
        foreach ($measuredValues as $index => $mv) {
            $tv = $this->targetValues[$index];
            $difference = $mv - $tv;
            $withinRange = abs($difference) <= self::ACCEPTABLE_ERROR;

            $analysis[] = [
                'section' => $index + 1,
                'target_value' => $tv,
                'measured_value' => $mv,
                'difference' => $difference,
                'within_range' => $withinRange,
                'linearity_factor' => $this->linearityFactors[$index],
                'suggested_adjustment_multiplier' => $this->linearityFactors[$index] * self::K
            ];
        }
        return $analysis;
    }

    /**
     * Check if measured values are within acceptable range
     */
    public function isWithinAcceptableRange(array $measuredValues): bool
    {
        foreach ($measuredValues as $index => $mv) {
            if (abs($mv - $this->targetValues[$index]) > self::ACCEPTABLE_ERROR) {
                return false;
            }
        }
        return true;
    }
}
