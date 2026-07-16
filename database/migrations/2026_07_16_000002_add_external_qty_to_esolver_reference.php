<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esolver_reference', function (Blueprint $table) {
            $table->decimal('external_qty', 15, 4)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('esolver_reference', function (Blueprint $table) {
            $table->dropColumn('external_qty');
        });
    }
};
