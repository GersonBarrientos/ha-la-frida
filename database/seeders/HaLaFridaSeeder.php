<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HaLaFridaSeeder extends Seeder
{
    public function run(): void
    {
        echo "Seeding with raw SQL batches...\n";

        // ROLES
        DB::unprepared("SET IDENTITY_INSERT [Rol] ON; 
            INSERT INTO [Rol] (id_rol, descripcion) VALUES (1, 'Admin'), (2, 'Mesero'), (3, 'Cocinero');
            SET IDENTITY_INSERT [Rol] OFF;");

        // USUARIOS
        DB::unprepared("SET IDENTITY_INSERT [Usuario] ON;
            INSERT INTO [Usuario] (id_usuario, nombre_completo, correo, pin_acceso, id_rol, estado) VALUES 
            (1, 'Administrador', 'admin@halafrida.com', '1234', 1, 'Activo'),
            (2, 'Mesero Principal', 'mesero@halafrida.com', '5678', 2, 'Activo'),
            (3, 'Jefe de Cocina', 'cocina@halafrida.com', '9012', 3, 'Activo');
            SET IDENTITY_INSERT [Usuario] OFF;");

        // MESAS
        $mesaSql = "SET IDENTITY_INSERT [Mesa] ON; ";
        for ($i = 1; $i <= 10; $i++) {
            $cap = ($i % 2 == 0) ? 4 : 2;
            $mesaSql .= "INSERT INTO [Mesa] (id_mesa, capacidad, estado) VALUES ($i, $cap, 'Libre'); ";
        }
        $mesaSql .= "SET IDENTITY_INSERT [Mesa] OFF;";
        DB::unprepared($mesaSql);

        // CATEGORIAS
        DB::unprepared("SET IDENTITY_INSERT [Categoria] ON;
            INSERT INTO [Categoria] (id_categoria, nombre_cat) VALUES (1, 'Tacos'), (2, 'Burritos'), (3, 'Bebidas');
            SET IDENTITY_INSERT [Categoria] OFF;");

        // INSUMOS
        DB::unprepared("SET IDENTITY_INSERT [Insumo] ON;
            INSERT INTO [Insumo] (id_insumo, nombre_insumo, unidad_medida, stock_actual, estado) VALUES 
            (1, 'Carne al Pastor', 'gramos', 5000, 'Activo'),
            (2, 'Tortilla de Maíz', 'unidades', 200, 'Activo'),
            (3, 'Queso', 'gramos', 2000, 'Activo');
            SET IDENTITY_INSERT [Insumo] OFF;");

        // PRODUCTOS
        DB::unprepared("SET IDENTITY_INSERT [Producto] ON;
            INSERT INTO [Producto] (id_producto, nombre_prod, precio, id_categoria, estado) VALUES 
            (1, 'Tacos al Pastor (3x)', 35.00, 1, 'Activo'),
            (2, 'Burrito Especial', 45.00, 2, 'Activo');
            SET IDENTITY_INSERT [Producto] OFF;");

        // RECETAS
        DB::unprepared("SET IDENTITY_INSERT [Receta] ON;
            INSERT INTO [Receta] (id_receta, id_producto, id_insumo, cantidad_necesaria) VALUES 
            (1, 1, 1, 150), (2, 1, 2, 3), (3, 2, 1, 100), (4, 2, 3, 50);
            SET IDENTITY_INSERT [Receta] OFF;");
    }
}
