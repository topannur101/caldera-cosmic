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
        'is_image'
    ];

    public function getIcon()
    {
        switch($this->ext) {
            case 'ods':
            case 'xls':
            case 'xlsx':
                return 'far fa-file-excel';
            case 'odt':
            case 'doc':
            case 'docx':
                return 'far fa-file-word';
            case 'pdf':
                return 'far fa-file-pdf';
            case 'rar':
            case '7zip':
            case 'zip':
                return 'far fa-file-zipper';
            case 'rtf':
            case 'txt':
            case 'csv':
                return 'far fa-file-lines';
            case 'ppt':
            case 'pptx':
                return 'far fa-file-powerpoint';
            case 'mp4':
            case 'avi':
            case 'mkv':
            case 'mov':
            case 'wmv':
            case 'flv':
            case 'webm':
                return 'far fa-file-video';
            default:
                return 'far fa-file';
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

        return round($this->size, 2) . ' ' . $units[$index];
    }
}
