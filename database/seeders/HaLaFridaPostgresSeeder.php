<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HaLaFridaPostgresSeeder extends Seeder
{
    public function run(): void
    {
        echo "Seeding Ha La Frida tables for PostgreSQL...\n";

        // ROLES
        if (DB::table('Rol')->count() === 0) {
            DB::table('Rol')->insert([
                ['id_rol' => 1, 'descripcion' => 'Admin'],
                ['id_rol' => 2, 'descripcion' => 'Mesero'],
                ['id_rol' => 3, 'descripcion' => 'Cocinero'],
            ]);
            // Reset sequence
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Rol\"', 'id_rol'), (SELECT MAX(id_rol) FROM \"Rol\"))");
        }

        // USUARIOS
        if (DB::table('Usuario')->count() === 0) {
            DB::table('Usuario')->insert([
                ['id_usuario' => 1, 'nombre_completo' => 'Administrador', 'correo' => 'admin@halafrida.com', 'pin_acceso' => '1234', 'id_rol' => 1, 'estado' => 'Activo'],
                ['id_usuario' => 2, 'nombre_completo' => 'Mesero Principal', 'correo' => 'mesero@halafrida.com', 'pin_acceso' => '5678', 'id_rol' => 2, 'estado' => 'Activo'],
                ['id_usuario' => 3, 'nombre_completo' => 'Jefe de Cocina', 'correo' => 'cocina@halafrida.com', 'pin_acceso' => '9012', 'id_rol' => 3, 'estado' => 'Activo'],
            ]);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Usuario\"', 'id_usuario'), (SELECT MAX(id_usuario) FROM \"Usuario\"))");
        }

        // MESAS
        if (DB::table('Mesa')->count() === 0) {
            $mesas = [];
            for ($i = 1; $i <= 10; $i++) {
                $mesas[] = [
                    'id_mesa' => $i,
                    'capacidad' => ($i % 2 == 0) ? 4 : 2,
                    'estado' => 'Libre',
                ];
            }
            DB::table('Mesa')->insert($mesas);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Mesa\"', 'id_mesa'), (SELECT MAX(id_mesa) FROM \"Mesa\"))");
        }

        // CATEGORIAS
        if (DB::table('Categoria')->count() === 0) {
            DB::table('Categoria')->insert([
                ['id_categoria' => 1, 'nombre_cat' => 'Tacos'],
                ['id_categoria' => 2, 'nombre_cat' => 'Burritos'],
                ['id_categoria' => 3, 'nombre_cat' => 'Bebidas'],
            ]);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Categoria\"', 'id_categoria'), (SELECT MAX(id_categoria) FROM \"Categoria\"))");
        }

        // INSUMOS
        if (DB::table('Insumo')->count() === 0) {
            DB::table('Insumo')->insert([
                ['id_insumo' => 1, 'nombre_insumo' => 'Carne al Pastor', 'unidad_medida' => 'gramos', 'stock_actual' => 5000, 'estado' => 'Activo'],
                ['id_insumo' => 2, 'nombre_insumo' => 'Tortilla de Maíz', 'unidad_medida' => 'unidades', 'stock_actual' => 200, 'estado' => 'Activo'],
                ['id_insumo' => 3, 'nombre_insumo' => 'Queso', 'unidad_medida' => 'gramos', 'stock_actual' => 2000, 'estado' => 'Activo'],
            ]);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Insumo\"', 'id_insumo'), (SELECT MAX(id_insumo) FROM \"Insumo\"))");
        }

        // PRODUCTOS
        if (DB::table('Producto')->count() === 0) {
            DB::table('Producto')->insert([
                ['id_producto' => 1, 'nombre_prod' => 'Tacos al Pastor (3x)', 'precio' => 35.00, 'id_categoria' => 1, 'estado' => 'Activo'],
                ['id_producto' => 2, 'nombre_prod' => 'Burrito Especial', 'precio' => 45.00, 'id_categoria' => 2, 'estado' => 'Activo'],
            ]);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Producto\"', 'id_producto'), (SELECT MAX(id_producto) FROM \"Producto\"))");
        }

        // RECETAS
        if (DB::table('Receta')->count() === 0) {
            DB::table('Receta')->insert([
                ['id_receta' => 1, 'id_producto' => 1, 'id_insumo' => 1, 'cantidad_necesaria' => 150],
                ['id_receta' => 2, 'id_producto' => 1, 'id_insumo' => 2, 'cantidad_necesaria' => 3],
                ['id_receta' => 3, 'id_producto' => 2, 'id_insumo' => 1, 'cantidad_necesaria' => 100],
                ['id_receta' => 4, 'id_producto' => 2, 'id_insumo' => 3, 'cantidad_necesaria' => 50],
            ]);
            DB::statement("SELECT setval(pg_get_serial_sequence('\"Receta\"', 'id_receta'), (SELECT MAX(id_receta) FROM \"Receta\"))");
        }

        echo "Seeding complete!\n";
    }
}
