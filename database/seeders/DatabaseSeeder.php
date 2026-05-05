<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Uses direct inserts compatible with SQLite (for testing) and PostgreSQL (production).
     */
    public function run(): void
    {
        // ROLES
        DB::table('Rol')->insert([
            ['id_rol' => 1, 'descripcion' => 'Admin'],
            ['id_rol' => 2, 'descripcion' => 'Mesero'],
            ['id_rol' => 3, 'descripcion' => 'Cocinero'],
        ]);

        // USUARIOS
        DB::table('Usuario')->insert([
            ['id_usuario' => 1, 'nombre_completo' => 'Administrador', 'correo' => 'admin@halafrida.com', 'pin_acceso' => '1234', 'id_rol' => 1, 'estado' => 'Activo'],
            ['id_usuario' => 2, 'nombre_completo' => 'Mesero Principal', 'correo' => 'mesero@halafrida.com', 'pin_acceso' => '5678', 'id_rol' => 2, 'estado' => 'Activo'],
            ['id_usuario' => 3, 'nombre_completo' => 'Jefe de Cocina', 'correo' => 'cocina@halafrida.com', 'pin_acceso' => '9012', 'id_rol' => 3, 'estado' => 'Activo'],
        ]);

        // MESAS
        $mesas = [];
        for ($i = 1; $i <= 10; $i++) {
            $mesas[] = ['id_mesa' => $i, 'capacidad' => ($i % 2 == 0) ? 4 : 2, 'estado' => 'Libre'];
        }
        DB::table('Mesa')->insert($mesas);

        // CATEGORIAS
        DB::table('Categoria')->insert([
            ['id_categoria' => 1, 'nombre_cat' => 'Tacos'],
            ['id_categoria' => 2, 'nombre_cat' => 'Burritos'],
            ['id_categoria' => 3, 'nombre_cat' => 'Bebidas'],
        ]);

        // INSUMOS
        DB::table('Insumo')->insert([
            ['id_insumo' => 1, 'nombre_insumo' => 'Carne al Pastor', 'unidad_medida' => 'gramos', 'stock_actual' => 5000, 'estado' => 'Activo'],
            ['id_insumo' => 2, 'nombre_insumo' => 'Tortilla de Maíz', 'unidad_medida' => 'unidades', 'stock_actual' => 200, 'estado' => 'Activo'],
            ['id_insumo' => 3, 'nombre_insumo' => 'Queso', 'unidad_medida' => 'gramos', 'stock_actual' => 2000, 'estado' => 'Activo'],
        ]);

        // PRODUCTOS
        DB::table('Producto')->insert([
            ['id_producto' => 1, 'nombre_prod' => 'Tacos al Pastor (3x)', 'precio' => 35.00, 'id_categoria' => 1, 'estado' => 'Activo'],
            ['id_producto' => 2, 'nombre_prod' => 'Burrito Especial', 'precio' => 45.00, 'id_categoria' => 2, 'estado' => 'Activo'],
        ]);

        // RECETAS
        DB::table('Receta')->insert([
            ['id_receta' => 1, 'id_producto' => 1, 'id_insumo' => 1, 'cantidad_necesaria' => 150],
            ['id_receta' => 2, 'id_producto' => 1, 'id_insumo' => 2, 'cantidad_necesaria' => 3],
            ['id_receta' => 3, 'id_producto' => 2, 'id_insumo' => 1, 'cantidad_necesaria' => 100],
            ['id_receta' => 4, 'id_producto' => 2, 'id_insumo' => 3, 'cantidad_necesaria' => 50],
        ]);
    }
}
