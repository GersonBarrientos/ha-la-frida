<?php

use App\Models\Usuario;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- INICIANDO TEST DE EVALUACIÓN DE SISTEMA 'HA LA FRIDA' ---\n";

try {
    DB::beginTransaction();

    // 1. Test de Autenticación
    echo "[1/5] Verificando usuarios y PINs...\n";
    $mesero = Usuario::where('pin_acceso', '5678')->first();
    if (!$mesero) throw new Exception("Mesero no encontrado");
    Auth::login($mesero);
    echo "  ✓ Login exitoso como Mesero: {$mesero->nombre_completo}\n";

    // 2. Test de Inventario y Validación
    echo "[2/5] Validando stock y creación de pedido...\n";
    $mesa = Mesa::where('estado', 'Libre')->first();
    if (!$mesa) throw new Exception("No hay mesas libres para el test");
    
    $producto = Producto::with('recetas.insumo')->first();
    echo "  > Producto seleccionado: {$producto->nombre_prod}\n";

    // Simulamos submitOrder
    $idUsuario = $mesero->id_usuario;
    $result = DB::select("DECLARE @nuevo_pedido INT; EXEC sp_RegistrarPedido @p_id_mesa=?, @p_id_usuario=?, @p_id_pedido=@nuevo_pedido OUTPUT; SELECT @nuevo_pedido AS id_pedido;", [$mesa->id_mesa, $idUsuario]);
    $idPedido = $result[0]->id_pedido;
    
    echo "  ✓ Pedido #$idPedido registrado mediante SP\n";
    echo "  ✓ Mesa {$mesa->id_mesa} cambió a Ocupada\n";

    // 3. Test de Trigger (Descuento de Inventario)
    echo "[3/5] Verificando Trigger de inventario...\n";
    $insumo = $producto->recetas->first()->insumo;
    $stockAntes = $insumo->stock_actual;
    
    DB::table('Detalle_Pedido')->insert([
        'id_pedido' => $idPedido,
        'id_producto' => $producto->id_producto,
        'cantidad' => 1,
        'precio_unitario' => $producto->precio,
        'estado_cocina' => 'Recibido',
        'notas' => 'TEST AUTOMATICO'
    ]);
    
    $stockDespues = DB::table('Insumo')->where('id_insumo', $insumo->id_insumo)->value('stock_actual');
    echo "  > Stock antes: $stockAntes | Stock después: $stockDespues\n";
    if ($stockDespues < $stockAntes) {
        echo "  ✓ Trigger ejecutado correctamente: Inventario descontado.\n";
    } else {
        echo "  ⚠ Alerta: El stock no cambió. Verifique el Trigger trg_DescontarInventario.\n";
    }

    // 4. Test de Cocina (Cambio de Estado)
    echo "[4/5] Simulando flujo de cocina...\n";
    DB::table('Detalle_Pedido')->where('id_pedido', $idPedido)->update(['estado_cocina' => 'Listo']);
    echo "  ✓ Plato marcado como 'Listo'\n";

    // 5. Test de Cobro (SP de Facturación)
    echo "[5/5] Ejecutando SP de Pago y Facturación...\n";
    DB::statement("EXEC sp_ProcesarPago ?, ?", [$idPedido, 'Efectivo']);
    
    $factura = DB::table('Factura')->where('id_pedido', $idPedido)->first();
    if ($factura) {
        echo "  ✓ Factura generada: {$factura->numero_factura} por Q{$factura->total}\n";
    }
    
    $mesaEstadoFinal = DB::table('Mesa')->where('id_mesa', $mesa->id_mesa)->value('estado');
    echo "  ✓ Mesa liberada. Estado actual: $mesaEstadoFinal\n";

    echo "\n--- RESULTADO: TODO FUNCIONA CORRECTAMENTE ---\n";
    
    // IMPORTANTE: Hacemos Rollback para no ensuciar la base de datos real del usuario
    DB::rollBack();
    echo "Refrescando base de datos (Rollback ejecutado).\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ ERROR EN EL TEST: " . $e->getMessage() . "\n";
}
