<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Receta;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    // Obtener mesas y menú
    public function getMesas()
    {
        return response()->json(Mesa::orderBy('id_mesa')->get());
    }

    public function getPedidoActivo($id_mesa)
    {
        $pedido = Pedido::with(['detalles.producto'])
            ->where('id_mesa', $id_mesa)
            ->whereIn('estado_pedido', ['Recibido', 'En Preparación'])
            ->latest('fecha_hora')
            ->first();

        return response()->json($pedido);
    }

    public function getMenu()
    {
        return response()->json(
            Producto::with('categoria')->where('estado', 'Activo')->get()
        );
    }

    // Validación de stock y creación de pedido (PostgreSQL compatible — sin SPs)
    public function submitOrder(Request $request)
    {
        $validated = $request->validate([
            'id_mesa' => 'required|exists:Mesa,id_mesa',
            'items' => 'required|array',
            'items.*.id_producto' => 'required|exists:Producto,id_producto',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.notas' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // 1. VALIDACIÓN ESTRICTA DE INVENTARIO
            foreach ($validated['items'] as $item) {
                $producto = Producto::with('recetas.insumo')->findOrFail($item['id_producto']);
                
                foreach ($producto->recetas as $receta) {
                    $insumo = $receta->insumo;
                    $cantidadNecesariaTotal = $receta->cantidad_necesaria * $item['cantidad'];

                    if ($insumo->stock_actual < $cantidadNecesariaTotal) {
                        return response()->json([
                            'error' => 'Stock insuficiente',
                            'message' => "No hay suficiente '{$insumo->nombre_insumo}' para preparar '{$producto->nombre_prod}'."
                        ], 400);
                    }
                }
            }

            // 2. Verificar mesa libre
            $mesa = Mesa::findOrFail($validated['id_mesa']);
            if ($mesa->estado !== 'Libre') {
                return response()->json(['error' => 'La mesa no está libre.'], 400);
            }

            // 3. Crear pedido
            $idUsuario = Auth::user()->id_usuario;
            $pedido = Pedido::create([
                'id_mesa' => $validated['id_mesa'],
                'id_usuario' => $idUsuario,
                'estado_pedido' => 'Recibido',
            ]);

            // 4. Marcar mesa como ocupada
            $mesa->estado = 'Ocupada';
            $mesa->save();

            // 5. INSERCIÓN DE DETALLES + descuento de inventario
            foreach ($validated['items'] as $item) {
                $producto = Producto::find($item['id_producto']);
                DetallePedido::create([
                    'id_pedido' => $pedido->id_pedido,
                    'id_producto' => $producto->id_producto,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $producto->precio,
                    'estado_cocina' => 'Recibido',
                    'notas' => $item['notas'] ?? null
                ]);

                // Descontar inventario (simula el trigger de SQL Server)
                $recetas = Receta::where('id_producto', $producto->id_producto)->get();
                foreach ($recetas as $receta) {
                    Insumo::where('id_insumo', $receta->id_insumo)->decrement(
                        'stock_actual',
                        $receta->cantidad_necesaria * $item['cantidad']
                    );
                }
            }

            DB::commit();
            return response()->json(['message' => 'Orden enviada a cocina con éxito', 'id_pedido' => $pedido->id_pedido]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar la orden', 'details' => $e->getMessage()], 500);
        }
    }
    
    // Proceso de Cobro (PostgreSQL compatible — sin SPs)
    public function cobrarPedido(Request $request)
    {
        $validated = $request->validate([
            'id_pedido' => 'required|exists:Pedido,id_pedido',
            'metodo_pago' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $pedido = Pedido::findOrFail($validated['id_pedido']);

            // Calcular total
            $total = DetallePedido::where('id_pedido', $pedido->id_pedido)
                ->selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0) as total')
                ->value('total');

            // Generar número de factura
            $numeroFactura = 'FACT-' . now()->format('Ymd') . '-' . $pedido->id_pedido;

            // Crear factura
            DB::table('Factura')->insert([
                'numero_factura' => $numeroFactura,
                'total' => $total,
                'metodo_pago' => $validated['metodo_pago'],
                'id_pedido' => $pedido->id_pedido,
                'fecha_pago' => now(),
            ]);

            // Actualizar estado del pedido
            $pedido->estado_pedido = 'Pagado';
            $pedido->save();

            // Liberar mesa
            Mesa::where('id_mesa', $pedido->id_mesa)->update(['estado' => 'Libre']);

            $factura = DB::table('Factura')
                ->where('id_pedido', $pedido->id_pedido)
                ->orderByDesc('id_factura')
                ->first();

            DB::commit();

            return response()->json([
                'message' => 'Pago procesado y mesa liberada correctamente',
                'factura' => $factura
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar el pago', 'details' => $e->getMessage()], 500);
        }
    }
}
