<?php

namespace App\Services;

use App\Models\InsPpmProduct;
use App\Models\InsPpmComponent;
use App\Models\InsPpmComponentsProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

class InsPpmExcelImportService
{
    /**
     * Import Excel file and save to database
     * 
     * @param string $filePath Path to the Excel file
     * @return array Result with success status and summary
     */
    public function import(string $filePath): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'summary' => [
                'products_created' => 0,
                'products_updated' => 0,
                'components_created' => 0,
                'components_updated' => 0,
                'processes_created' => 0,
                'processes_updated' => 0,
                'errors' => [],
            ],
        ];

        try {
            // Try to identify the file type first
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Check if file exists and is readable
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            
            if (!is_readable($filePath)) {
                throw new \Exception("File is not readable: {$filePath}");
            }
            
            // For xlsx files, verify it's a valid zip file first
            if ($fileExtension === 'xlsx') {
                $zip = new \ZipArchive();
                $zipResult = $zip->open($filePath);
                if ($zipResult !== true) {
                    throw new \Exception(
                        "Invalid xlsx file: The file is not a valid Excel file or may be corrupted. " .
                        "Please ensure you saved the file as .xlsx format in Microsoft Excel or a compatible application. " .
                        "Error code: {$zipResult}"
                    );
                }
                $zip->close();
            }
            
            // Map extension to reader
            $readerMap = [
                'xlsx' => 'Xlsx',
                'xls' => 'Xls',
                'csv' => 'Csv',
                'ods' => 'Ods',
                'slk' => 'Slk',
                'xml' => 'Xml',
                'gnumeric' => 'Gnumeric',
            ];
            
            if (!isset($readerMap[$fileExtension])) {
                throw new \PhpOffice\PhpSpreadsheet\Reader\Exception(
                    "Unsupported file extension: .{$fileExtension}. Supported extensions: xlsx, xls, csv"
                );
            }
            
            // Create reader for the specific format
            $reader = IOFactory::createReader($readerMap[$fileExtension]);
            
            // For CSV files, set additional options
            if ($fileExtension === 'csv') {
                $reader->setInputEncoding('UTF-8');
            }
            
            try {
                $spreadsheet = $reader->load($filePath);
            } catch (\Exception $e) {
                // If xlsx fails, try xls reader as fallback (file might be mislabeled)
                if ($fileExtension === 'xlsx') {
                    Log::warning('Xlsx reader failed, trying Xls reader', ['error' => $e->getMessage()]);
                    $xlsReader = IOFactory::createReader('Xls');
                    $spreadsheet = $xlsReader->load($filePath);
                } else {
                    throw $e;
                }
            }
            
            $sheet = $spreadsheet->getActiveSheet();
            $data = $this->parseExcel($sheet);
            
            Log::debug('PPM Excel Import - Parsed data', $data);
            
            if (empty($data['product'])) {
                $result['message'] = 'No product information found in Excel file. Please ensure the Excel file contains DEV-STYLE, PRODUCT CODE, COLOR WAY and DATE fields in the header rows.';
                Log::warning('PPM Excel Import - No product data found', ['highestRow' => $sheet->getHighestRow()]);
                return $result;
            }
            
            // Log product data for debugging
            Log::debug('PPM Excel Import - Product data', $data['product']);

            DB::beginTransaction();

            try {
                $product = $this->saveProduct($data['product']);
                $result['summary']['products_created'] = $product['created'] ? 1 : 0;
                $result['summary']['products_updated'] = $product['created'] ? 0 : 1;

                foreach ($data['components'] as $componentData) {
                    $componentResult = $this->saveComponent($product['model'], $componentData);
                    $result['summary']['components_created'] += $componentResult['created'] ? 1 : 0;
                    $result['summary']['components_updated'] += $componentResult['created'] ? 0 : 1;
                    $result['summary']['processes_created'] += $componentResult['processes_created'];
                    $result['summary']['processes_updated'] += $componentResult['processes_updated'];
                }

                DB::commit();
                $result['success'] = true;
                $result['message'] = "Import successful: Product '{$product['model']->product_code}' with " . count($data['components']) . " component(s)";

            } catch (\Exception $e) {
                DB::rollBack();
                $result['message'] = 'Database error: ' . $e->getMessage();
                $result['summary']['errors'][] = $e->getMessage();
            }

        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            $result['message'] = 'Excel parsing error: ' . $e->getMessage();
            $result['summary']['errors'][] = $e->getMessage();
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
            $result['summary']['errors'][] = $e->getMessage();
            Log::error('PPM Excel Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    /**
     * Parse Excel sheet and extract product, components, and processes
     */
    private function parseExcel($sheet): array
    {
        $result = [
            'product' => [],
            'components' => [],
        ];

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Check if this is the new/flat template format
        $isNewFormat = false;
        for ($row = 1; $row <= min(10, $highestRow); $row++) {
            for ($col = 1; $col <= min(6, $highestColumnIndex); $col++) {
                $cellValue = $this->getCellValue($this->getCell($sheet, $col, $row));
                $normalized = strtoupper(trim((string) $cellValue));

                if (
                    strpos($normalized, 'PRODUCT INFORMATION') !== false ||
                    strpos($normalized, 'PROCESS STEPS') !== false ||
                    strpos($normalized, 'PRODUCT CODE') !== false ||
                    strpos($normalized, 'PART NAME') !== false
                ) {
                    $isNewFormat = true;
                    break 2;
                }
            }
        }

        if ($isNewFormat) {
            return $this->parseNewTemplateFormat($sheet, $highestRow, $highestColumnIndex);
        }

        // **FIX 1: Parse product info from rows 1-4 (with colon separator)**
        $productData = [];
        for ($row = 1; $row <= 4; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $this->getCell($sheet, $col, $row);
                $value = $this->getCellValue($cell);
                
                // Split by colon if present
                if (strpos($value, ':') !== false) {
                    [$key, $val] = explode(':', $value, 2);
                    $key = strtolower(str_replace([' ', '-', '_'], '', trim($key)));
                    $val = trim($val);
                    
                    if (strpos($key, 'devstyle') !== false) {
                        $productData['dev_style'] = $val;
                    }
                    if (strpos($key, 'productcode') !== false) {
                        $productData['product_code'] = $val;
                    }
                    if (strpos($key, 'colorway') !== false) {
                        $productData['color_way'] = $val;
                    }
                    if (strpos($key, 'date') !== false || strpos($key, 'production') !== false) {
                        $productData['production_date'] = $this->parseDate($val);
                    }
                }
            }
        }

        $result['product'] = $productData;

        // **FIX 2: Find component header row (look for PROCESS header)**
        $componentHeaderRow = null;
        $componentColumns = [];
        
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $this->getCellValue($this->getCell($sheet, $col, $row));
                if (strtoupper(trim($value)) === 'PROCESS') {
                    $componentHeaderRow = $row;
                    break 2;
                }
            }
        }

        if ($componentHeaderRow) {
            // Map out column headers
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = strtoupper(trim($this->getCellValue($this->getCell($sheet, $col, $componentHeaderRow))));
                
                if ($value === 'PROCESS') {
                    $componentColumns['process'] = $col;
                }
                if (strpos($value, 'CHEMICAL') !== false) {
                    $componentColumns['chemical'] = $col;
                }
                if (strpos($value, 'TEMP') !== false) {
                    $componentColumns['temp'] = $col;
                }
                if (strpos($value, 'HARDENER') !== false) {
                    $componentColumns['hardener'] = $col;
                }
                if (strpos($value, 'WIPES') !== false) {
                    $componentColumns['wipes'] = $col;
                }
                if (strpos($value, 'ROUND') !== false) {
                    $componentColumns['round'] = $col;
                }
                if (strpos($value, 'TIME') !== false) {
                    $componentColumns['time'] = $col;
                }
                if (strpos($value, 'MESH') !== false) {
                    $componentColumns['mesh'] = $col;
                }
            }
        }

        // **FIX 3: Parse process rows starting after the header**
        $currentComponent = null;
        
        if ($componentHeaderRow) {
            for ($row = $componentHeaderRow + 1; $row <= $highestRow; $row++) {
                $processName = $this->getCellValue($this->getCell($sheet, $componentColumns['process'] ?? 1, $row));
                
                if (!empty(trim($processName))) {
                    $processRow = [];
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $headerValue = $this->getCellValue($this->getCell($sheet, $col, $componentHeaderRow));
                        $cellValue = $this->getCellValue($this->getCell($sheet, $col, $row));
                        
                        if (!empty(trim($headerValue)) && !empty(trim($cellValue))) {
                            $processRow[strtolower(str_replace(' ', '_', trim($headerValue)))] = $cellValue;
                        }
                    }
                    
                    if (!empty($processRow)) {
                        if ($currentComponent === null) {
                            // **Extract component name from Row 5**
                            $componentName = $this->getCellValue($this->getCell($sheet, 2, 5)); // Column B
                            $currentComponent = [
                                'part_name' => $componentName,
                                'material_number' => $this->getCellValue($this->getCell($sheet, 6, 5)),
                                'mcs_number' => $this->getCellValue($this->getCell($sheet, 7, 6)),
                                'processes' => [],
                            ];
                        }
                        
                        $currentComponent['processes'][] = $processRow;
                    }
                }
            }
        }

        if ($currentComponent !== null) {
            $result['components'][] = $currentComponent;
        }

        return $result;
    }

    /**
     * Parse new flat template format
     */
    private function parseNewTemplateFormat($sheet, int $highestRow, int $highestColumnIndex): array
    {
        $result = [
            'product' => [],
            'components' => [],
        ];

        // Find header rows
        $productHeaderRow = null;
        $componentHeaderRow = null;
        $processHeaderRow = null;

        for ($row = 1; $row <= min(15, $highestRow); $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellValue = $this->getCellValue($this->getCell($sheet, $col, $row));
                if (trim($cellValue) === 'PRODUCT INFORMATION') {
                    $productHeaderRow = $row + 1; // Next row has headers
                }
                if (trim($cellValue) === 'COMPONENT INFORMATION') {
                    $componentHeaderRow = $row + 1;
                }
                if (trim($cellValue) === 'PROCESS STEPS') {
                    $processHeaderRow = $row + 1;
                }
            }
        }

        // Parse product data
        if ($productHeaderRow) {
            // Find first data row
            for ($row = $productHeaderRow + 1; $row <= $highestRow; $row++) {
                $productCode = $this->getCellValue($this->getCell($sheet, 2, $row));
                if (!empty(trim($productCode))) {
                    $partName = $this->getCellValue($this->getCell($sheet, 4, $row));

                    $result['product'] = [
                        'product_code' => $productCode,
                        'dev_style' => $this->getCellValue($this->getCell($sheet, 3, $row)) ?? '',
                        'color_way' => '',
                        'production_date' => null,
                    ];

                    // For compact template, part name is provided in PRODUCT INFORMATION row.
                    if (!empty(trim((string) $partName))) {
                        $result['components'][] = [
                            'part_name' => $partName,
                            'base_part_name' => '',
                            'description' => '',
                            'material_number' => '',
                            'material_name' => '',
                            'mcs_number' => '',
                            'vendor_type' => '',
                            'hera_hardness' => '',
                            'processes' => [],
                        ];
                    }

                    break;
                }
            }
        }

        // Parse component data
        if ($componentHeaderRow) {
            for ($row = $componentHeaderRow + 1; $row <= $highestRow; $row++) {
                $partName = $this->getCellValue($this->getCell($sheet, 2, $row));
                if (!empty(trim($partName))) {
                    $component = [
                        'part_name' => $partName,
                        'base_part_name' => $this->getCellValue($this->getCell($sheet, 3, $row)) ?? '',
                        'description' => $this->getCellValue($this->getCell($sheet, 4, $row)) ?? '',
                        'material_number' => $this->getCellValue($this->getCell($sheet, 5, $row)) ?? '',
                        'material_name' => $this->getCellValue($this->getCell($sheet, 6, $row)) ?? '',
                        'mcs_number' => $this->getCellValue($this->getCell($sheet, 7, $row)) ?? '',
                        'vendor_type' => $this->getCellValue($this->getCell($sheet, 8, $row)) ?? '',
                        'hera_hardness' => $this->getCellValue($this->getCell($sheet, 9, $row)) ?? '',
                        'processes' => [],
                    ];
                    $result['components'][] = $component;
                    break;
                }
            }
        }

        // Parse process steps for component section template
        if ($processHeaderRow && !empty($result['components'])) {
            for ($row = $processHeaderRow + 1; $row <= $highestRow; $row++) {
                $stepNumber = $this->getCellValue($this->getCell($sheet, 2, $row));
                $processType = $this->getCellValue($this->getCell($sheet, 3, $row));
                
                if (!empty(trim($stepNumber)) || !empty(trim($processType))) {
                    $process = [
                        'step_number' => $stepNumber ?? count($result['components'][0]['processes']) + 1,
                        'process_type' => $processType ?? '',
                        'operation' => $this->getCellValue($this->getCell($sheet, 4, $row)) ?? '',
                        'color_code' => $this->getCellValue($this->getCell($sheet, 5, $row)) ?? '',
                        'chemical' => $this->getCellValue($this->getCell($sheet, 6, $row)) ?? '',
                        'hardener_code' => $this->getCellValue($this->getCell($sheet, 7, $row)) ?? '',
                        'temperature_c' => $this->getCellValue($this->getCell($sheet, 8, $row)) ?? '',
                        'wipes_count' => $this->getCellValue($this->getCell($sheet, 9, $row)) ?? '',
                        'rounds_count' => $this->getCellValue($this->getCell($sheet, 10, $row)) ?? '',
                        'duration' => $this->getCellValue($this->getCell($sheet, 11, $row)) ?? '',
                        'mesh_number' => $this->getCellValue($this->getCell($sheet, 12, $row)) ?? '',
                        'method' => $this->getCellValue($this->getCell($sheet, 13, $row)) ?? '',
                    ];
                    $result['components'][0]['processes'][] = $process;
                }
            }
        }

        // If there is PROCESS STEPS but no component section exists, build one from PRODUCT INFORMATION's part name
        if ($processHeaderRow && empty($result['components'])) {
            $fallbackPartName = null;
            if ($productHeaderRow) {
                $fallbackPartName = $this->getCellValue($this->getCell($sheet, 4, $productHeaderRow + 1));
            }

            $result['components'][] = [
                'part_name' => !empty(trim((string) $fallbackPartName)) ? $fallbackPartName : 'Default Part',
                'base_part_name' => '',
                'description' => '',
                'material_number' => '',
                'material_name' => '',
                'mcs_number' => '',
                'vendor_type' => '',
                'hera_hardness' => '',
                'processes' => [],
            ];

            for ($row = $processHeaderRow + 1; $row <= $highestRow; $row++) {
                $stepNumber = $this->getCellValue($this->getCell($sheet, 2, $row));
                $processType = $this->getCellValue($this->getCell($sheet, 3, $row));

                if (empty(trim((string) $stepNumber)) && empty(trim((string) $processType))) {
                    continue;
                }

                $result['components'][0]['processes'][] = [
                    'step_number' => $stepNumber ?? count($result['components'][0]['processes']) + 1,
                    'process_type' => $processType ?? '',
                    'operation' => $this->getCellValue($this->getCell($sheet, 4, $row)) ?? '',
                    'color_code' => $this->getCellValue($this->getCell($sheet, 5, $row)) ?? '',
                    'chemical' => $this->getCellValue($this->getCell($sheet, 6, $row)) ?? '',
                    'hardener_code' => $this->getCellValue($this->getCell($sheet, 7, $row)) ?? '',
                    'temperature_c' => $this->getCellValue($this->getCell($sheet, 8, $row)) ?? '',
                    'wipes_count' => $this->getCellValue($this->getCell($sheet, 9, $row)) ?? '',
                    'rounds_count' => $this->getCellValue($this->getCell($sheet, 10, $row)) ?? '',
                    'duration' => $this->getCellValue($this->getCell($sheet, 11, $row)) ?? '',
                    'mesh_number' => $this->getCellValue($this->getCell($sheet, 12, $row)) ?? '',
                    'method' => '',
                ];
            }
        }

        return $result;
    }

    /**
     * Helper to parse a row as associative array
     */
    private function parseRowAsArray($sheet, int $row, array $columnMap): array
    {
        $result = [];
        foreach ($columnMap as $key => $col) {
            $colIndex = Coordinate::columnIndexFromString($col);
            $result[$key] = $this->getCellValue($this->getCell($sheet, $colIndex, $row));
        }
        return $result;
    }

    /**
     * Get cell by column index and row number
     */
    private function getCell($sheet, int $column, int $row)
    {
        $colLetter = Coordinate::stringFromColumnIndex($column);
        return $sheet->getCell($colLetter . $row);
    }

    /**
     * Get cell value, handling formulas and nulls
     */
    private function getCellValue(Cell $cell): ?string
    {
        $value = $cell->getValue();
        
        if ($cell->isFormula()) {
            $value = $cell->getCalculatedValue();
        }
        
        return $value;
    }

    /**
     * Parse date value from various formats
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            $date = \Carbon\Carbon::parse($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Save or update product
     */
    private function saveProduct(array $data): array
    {
        $productCode = $data['product_code'] ?? null;
        
        Log::debug('PPM Excel Import - Saving product', ['product_code' => $productCode, 'data' => $data]);
        
        if (empty($productCode)) {
            throw new \Exception('Product code is required but was not found in the Excel file');
        }
        
        $product = InsPpmProduct::where('product_code', $productCode)->first();
        
        $isNew = false;
        if (!$product) {
            $product = new InsPpmProduct();
            $isNew = true;
        }

        $product->dev_style = $data['dev_style'] ?? null;
        $product->product_code = $productCode;
        $product->color_way = $data['color_way'] ?? null;
        $product->production_date = $data['production_date'] ?? null;
        
        $product->save();

        return ['model' => $product, 'created' => $isNew];
    }

    /**
     * Save or update component with its processes
     */
    private function saveComponent(InsPpmProduct $product, array $data): array
    {
        $component = InsPpmComponent::where('product_id', $product->id)
            ->where('part_name', $data['part_name'] ?? '')
            ->first();
        
        $isNew = false;
        if (!$component) {
            $component = new InsPpmComponent();
            $isNew = true;
        }

        $component->product_id = $product->id;
        $component->part_name = $data['part_name'] ?? null;
        $component->base_part_name = $data['base_part_name'] ?? null;
        $component->material_number = $data['material_number'] ?? null;
        $component->material_name = $data['material_name'] ?? null;
        $component->mcs_number = $data['mcs_number'] ?? null;
        $component->vendor_type = $data['vendor_type'] ?? null;
        $component->hera_hardness = $data['hera_hardness'] ?? null;
        
        $component->save();

        $processesCreated = 0;
        $processesUpdated = 0;
        
        if (!empty($data['processes'])) {
            // Transform processes to the expected format (process_steps key)
            $steps = array_map(function($process) {
                return [
                    'step_number' => isset($process['step_number']) ? (int)$process['step_number'] : 1,
                    'process_type' => $process['process_type'] ?? $process['process'] ?? '',
                    'operation' => $process['operation'] ?? '',
                    'color_code' => $process['color_code'] ?? '',
                    'chemical' => $process['chemical'] ?? '',
                    'hardener_code' => $process['hardener_code'] ?? $process['hardener'] ?? '',
                    'temperature_c' => $process['temperature_c'] ?? $process['temp'] ?? '',
                    'wipes_count' => !empty($process['wipes_count']) ? (int)$process['wipes_count'] : null,
                    'rounds_count' => !empty($process['rounds_count']) ? (int)$process['rounds_count'] : null,
                    'duration' => $process['duration'] ?? $process['time'] ?? '',
                    'mesh_number' => $process['mesh_number'] ?? $process['mesh'] ?? '',
                    'method' => $process['method'] ?? '',
                ];
            }, $data['processes']);

            $processData = [
                'process_steps' => $steps,
                'imported_at' => now()->toISOString(),
            ];

            $process = InsPpmComponentsProcess::where('component_id', $component->id)->first();
            
            if (!$process) {
                $process = new InsPpmComponentsProcess();
                $processesCreated = 1;
            } else {
                $processesUpdated = 1;
            }

            $process->component_id = $component->id;
            $process->process_data = $processData;
            $process->save();
        }

        return [
            'created' => $isNew,
            'processes_created' => $processesCreated,
            'processes_updated' => $processesUpdated,
        ];
    }

    /**
     * Validate Excel file before import
     */
    public function validate(string $filePath): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        try {
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Map extension to reader
            $readerMap = [
                'xlsx' => 'Xlsx',
                'xls' => 'Xls',
                'csv' => 'Csv',
                'ods' => 'Ods',
                'slk' => 'Slk',
                'xml' => 'Xml',
                'gnumeric' => 'Gnumeric',
            ];
            
            if (!isset($readerMap[$fileExtension])) {
                $result['valid'] = false;
                $result['errors'][] = 'Invalid file format. Please upload an Excel file (.xlsx, .xls, .csv)';
                return $result;
            }

            if (!is_readable($filePath)) {
                $result['valid'] = false;
                $result['errors'][] = 'Cannot read the file';
                return $result;
            }

            // Create reader for the specific format
            $reader = IOFactory::createReader($readerMap[$fileExtension]);
            
            // For CSV files, set additional options
            if ($fileExtension === 'csv') {
                $reader->setInputEncoding('UTF-8');
            }
            
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            if ($sheet->getHighestRow() < 8) {
                $result['valid'] = false;
                $result['errors'][] = 'File appears to be empty or has insufficient data';
            }

        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = 'Error reading file: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Generate Excel template for process import
     * 
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Process Template');

        $lastCol = 'L'; // A-L = 12 columns

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(14);
        $sheet->getColumnDimension('K')->setWidth(14);
        $sheet->getColumnDimension('L')->setWidth(14);

        // ===== PRODUCT INFORMATION =====
        $sheet->setCellValue('A1', 'PRODUCT INFORMATION');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(12);
        $sheet->mergeCells('A1:' . $lastCol . '1');

        // Product headers (Row 2)
        $productHeaders = [
            'A' => 'No.',
            'B' => 'Product Code',
            'C' => 'Dev Style',
            'D' => 'Part Name',
        ];
        foreach ($productHeaders as $col => $header) {
            $sheet->setCellValue($col . '2', $header);
            $sheet->getStyle($col . '2')->getFont()->setBold(true);
            $sheet->getStyle($col . '2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($col . '2')->getFill()->getStartColor()->setRGB('D9D9D9');
        }

        // Sample product data (Row 3) — red to indicate example data
        $sheet->setCellValue('A3', '1');
        $sheet->setCellValue('B3', 'IX1194-500');
        $sheet->setCellValue('C3', 'W AIR ZOOM PEGASUS 42 TB - IX1194');
        $sheet->setCellValue('D3', 'SWOOSH MEDIAL 1');
        $sheet->getStyle('B3:D3')->getFont()->getColor()->setRGB('FF0000');

        // ===== PROCESS STEPS =====
        $sheet->setCellValue('A5', 'PROCESS STEPS');
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A5')->getFont()->setSize(12);
        $sheet->mergeCells('A5:' . $lastCol . '5');

        // Process step headers (Row 6)
        $processHeaders = [
            'A' => 'No.',
            'B' => 'Step #',
            'C' => 'Process Type',
            'D' => 'Operation',
            'E' => 'Color Code',
            'F' => 'Chemical',
            'G' => 'Hardener Code',
            'H' => 'Temperature (°C)',
            'I' => 'Wipes Count',
            'J' => 'Rounds Count',
            'K' => 'Duration',
            'L' => 'Mesh Number',
        ];
        foreach ($processHeaders as $col => $header) {
            $sheet->setCellValue($col . '6', $header);
            $sheet->getStyle($col . '6')->getFont()->setBold(true);
            $sheet->getStyle($col . '6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($col . '6')->getFill()->getStartColor()->setRGB('D9D9D9');
        }

        // Sample process steps matching real-world example (Rows 7-16) — red to indicate example data
        $sampleSteps = [
            [1,  1,  'CLEANER',              'CLEANING',  'CLEAR', 'NO. 29 [SB]', 'N/A', 'R/T or NIR', '',  2,  '4m-5m',   ''],
            [2,  2,  'PRIMER',               'PRIMING',   'CLEAR', 'P-001',       'N/A', 'R/T or NIR', '',  4,  '10m-15m', '200'],
            [3,  3,  'MAIN',                 '',          '',      'A 4%',        '',    'R/T or NIR', '',  8,  '10m-15m', '200'],
            [4,  4,  'MAIN',                 '',          '',      'A 4%',        '',    'R/T or NIR', '',  2,  '10m-15m', '200'],
            [5,  5,  'MAIN',                 '',          '',      'A 4%',        '',    'R/T or NIR', '',  2,  '10m-15m', '200'],
            [6,  6,  'TAKE OUT MATERIAL',    'TAKE OUT',  '',      '',            '',    '',           '',  '',  '',       ''],
            [7,  7,  'INSPECTION',           '',          '',      '',            '',    '',           '',  '',  '',       ''],
            [8,  8,  'AGING TIME',           'TAKEOUT',   '',      '',            '',    '',           '',  '',  '4 hours',''],
            [9,  9,  'PACKING',              '',          '',      '',            '',    '',           '',  '',  '',       ''],
            [10, 10, 'MOVE TO NEXT PROCESS', '',          '',      '',            '',    '',           '',  '',  '',       ''],
        ];

        $row = 7;
        foreach ($sampleSteps as $step) {
            [$no, $stepNum, $processType, $operation, $colorCode, $chemical, $hardener, $temp, $wipes, $rounds, $duration, $mesh] = $step;
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, $stepNum);
            $sheet->setCellValue('C' . $row, $processType);
            $sheet->setCellValue('D' . $row, $operation);
            $sheet->setCellValue('E' . $row, $colorCode);
            $sheet->setCellValue('F' . $row, $chemical);
            $sheet->setCellValue('G' . $row, $hardener);
            $sheet->setCellValue('H' . $row, $temp);
            $sheet->setCellValue('I' . $row, $wipes);
            $sheet->setCellValue('J' . $row, $rounds);
            $sheet->setCellValue('K' . $row, $duration);
            $sheet->setCellValue('L' . $row, $mesh);
            // Style non-empty cells red (example data indicator)
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->getColor()->setRGB('FF0000');
            $row++;
        }

        // Add empty rows for user input
        for ($i = 1; $i <= 10; $i++) {
            $sheet->setCellValue('A' . $row, $row - 6);
            $row++;
        }

        return $spreadsheet;
    }

    /**
     * Download Excel template
     */
    public function downloadTemplate()
    {
        $spreadsheet = $this->generateTemplate();
        $filename = 'ppm-process-import-template-' . date('Y-m-d') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
