<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Insumo', function (Blueprint $table) {
            $table->decimal('costo_unitario', 10, 2)->default(0)->after('nivel_alerta');
        });
    }

    public function down(): void
    {
        Schema::table('Insumo', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
