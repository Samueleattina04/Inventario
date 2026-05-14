<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryRecord extends Model
{
    protected $fillable = [
        'user_id', 'warehouse_id', 'area_id',
        'article_code', 'description', 'um', 'lot',
        'quantity', 'db_source',
        'sample_count', 'sample_weight', 'total_weight',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:4',
            'sample_weight' => 'decimal:4',
            'total_weight'  => 'decimal:4',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function getDbSourceLabelAttribute(): string
    {
        return match ($this->db_source) {
            'sqlsrv'    => 'SQL Server',
            'access'    => 'Access',
            'not_found' => 'Non trovato',
            default     => $this->db_source,
        };
    }
}
