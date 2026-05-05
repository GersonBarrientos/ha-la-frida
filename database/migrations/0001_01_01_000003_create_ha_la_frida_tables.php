<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates all Ha La Frida business tables for PostgreSQL (Supabase).
     */
    public function up(): void
    {
        // Tabla: Rol
        if (!Schema::hasTable('Rol')) {
            Schema::create('Rol', function (Blueprint $table) {
                $table->id('id_rol');
                $table->string('descripcion', 255);
            });
        }

        // Tabla: Categoria
        if (!Schema::hasTable('Categoria')) {
            Schema::create('Categoria', function (Blueprint $table) {
                $table->id('id_categoria');
                $table->string('nombre_cat', 100);
            });
        }

        // Tabla: Mesa
        if (!Schema::hasTable('Mesa')) {
            Schema::create('Mesa', function (Blueprint $table) {
                $table->id('id_mesa');
                $table->integer('capacidad');
                $table->string('estado', 100)->default('Libre');
            });
        }

        // Tabla: Insumo
        if (!Schema::hasTable('Insumo')) {
            Schema::create('Insumo', function (Blueprint $table) {
                $table->id('id_insumo');
                $table->string('nombre_insumo', 100);
                $table->string('unidad_medida', 100);
                $table->decimal('stock_actual', 10, 2)->default(0);
                $table->date('fecha_vencimiento')->nullable();
                $table->string('estado', 20)->default('Activo');
            });
        }

        // Tabla: Usuario
        if (!Schema::hasTable('Usuario')) {
            Schema::create('Usuario', function (Blueprint $table) {
                $table->id('id_usuario');
                $table->string('nombre_completo', 500);
                $table->string('correo', 255)->unique();
                $table->string('pin_acceso', 500)->unique();
                $table->string('estado', 20)->default('Activo');
                $table->unsignedBigInteger('id_rol');
                $table->foreign('id_rol')->references('id_rol')->on('Rol');
            });
        }

        // Tabla: Producto
        if (!Schema::hasTable('Producto')) {
            Schema::create('Producto', function (Blueprint $table) {
                $table->id('id_producto');
                $table->string('nombre_prod', 100);
                $table->string('descripcion', 500)->nullable();
                $table->decimal('precio', 10, 2)->default(0);
                $table->string('url_imagen', 500)->nullable();
                $table->string('estado', 20)->default('Activo');
                $table->unsignedBigInteger('id_categoria');
                $table->foreign('id_categoria')->references('id_categoria')->on('Categoria');
            });
        }

        // Tabla: Receta
        if (!Schema::hasTable('Receta')) {
            Schema::create('Receta', function (Blueprint $table) {
                $table->id('id_receta');
                $table->unsignedBigInteger('id_producto');
                $table->unsignedBigInteger('id_insumo');
                $table->decimal('cantidad_necesaria', 10, 2);
                $table->foreign('id_producto')->references('id_producto')->on('Producto');
                $table->foreign('id_insumo')->references('id_insumo')->on('Insumo');
            });
        }

        // Tabla: Pedido
        if (!Schema::hasTable('Pedido')) {
            Schema::create('Pedido', function (Blueprint $table) {
                $table->id('id_pedido');
                $table->string('estado_pedido', 100)->default('Recibido');
                $table->timestamp('fecha_hora')->useCurrent();
                $table->unsignedBigInteger('id_mesa');
                $table->unsignedBigInteger('id_usuario');
                $table->foreign('id_mesa')->references('id_mesa')->on('Mesa');
                $table->foreign('id_usuario')->references('id_usuario')->on('Usuario');
            });
        }

        // Tabla: Factura
        if (!Schema::hasTable('Factura')) {
            Schema::create('Factura', function (Blueprint $table) {
                $table->id('id_factura');
                $table->string('numero_factura', 100)->unique();
                $table->decimal('total', 10, 2)->default(0);
                $table->string('metodo_pago', 50);
                $table->timestamp('fecha_pago')->useCurrent();
                $table->unsignedBigInteger('id_pedido');
                $table->foreign('id_pedido')->references('id_pedido')->on('Pedido');
            });
        }

        // Tabla: Detalle_Pedido
        if (!Schema::hasTable('Detalle_Pedido')) {
            Schema::create('Detalle_Pedido', function (Blueprint $table) {
                $table->id('id_detalle');
                $table->integer('cantidad');
                $table->decimal('precio_unitario', 10, 2);
                $table->string('notas', 500)->nullable();
                $table->string('estado_cocina', 100)->default('Recibido');
                $table->unsignedBigInteger('id_pedido');
                $table->unsignedBigInteger('id_producto');
                $table->foreign('id_pedido')->references('id_pedido')->on('Pedido');
                $table->foreign('id_producto')->references('id_producto')->on('Producto');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Detalle_Pedido');
        Schema::dropIfExists('Factura');
        Schema::dropIfExists('Pedido');
        Schema::dropIfExists('Receta');
        Schema::dropIfExists('Producto');
        Schema::dropIfExists('Insumo');
        Schema::dropIfExists('Usuario');
        Schema::dropIfExists('Mesa');
        Schema::dropIfExists('Categoria');
        Schema::dropIfExists('Rol');
    }
};
