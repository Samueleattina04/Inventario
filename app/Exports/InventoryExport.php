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
            ->where('hidden', false)
            ->orderBy('created_at');

        if (! empty($this->filters['date_from'])) {
            $from = $this->filters['date_from'] . ' ' . (! empty($this->filters['time_from']) ? $this->filters['time_from'] . ':00' : '00:00:00');
            $query->where('created_at', '>=', $from);
        }
        if (! empty($this->filters['date_to'])) {
            $to = $this->filters['date_to'] . ' ' . (! empty($this->filters['time_to']) ? $this->filters['time_to'] . ':59' : '23:59:59');
            $query->where('created_at', '<=', $to);
        }
        if (! empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }
        if (! empty($this->filters['area_id'])) {
            $query->where('area_id', $this->filters['area_id']);
        }
        if (! empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }
        if (! empty($this->filters['um'])) {
            $query->where('um', $this->filters['um']);
        }
        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('article_code', 'like', "%{$search}%")
                  ->orWhere('lot', 'like', "%{$search}%");
            });
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
            'ID',
            'Operatore',
            'Data/Ora',
            'Magazzino',
            'Area',
            'Codice Articolo',
            'Descrizione',
            'UM',
            'Lotto',
            'Scadenza',
            'Quantità',
            'DB Provenienza',
            'Pezzi campione',
            'Peso campione (kg)',
            'Peso totale (kg)',
            'Tara (kg)',
            'Note',
        ];
    }

    public function map($record): array
    {
        return [
            $record->id,
            $record->user?->name ?? '',
            $record->created_at?->format('d/m/Y H:i:s') ?? '',
            $record->warehouse?->name ?? '',
            $record->area?->name ?? '',
            $record->article_code,
            $record->description,
            $record->um ?? '',
            $record->lot ?? '',
            $record->expiry_date?->format('d/m/Y') ?? '',
            $record->quantity,
            $record->db_source_label,
            $record->sample_count,
            $record->sample_weight,
            $record->total_weight,
            $record->tare,
            $record->notes ?? '',
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
