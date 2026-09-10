<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EsolverDetail extends Model
{
    protected $table    = 'esolver_detail';
    protected $fillable = ['mag', 'article_code', 'description', 'lot', 'um', 'quantity'];
}
