<?php

namespace App\Exports;

use App\Models\InventoryRecord;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        $query = InventoryRecord::with(['user', 'warehouse', 'area'])
            ->orderBy('created_at');

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }
        if (! empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }
        if (! empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }
        if (! empty($this->filters['source'])) {
            match ($this->filters['source']) {
                'sqlsrv'    => $query->where(function ($q) {
                                    $q->where('db_source', 'sqlsrv')->orWhere('db_source', 'sqlsrv+access');
                                }),
                'access'    => $query->where('db_source', 'access'),
                'not_found' => $query->where('db_source', 'not_found'),
                default     => null,
            };
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Operatore',
            'Data/Ora',
            'Magazzino',
            'Area',
            'Codice Articolo',
            'Descrizione',
            'UM',
            'Lotto',
            'Quantità',
            'DB Provenienza',
        ];
    }

    public function map($record): array
    {
        return [
            $record->user?->name ?? '',
            $record->created_at?->format('d/m/Y H:i:s') ?? '',
            $record->warehouse?->name ?? '',
            $record->area?->name ?? '',
            $record->article_code,
            $record->description,
            $record->um ?? '',
            $record->lot ?? '',
            $record->quantity,
            $record->db_source_label,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'],
                ],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ],
        ];
    }
}
