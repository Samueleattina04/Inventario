<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esolver_detail', function (Blueprint $table) {
            $table->id();
            $table->string('mag', 20)->nullable();
            $table->string('article_code', 100)->index();
            $table->string('description', 500)->nullable();
            $table->string('lot', 200)->nullable();
            $table->string('um', 20)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esolver_detail');
    }
};
