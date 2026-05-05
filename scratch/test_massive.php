<?php

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use Illuminate\Support\Facades\DB;

function runMassiveTest() {
    echo "--- INICIANDO TEST MASIVO HA LA FRIDA ---\n";
    
    // 1. Snapshot de Inventario
    $stockInicial = Insumo::pluck('stock_actual', 'id_insumo')->toArray();
    echo "Snapshot de inventario capturado.\n";

    // 2. Simulación de 50 pedidos aleatorios
    echo "Generando 50 pedidos concurrentes...\n";
    $productos = Producto::where('estado', 'Activo')->get();
    
    for ($i = 0; $i < 50; $i++) {
        $id_mesa = rand(1, 10);
        
        DB::transaction(function() use ($id_mesa, $productos) {
            $pedido = Pedido::create([
                'id_mesa' => $id_mesa,
                'id_usuario' => 1, // Admin/Mesero por defecto
                'nombre_cliente' => 'Test Client ' . rand(1, 100),
                'nit_cliente' => 'CF',
                'estado' => 'Abierto'
            ]);

            // Agregar 2-5 productos por pedido
            $numItems = rand(2, 5);
            for ($j = 0; $j < $numItems; $j++) {
                $p = $productos->random();
                DetallePedido::create([
                    'id_pedido' => $pedido->id_pedido,
                    'id_producto' => $p->id_producto,
                    'cantidad' => rand(1, 3),
                    'precio_unitario' => $p->precio,
                    'estado_cocina' => 'Recibido'
                ]);
            }
        });
    }
    echo "50 pedidos creados exitosamente.\n";

    // 3. Simulación de Cocina (Procesar todo)
    echo "Simulando procesamiento masivo en cocina...\n";
    $detalles = DetallePedido::where('estado_cocina', 'Recibido')->get();
    foreach ($detalles as $d) {
        $d->update(['estado_cocina' => 'Listo']);
    }
    echo "Todos los ítems marcados como LISTOS.\n";

    // 4. Validación de Integridad
    $stockFinal = Insumo::pluck('stock_actual', 'id_insumo')->toArray();
    $errores = 0;
    foreach ($stockInicial as $id => $val) {
        if ($stockFinal[$id] > $val) {
            echo "ERR: Insumo {$id} aumentó stock inexplicablemente.\n";
            $errores++;
        }
    }

    if ($errores == 0) {
        echo "RESULTADO: Test de integridad de inventario PASADO.\n";
    } else {
        echo "RESULTADO: Se encontraron {$errores} inconsistencias.\n";
    }

    // 5. Simulación de Cobro Masivo
    echo "Simulando cobro y facturación masiva...\n";
    $pedidos = Pedido::where('estado', 'Abierto')->get();
    foreach ($pedidos as $p) {
        $total = DetallePedido::where('id_pedido', $p->id_pedido)->sum(DB::raw('cantidad * precio_unitario'));
        DB::statement("EXEC sp_ProcesarPago ?, ?, ?, ?", [$p->id_pedido, 'Efectivo', $p->nombre_cliente, $p->nit_cliente]);
    }
    echo "Facturación completada.\n";

    echo "--- TEST FINALIZADO ---";
}

// Para correrlo en Laravel tinker o via script:
runMassiveTest();
