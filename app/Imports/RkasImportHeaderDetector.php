<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;

class RkasImportHeaderDetector
{
    private const HEADER_KEYWORDS = [
        'no_urut',
        'kode_rekening',
        'kode_program',
        'uraian',
        'volume',
        'satuan',
        'tarif',
        'jumlah',
    ];

    private const COLUMN_ALIASES = [
        'tarif_harga' => 'tarif',
    ];

    private const SCAN_LIMIT = 20;

    private const MIN_MATCH = 4;

    public static function detect(string $filePath): int
    {
        $rows = self::readRows($filePath);

        return self::detectFromRows($rows);
    }

    /**
     * @return array{start_row: int, columns: array<string, int>}
     */
    public static function detectColumns(string $filePath): array
    {
        $rows = self::readRows($filePath);

        $headingRow = self::detectFromRows($rows);

        $columns = [];
        $lastHeaderIndex = $headingRow - 1;

        for ($i = $headingRow - 1; $i < count($rows); $i++) {
            $found = false;
            foreach ($rows[$i] ?? [] as $index => $cell) {
                $key = self::normalizeCell($cell);
                if (isset(self::COLUMN_ALIASES[$key])) {
                    $key = self::COLUMN_ALIASES[$key];
                }
                if (in_array($key, self::HEADER_KEYWORDS, true) && !isset($columns[$key])) {
                    $columns[$key] = $index + 1;
                    $found = true;
                }
            }

            if ($found) {
                $lastHeaderIndex = $i;
            } else {
                break;
            }
        }

        return [
            'start_row' => $lastHeaderIndex + 2,
            'columns' => $columns,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function readRows(string $filePath): array
    {
        $import = new class implements WithMultipleSheets {
            /**
             * @return array<int, object>
             */
            public function sheets(): array
            {
                return [0 => new class {
                }];
            }
        };

        $sheets = Excel::toArray($import, $filePath);

        return $sheets[0] ?? [];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private static function detectFromRows(array $rows): int
    {
        $limit = min(count($rows), self::SCAN_LIMIT);

        for ($i = 0; $i < $limit; $i++) {
            if (self::isHeaderRow($rows[$i] ?? [])) {
                return $i + 1;
            }
        }

        return 1;
    }

    /**
     * @param array<int, mixed> $row
     */
    private static function isHeaderRow(array $row): bool
    {
        $cells = array_map(
            static fn (mixed $value): string => self::normalizeCell($value),
            $row
        );

        $matches = 0;
        foreach (self::HEADER_KEYWORDS as $keyword) {
            if (in_array($keyword, $cells, true)) {
                $matches++;
            }
        }

        return $matches >= self::MIN_MATCH;
    }

    private static function normalizeCell(mixed $value): string
    {
        return (string) preg_replace(
            '/[^a-z0-9]+/',
            '_',
            strtolower(trim((string) $value))
        );
    }
}
