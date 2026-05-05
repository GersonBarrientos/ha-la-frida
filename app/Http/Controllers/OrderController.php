<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
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
        return response()->json(Producto::where('estado', 'Activo')->get());
    }

    // Validación de stock en milisegundos y creación de pedido
    public function submitOrder(Request $request)
    {
        $validated = $request->validate([
            'id_mesa' => 'required|exists:Mesa,id_mesa',
            'items' => 'required|array',
            'items.*.id_producto' => 'required|exists:Producto,id_producto',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.notas' => 'nullable|string|max:500',
            'nombre_cliente' => 'nullable|string|max:255',
            'nit_cliente' => 'nullable|string|max:50'
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

            // 2. CREACIÓN DEL PEDIDO USANDO EL SP
            $idUsuario = Auth::user()->id_usuario;
            $idMesa = $validated['id_mesa'];
            
            $result = DB::select("DECLARE @nuevo_pedido INT; EXEC sp_RegistrarPedido @p_id_mesa=?, @p_id_usuario=?, @p_id_pedido=@nuevo_pedido OUTPUT; SELECT @nuevo_pedido AS id_pedido;", [$idMesa, $idUsuario]);
            
            $idPedido = $result[0]->id_pedido ?? null;

            if (!$idPedido) {
                throw new \Exception("La mesa no está Libre o falló el registro.");
            }

            // Guardar datos del cliente en el pedido
            Pedido::where('id_pedido', $idPedido)->update([
                'nombre_cliente' => $validated['nombre_cliente'] ?? 'Consumidor Final',
                'nit_cliente' => $validated['nit_cliente'] ?? 'C/F'
            ]);

            // 3. INSERCIÓN DE DETALLES
            foreach ($validated['items'] as $item) {
                $producto = Producto::find($item['id_producto']);
                DetallePedido::create([
                    'id_pedido' => $idPedido,
                    'id_producto' => $producto->id_producto,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $producto->precio,
                    'estado_cocina' => 'Recibido',
                    'notas' => $item['notas'] ?? null
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Orden enviada a cocina con éxito', 'id_pedido' => $idPedido]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar la orden', 'details' => $e->getMessage()], 500);
        }
    }
    
    // Proceso de Cobro
    public function cobrarPedido(Request $request)
    {
        $validated = $request->validate([
            'id_pedido' => 'required|exists:Pedido,id_pedido',
            'metodo_pago' => 'required|string',
            'nombre_cliente' => 'nullable|string|max:255',
            'nit_cliente' => 'nullable|string|max:50'
        ]);

        try {
            DB::statement("EXEC sp_ProcesarPago ?, ?, ?, ?", [
                $validated['id_pedido'],
                $validated['metodo_pago'],
                $validated['nombre_cliente'] ?? null,
                $validated['nit_cliente'] ?? null
            ]);

            $factura = DB::table('Factura')
                ->where('id_pedido', $validated['id_pedido'])
                ->orderByDesc('id_factura')
                ->first();

            return response()->json([
                'message' => 'Pago procesado y mesa liberada correctamente',
                'factura' => $factura
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el pago', 'details' => $e->getMessage()], 500);
        }
    }
}
