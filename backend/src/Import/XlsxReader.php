<?php

declare(strict_types=1);

namespace App\Import;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

final class XlsxReader
{
    /**
     * Reads the first sheet; the first row is the header.
     *
     * @return array<int, array<string, mixed>> spreadsheet row number => header => cell value (empty rows skipped)
     */
    public function read(string $path): array
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $rows = $sheet->toArray(null, true, false, false);
        $headers = array_map(fn (mixed $h) => trim((string) $h), array_shift($rows) ?? []);
        $missing = array_diff(ProductRowParser::REQUIRED_COLUMNS, $headers);
        if ($missing !== []) {
            throw new \RuntimeException('В файле нет обязательных колонок: ' . implode(', ', $missing));
        }

        $result = [];
        foreach ($rows as $i => $row) {
            if (array_filter($row, fn (mixed $v) => $v !== null && $v !== '') === []) {
                continue;
            }
            // shortcut: duplicate header names keep the last column, fine for the given format
            $result[$i + 2] = array_combine($headers, $row);
        }

        return $result;
    }
}
