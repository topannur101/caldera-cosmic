<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'com_item_id',
        'name',
        'client_name',
        'size',
        'ext',
        'is_image',
    ];

    public function getIcon()
    {
        switch ($this->ext) {
            case 'ods':
            case 'xls':
            case 'xlsx':
                return 'icon-file-spreadsheet';
            case 'odt':
            case 'doc':
            case 'docx':
            case 'pdf':
            case 'rtf':
            case 'txt':
            case 'csv':
            case 'ppt':
            case 'pptx':
                return 'icon-file-text';
            case 'rar':
            case '7zip':
            case 'zip':
                return 'icon-file-archive';
            case 'mp4':
            case 'avi':
            case 'mkv':
            case 'mov':
            case 'wmv':
            case 'flv':
            case 'webm':
                return 'icon-file-video';
            default:
                return 'icon-file';
        }
    }

    public function getFormattedSize()
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($this->size >= 1024 && $index < count($units) - 1) {
            $this->size /= 1024;
            $index++;
        }

        return round($this->size, 2).' '.$units[$index];
    }
}
