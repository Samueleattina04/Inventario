<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCategory extends Model
{
    protected $fillable = [
        'article_code', 'description',
        'settore_code', 'settore',
        'linea_code', 'linea',
        'macrofamiglia_code', 'macrofamiglia',
        'famiglia_code', 'famiglia',
        'classe_inv',
    ];
}
