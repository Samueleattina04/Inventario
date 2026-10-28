<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('article_code', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('settore_code', 20)->nullable()->index();
            $table->string('settore', 200)->nullable();
            $table->string('linea_code', 20)->nullable()->index();
            $table->string('linea', 200)->nullable();
            $table->string('macrofamiglia_code', 20)->nullable()->index();
            $table->string('macrofamiglia', 200)->nullable();
            $table->string('famiglia_code', 20)->nullable()->index();
            $table->string('famiglia', 200)->nullable();
            $table->string('classe_inv', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
