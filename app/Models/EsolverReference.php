<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EsolverReference extends Model
{
    protected $table    = 'esolver_reference';
    protected $fillable = ['article_code', 'description', 'um', 'quantity', 'external_qty'];
}
