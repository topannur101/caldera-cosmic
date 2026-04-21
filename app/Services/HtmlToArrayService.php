<?php

namespace App\Services;

class HtmlToArrayService
{
	/**
	 * Extract the first table from an HTML file and convert it to array rows.
	 *
	 * @param string $filePath Path to HTML file.
	 * @return array
	 */
	public function extract(string $filePath): array
	{
		$result = [
			'success' => false,
			'message' => '',
			'title' => '',
			'headers' => [],
			'rows' => [],
			'total_rows' => 0,
			'errors' => [],
		];

		try {
			if (!file_exists($filePath)) {
				throw new \Exception("File not found: {$filePath}");
			}

			if (!is_readable($filePath)) {
				throw new \Exception("File is not readable: {$filePath}");
			}

			$html = file_get_contents($filePath);
			if ($html === false || trim($html) === '') {
				throw new \Exception('HTML file is empty or unreadable.');
			}

			$internalErrors = libxml_use_internal_errors(true);

			$dom = new \DOMDocument();
			$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);

			$xpath = new \DOMXPath($dom);
			$titleNode = $xpath->query('//title')->item(0);
			$result['title'] = $titleNode ? trim($titleNode->textContent) : '';

			$tableNode = $xpath->query('//table')->item(0);
			if (!$tableNode) {
				throw new \Exception('No <table> found in HTML file.');
			}

			$rowNodes = $xpath->query('.//tr', $tableNode);
			if (!$rowNodes || $rowNodes->length === 0) {
				throw new \Exception('No table rows found in HTML file.');
			}

			$headers = [];
			$dataStartIndex = 1;

			$firstRow = $rowNodes->item(0);
			if ($firstRow) {
				$headerCells = $xpath->query('./th|./td', $firstRow);
				if ($headerCells && $headerCells->length > 0) {
					for ($i = 0; $i < $headerCells->length; $i++) {
						$headers[] = $this->normalizeHeader(
							$this->cleanCellValue($headerCells->item($i)->textContent)
						);
					}
				}
			}

			if (empty($headers)) {
				$dataStartIndex = 0;
				$firstDataRow = $rowNodes->item(0);
				$cellNodes = $xpath->query('./td|./th', $firstDataRow);
				$cellCount = $cellNodes ? $cellNodes->length : 0;

				for ($i = 1; $i <= $cellCount; $i++) {
					$headers[] = 'column_' . $i;
				}
			}

			for ($rowIndex = $dataStartIndex; $rowIndex < $rowNodes->length; $rowIndex++) {
				$rowNode = $rowNodes->item($rowIndex);
				$cellNodes = $xpath->query('./td|./th', $rowNode);

				if (!$cellNodes || $cellNodes->length === 0) {
					continue;
				}

				$row = [];
				$hasValue = false;

				for ($i = 0; $i < count($headers); $i++) {
					$value = null;

					if ($i < $cellNodes->length) {
						$rawValue = $this->cleanCellValue($cellNodes->item($i)->textContent);
						$value = $this->normalizeValue($rawValue);
					}

					if ($value !== null && $value !== '') {
						$hasValue = true;
					}

					$row[$headers[$i]] = $value;
				}

				if ($hasValue) {
					$result['rows'][] = $row;
				}
			}

			$result['headers'] = $headers;
			$result['total_rows'] = count($result['rows']);
			$result['success'] = true;
			$result['message'] = 'HTML extracted successfully.';

			libxml_clear_errors();
			libxml_use_internal_errors($internalErrors);
		} catch (\Throwable $e) {
			$result['errors'][] = $e->getMessage();
			$result['message'] = 'HTML parsing error: ' . $e->getMessage();
		}

		return $result;
	}

	private function normalizeHeader(?string $value): string
	{
		$value = strtolower((string) $value);
		$value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? '';
		$value = trim($value, '_');

		return $value !== '' ? $value : 'column';
	}

	private function cleanCellValue(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = str_replace("\xc2\xa0", ' ', $value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? '';
		$value = trim($value);

		if ($value === '' || strtolower($value) === 'nbsp') {
			return null;
		}

		return $value;
	}

	private function normalizeValue(?string $value)
	{
		if ($value === null) {
			return null;
		}

		$numeric = str_replace(',', '', $value);
		if (preg_match('/^-?\d+(\.\d+)?$/', $numeric) === 1) {
			return strpos($numeric, '.') !== false ? (float) $numeric : (int) $numeric;
		}

		return $value;
	}
}
