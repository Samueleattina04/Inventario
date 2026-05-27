<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            // Remove the enum CHECK constraint; allow any string (e.g. 'sqlsrv+access')
            $table->string('db_source', 50)->default('not_found')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->enum('db_source', ['sqlsrv', 'sqlsrv+access', 'access', 'not_found'])->default('not_found')->change();
        });
    }
};
