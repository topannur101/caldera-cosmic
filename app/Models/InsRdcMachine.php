<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsRdcMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'type',
        'cells',
        'is_active',
    ];

    protected $casts = [
        'cells' => 'array',
        'is_active' => 'boolean',
    ];

    // Bounds fields that should populate both low and high values
    protected const BOUNDS_FIELDS = ['s_min', 's_max', 'tc10', 'tc50', 'tc90'];

    /**
     * Get the count of configured cells/patterns
     */
    public function cellsCount(): int
    {
        return count($this->cells ?? []);
    }

    /**
     * Get configuration for a specific field
     */
    public function getFieldConfig(string $field): ?array
    {
        $cells = $this->cells ?? [];

        foreach ($cells as $cell) {
            if (isset($cell['field']) && $cell['field'] === $field) {
                return $cell;
            }
        }

        return null;
    }

    /**
     * Check if machine is Excel type
     */
    public function isExcel(): bool
    {
        return $this->type === 'excel';
    }

    /**
     * Check if machine is TXT type
     */
    public function isTxt(): bool
    {
        return $this->type === 'txt';
    }

    /**
     * Get accepted file types for upload
     */
    public function getAcceptedFileTypes(): string
    {
        return match ($this->type) {
            'excel' => '.xls,.xlsx',
            'txt' => '.txt',
            default => '.xls,.xlsx,.txt'
        };
    }

    /**
     * Get file type description
     */
    public function getFileTypeDescription(): string
    {
        return match ($this->type) {
            'excel' => 'Excel Files (.xls, .xlsx)',
            'txt' => 'Text Files (.txt)',
            default => 'Excel or Text Files'
        };
    }

    /**
     * Check if a field is a bounds field
     */
    public static function isBoundsField(string $field): bool
    {
        return in_array($field, self::BOUNDS_FIELDS);
    }

    /**
     * Get the low and high field names for a bounds field
     */
    public static function getBoundsFieldNames(string $field): array
    {
        if (! self::isBoundsField($field)) {
            return [$field];
        }

        return [
            $field.'_low',
            $field.'_high',
        ];
    }

    /**
     * Normalize search term for dynamic matching
     */
    public static function normalizeSearchTerm(string $text): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $text));
    }

    /**
     * Check if configuration is legacy format (needs migration)
     */
    public function hasLegacyConfig(): bool
    {
        $cells = $this->cells ?? [];

        foreach ($cells as $cell) {
            // Legacy format has 'address' or 'pattern' without 'type'
            if (! isset($cell['type']) && (isset($cell['address']) || isset($cell['pattern']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Migrate legacy configuration to hybrid format
     */
    public function migrateLegacyConfig(): bool
    {
        if (! $this->hasLegacyConfig()) {
            return false; // Already migrated or no config
        }

        $cells = $this->cells ?? [];
        $migratedCells = [];

        foreach ($cells as $cell) {
            if (! isset($cell['field'])) {
                continue;
            }

            $migratedCell = ['field' => $cell['field']];

            if (isset($cell['address'])) {
                // Excel static format
                $migratedCell['type'] = 'static';
                $migratedCell['address'] = strtoupper(trim($cell['address']));
            } elseif (isset($cell['pattern'])) {
                // This is for txt machines, but we'll keep pattern format as is
                $migratedCell['type'] = 'pattern';
                $migratedCell['pattern'] = $cell['pattern'];
            } else {
                continue; // Skip invalid configurations
            }

            $migratedCells[] = $migratedCell;
        }

        $this->cells = $migratedCells;

        return $this->save();
    }

    /**
     * Validate hybrid configuration
     */
    public function validateHybridConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $index => $field) {
            if (! isset($field['field']) || ! isset($field['type'])) {
                $errors[] = "Configuration {$index}: Missing required fields";

                continue;
            }

            switch ($field['type']) {
                case 'static':
                    if (! isset($field['address']) || ! preg_match('/^[A-Z]+[1-9]\d*$/', $field['address'])) {
                        $errors[] = "Field {$field['field']}: Invalid Excel address";
                    }
                    break;

                case 'dynamic':
                    if (! isset($field['row_search']) || ! isset($field['column_search'])) {
                        $errors[] = "Field {$field['field']}: Missing search terms";
                        break;
                    }

                    // Validate alphanumeric only
                    if (! preg_match('/^[a-zA-Z0-9]+$/', $field['row_search'])) {
                        $errors[] = "Field {$field['field']}: Row search must be alphanumeric only";
                    }

                    if (! preg_match('/^[a-zA-Z0-9]+$/', $field['column_search'])) {
                        $errors[] = "Field {$field['field']}: Column search must be alphanumeric only";
                    }

                    // Validate offsets are integers
                    if (isset($field['row_offset']) && ! is_int($field['row_offset'])) {
                        $errors[] = "Field {$field['field']}: Row offset must be integer";
                    }

                    if (isset($field['column_offset']) && ! is_int($field['column_offset'])) {
                        $errors[] = "Field {$field['field']}: Column offset must be integer";
                    }
                    break;

                case 'pattern':
                    // For txt machines
                    if (! isset($field['pattern']) || empty(trim($field['pattern']))) {
                        $errors[] = "Field {$field['field']}: Pattern cannot be empty";
                    }
                    break;

                default:
                    $errors[] = "Field {$field['field']}: Invalid configuration type";
            }
        }

        return $errors;
    }
}
