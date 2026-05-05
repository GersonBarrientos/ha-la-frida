<?php

namespace App\Http\Controllers;

use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Receta;
use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function getActiveOrders()
    {
        $ordenes = Pedido::with([
                'detalles' => fn($q) => $q->whereIn('estado_cocina', ['Recibido', 'En Preparación', 'Listo']),
                'detalles.producto',
                'mesa'
            ])
            ->whereIn('estado_pedido', ['Recibido', 'En Preparación'])
            ->orderBy('fecha_hora', 'asc')
            ->get();

        return response()->json(
            $ordenes->filter(fn($p) => $p->detalles->count() > 0)->values()
        );
    }

    public function updateStatus(Request $request, $id_detalle)
    {
        $validated = $request->validate([
            'estado_cocina' => 'required|in:Recibido,En Preparación,Listo,Entregado'
        ]);

        $detalle = DetallePedido::findOrFail($id_detalle);
        $detalle->estado_cocina = $validated['estado_cocina'];
        $detalle->save();

        return response()->json(['message' => 'Estado actualizado', 'detalle' => $detalle]);
    }

    /**
     * Botón de pánico: cancela un ítem y revierte el inventario descontado por el trigger.
     */
    public function cancelarDetalle(Request $request, $id_detalle)
    {
        $detalle = DetallePedido::with('producto')->findOrFail($id_detalle);

        DB::beginTransaction();
        try {
            // Revertir los insumos descontados por el trigger (restaurar stock)
            $recetas = Receta::where('id_producto', $detalle->id_producto)->get();
            foreach ($recetas as $receta) {
                Insumo::where('id_insumo', $receta->id_insumo)->increment(
                    'stock_actual',
                    $receta->cantidad_necesaria * $detalle->cantidad
                );
            }

            // Marcar el detalle como cancelado
            $detalle->estado_cocina = 'Cancelado';
            $detalle->notas = '[CANCELADO POR COCINA] ' . ($detalle->notas ?? '');
            $detalle->save();

            DB::commit();
            return response()->json([
                'message' => 'Ítem cancelado. Inventario restaurado.',
                'detalle' => $detalle
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
