<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['name', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    public function activeAreas()
    {
        return $this->hasMany(Area::class)->where('active', true);
    }
}
