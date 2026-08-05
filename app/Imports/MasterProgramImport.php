<?php

namespace App\Imports;

use App\Models\MasterProgram;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterProgramImport implements WithMultipleSheets
{
    public int $importedCount = 0;
    public int $skippedCount = 0;

    /** @var array<int, string> */
    private array $rowErrors = [];

    /**
     * @return array<int, MasterProgramSheetImport>
     */
    public function sheets(): array
    {
        return [
            1 => new MasterProgramSheetImport($this),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getAllErrors(): array
    {
        return $this->rowErrors;
    }

    public function addError(string $error): void
    {
        $this->rowErrors[] = $error;
    }
}

class MasterProgramSheetImport implements ToCollection, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    private MasterProgramImport $parent;

    public function __construct(MasterProgramImport $parent)
    {
        $this->parent = $parent;
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $kode = $row['kode_kegiatan'] ?? null;
            $nama = $row['uraian'] ?? null;
            $program = $row['program'] ?? null;
            $subProgram = $row['sub_program'] ?? null;

            if ($kode === null || $nama === null || trim((string) $kode) === '' || trim((string) $nama) === '') {
                $this->parent->skippedCount++;

                continue;
            }

            try {
                $level = substr_count(trim((string) $kode), '.') + 1;

                MasterProgram::updateOrCreate(
                    ['kode' => trim((string) $kode)],
                    [
                        'nama' => trim((string) $nama),
                        'program' => trim((string) $program),
                        'sub_program' => trim((string) $subProgram),
                        'parent_id' => null,
                        'level' => $level,
                    ]
                );

                $this->parent->importedCount++;
            } catch (\Exception $e) {
                $this->parent->skippedCount++;
                $this->parent->addError('Baris ' . ($index + 2) . ': ' . $e->getMessage());
            }
        }
    }
}
