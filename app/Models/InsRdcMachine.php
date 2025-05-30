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
    ];

    protected $casts = [
        'cells' => 'array',
    ];

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
        return match($this->type) {
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
        return match($this->type) {
            'excel' => 'Excel Files (.xls, .xlsx)',
            'txt' => 'Text Files (.txt)',
            default => 'Excel or Text Files'
        };
    }
}