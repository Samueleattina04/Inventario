<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esolver_reference', function (Blueprint $table) {
            $table->id();
            $table->string('article_code')->index();
            $table->string('description')->nullable();
            $table->string('um', 20)->nullable();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esolver_reference');
    }
};
