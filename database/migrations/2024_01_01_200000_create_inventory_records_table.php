<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('article_code');
            $table->string('description')->nullable();
            $table->string('um', 20)->nullable();
            $table->string('lot')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->enum('db_source', ['sqlsrv', 'access', 'not_found'])->default('not_found');
            // weight calculator fields (only when area has_weight_calculator = true)
            $table->integer('sample_count')->nullable();
            $table->decimal('sample_weight', 12, 4)->nullable();
            $table->decimal('total_weight', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_records');
    }
};
