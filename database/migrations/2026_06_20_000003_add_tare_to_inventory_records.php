<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->decimal('tare', 10, 4)->nullable()->after('total_weight');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropColumn('tare');
        });
    }
};
