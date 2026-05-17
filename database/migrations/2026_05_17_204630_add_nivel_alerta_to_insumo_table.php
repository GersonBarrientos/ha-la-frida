<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Insumo', function (Blueprint $table) {
            $table->decimal('nivel_alerta', 8, 2)->default(10)->after('stock_actual');
        });
    }

    public function down(): void
    {
        Schema::table('Insumo', function (Blueprint $table) {
            $table->dropColumn('nivel_alerta');
        });
    }
};
