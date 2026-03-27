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

        // Check if this is the new flat template format
        // New format has "Product Code *" in row 2, column B
        $isNewFormat = false;
        for ($row = 1; $row <= min(10, $highestRow); $row++) {
            for ($col = 1; $col <= min(5, $highestColumnIndex); $col++) {
                $cellValue = $this->getCellValue($this->getCell($sheet, $col, $row));
                if (strpos($cellValue ?? '', 'Product Code *') !== false || 
                    strpos($cellValue ?? '', 'Part Name *') !== false) {
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
            $productData = $this->parseRowAsArray($sheet, $productHeaderRow, [
                'no' => 'A',
                'product_code' => 'B',
                'dev_style' => 'C',
                'color_way' => 'D',
                'production_date' => 'E',
            ]);
            
            // Find first data row
            for ($row = $productHeaderRow + 1; $row <= $highestRow; $row++) {
                $productCode = $this->getCellValue($this->getCell($sheet, 2, $row));
                if (!empty(trim($productCode))) {
                    $result['product'] = [
                        'product_code' => $productCode,
                        'dev_style' => $this->getCellValue($this->getCell($sheet, 3, $row)) ?? '',
                        'color_way' => $this->getCellValue($this->getCell($sheet, 4, $row)) ?? '',
                        'production_date' => $this->parseDate($this->getCellValue($this->getCell($sheet, 5, $row))),
                    ];
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

        // Parse process steps
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
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(20);
        $sheet->getColumnDimension('K')->setWidth(15);
        $sheet->getColumnDimension('L')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(15);
        $sheet->getColumnDimension('N')->setWidth(15);
        $sheet->getColumnDimension('O')->setWidth(15);
        $sheet->getColumnDimension('P')->setWidth(15);
        $sheet->getColumnDimension('Q')->setWidth(20);
        $sheet->getColumnDimension('R')->setWidth(20);

        // ===== PRODUCT SECTION =====
        $sheet->setCellValue('A1', 'PRODUCT INFORMATION');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->mergeCells('A1:R1');
        
        // Product headers (Row 2)
        $productHeaders = ['A' => 'No.', 'B' => 'Product Code *', 'C' => 'Dev Style *', 'D' => 'Color Way *', 'E' => 'Production Date *'];
        foreach ($productHeaders as $col => $header) {
            $sheet->setCellValue($col . '2', $header);
            $sheet->getStyle($col . '2')->getFont()->setBold(true);
            $sheet->getStyle($col . '2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($col . '2')->getFill()->getStartColor()->setRGB('E0E0E0');
        }
        
        // Sample product data (Row 3)
        $sheet->setCellValue('A3', '1');
        $sheet->setCellValue('B3', 'PC-001');
        $sheet->setCellValue('C3', 'STYLE-2024-001');
        $sheet->setCellValue('D3', 'RED/BLACK');
        $sheet->setCellValue('E3', '2024-01-15');
        
        // ===== COMPONENT SECTION =====
        $sheet->setCellValue('A5', 'COMPONENT INFORMATION');
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A5')->getFont()->setSize(14);
        $sheet->mergeCells('A5:R5');
        
        // Component headers (Row 6)
        $componentHeaders = [
            'A' => 'No.',
            'B' => 'Part Name *',
            'C' => 'Base Part Name',
            'D' => 'Description',
            'E' => 'Material Number',
            'F' => 'Material Name',
            'G' => 'MCS Number',
            'H' => 'Vendor Type',
            'I' => 'Hera Hardness'
        ];
        foreach ($componentHeaders as $col => $header) {
            $sheet->setCellValue($col . '6', $header);
            $sheet->getStyle($col . '6')->getFont()->setBold(true);
            $sheet->getStyle($col . '6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($col . '6')->getFill()->getStartColor()->setRGB('E0E0E0');
        }
        
        // Sample component data (Row 7)
        $sheet->setCellValue('A7', '1');
        $sheet->setCellValue('B7', 'Upper');
        $sheet->setCellValue('C7', 'BASE-UPPER');
        $sheet->setCellValue('D7', 'Main upper part of the shoe');
        $sheet->setCellValue('E7', 'MAT-001');
        $sheet->setCellValue('F7', 'Synthetic Leather');
        $sheet->setCellValue('G7', 'MCS-001');
        $sheet->setCellValue('H7', 'Internal');
        $sheet->setCellValue('I7', ' Shore A');
        
        // ===== PROCESS STEPS SECTION =====
        $sheet->setCellValue('A9', 'PROCESS STEPS');
        $sheet->getStyle('A9')->getFont()->setBold(true);
        $sheet->getStyle('A9')->getFont()->setSize(14);
        $sheet->mergeCells('A9:R9');
        
        // Process step headers (Row 10)
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
            'M' => 'Method'
        ];
        foreach ($processHeaders as $col => $header) {
            $sheet->setCellValue($col . '10', $header);
            $sheet->getStyle($col . '10')->getFont()->setBold(true);
            $sheet->getStyle($col . '10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($col . '10')->getFill()->getStartColor()->setRGB('E0E0E0');
        }
        
        // Sample process steps (Rows 11-15)
        $sampleSteps = [
            ['step_number' => '1', 'process_type' => 'CLEANER', 'operation' => 'CLEANING COLOR/CODE', 'color_code' => 'CLEAR', 'chemical' => 'NO. 29 [SB]', 'hardener_code' => 'N/A', 'temperature_c' => '25', 'wipes_count' => '1', 'rounds_count' => '2', 'duration' => '4m-5m', 'mesh_number' => '200', 'method' => 'MANUAL'],
            ['step_number' => '2', 'process_type' => 'PRIMER', 'operation' => 'PRIMING', 'color_code' => 'CLEAR', 'chemical' => 'P-001', 'hardener_code' => 'H-001', 'temperature_c' => '25', 'wipes_count' => '', 'rounds_count' => '', 'duration' => '10m', 'mesh_number' => '', 'method' => 'SPRAY'],
            ['step_number' => '3', 'process_type' => 'BASE COAT', 'operation' => 'APPLYING BASE', 'color_code' => 'RED', 'chemical' => 'BC-001', 'hardener_code' => 'H-002', 'temperature_c' => '25', 'wipes_count' => '', 'rounds_count' => '2', 'duration' => '15m', 'mesh_number' => '100', 'method' => 'SPRAY'],
            ['step_number' => '4', 'process_type' => 'TOP COAT', 'operation' => 'APPLYING TOP COAT', 'color_code' => 'CLEAR', 'chemical' => 'TC-001', 'hardener_code' => 'H-003', 'temperature_c' => '30', 'wipes_count' => '', 'rounds_count' => '3', 'duration' => '20m', 'mesh_number' => '150', 'method' => 'SPRAY'],
        ];
        
        $row = 11;
        foreach ($sampleSteps as $index => $step) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $step['step_number']);
            $sheet->setCellValue('C' . $row, $step['process_type']);
            $sheet->setCellValue('D' . $row, $step['operation']);
            $sheet->setCellValue('E' . $row, $step['color_code']);
            $sheet->setCellValue('F' . $row, $step['chemical']);
            $sheet->setCellValue('G' . $row, $step['hardener_code']);
            $sheet->setCellValue('H' . $row, $step['temperature_c']);
            $sheet->setCellValue('I' . $row, $step['wipes_count']);
            $sheet->setCellValue('J' . $row, $step['rounds_count']);
            $sheet->setCellValue('K' . $row, $step['duration']);
            $sheet->setCellValue('L' . $row, $step['mesh_number']);
            $sheet->setCellValue('M' . $row, $step['method']);
            $row++;
        }
        
        // Add more empty rows for user to fill
        for ($i = 0; $i < 10; $i++) {
            $sheet->setCellValue('A' . $row, $row - 10);
            $row++;
        }
        
        // ===== INSTRUCTIONS =====
        $instructions = [
            '',
            'INSTRUCTIONS:',
            '1. Fill in the Product Information section (rows 2-3). Product Code, Dev Style, Color Way, and Production Date are required.',
            '2. Fill in the Component Information section (rows 6-7). Part Name is required.',
            '3. Fill in the Process Steps section starting from row 11. Add more rows as needed.',
            '4. For Process Steps, Step # and Process Type are required.',
            '5. Date format: YYYY-MM-DD (e.g., 2024-01-15)',
            '6. Keep the header rows (1, 2, 5, 6, 9, 10) unchanged.',
            '7. Save the file as .xlsx format before importing.'
        ];
        
        $row += 2;
        foreach ($instructions as $instruction) {
            $sheet->setCellValue('A' . $row, $instruction);
            if (strpos($instruction, 'INSTRUCTIONS:') !== false) {
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            }
            $sheet->mergeCells('A' . $row . ':R' . $row);
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
