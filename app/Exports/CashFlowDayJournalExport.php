<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CashFlowDayJournalExport implements FromArray, ShouldAutoSize
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Carbon $date,
        private readonly Collection $rows,
    ) {}

    /**
     * @return array<int, array<int, string|float>>
     */
    public function array(): array
    {
        $exportRows = [
            ['Cash Flow Day Journal', $this->date->format('d M Y')],
            [],
            ['Amount', 'Debit / Credit', 'Journal', 'Remarks', 'Category'],
        ];

        foreach ($this->rows as $row) {
            $exportRows[] = [
                (float) $row['amount'],
                (string) $row['direction'],
                (string) $row['journal'],
                (string) ($row['remarks'] ?: 'No remarks'),
                (string) $row['category'],
            ];
        }

        return $exportRows;
    }
}
