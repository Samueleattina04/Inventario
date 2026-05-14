<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['warehouse_id', 'name', 'code', 'has_weight_calculator', 'active'];

    protected function casts(): array
    {
        return [
            'has_weight_calculator' => 'boolean',
            'active'                => 'boolean',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inventoryRecords()
    {
        return $this->hasMany(InventoryRecord::class);
    }
}
