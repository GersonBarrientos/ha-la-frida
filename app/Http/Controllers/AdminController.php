<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // === ESTADÍSTICAS DEL DASHBOARD ===
    public function getStats()
    {
        $ventasHoy = DB::select("SELECT * FROM vw_VentasDelDia");
        $inventarioCritico = DB::select("SELECT * FROM vw_InventarioCritico");
        
        // BI: Ventas por Categoría
        $ventasPorCat = DB::select("
            SELECT c.nombre_cat, SUM(dp.cantidad * dp.precio_unitario) as total
            FROM Detalle_Pedido dp
            JOIN Producto p ON dp.id_producto = p.id_producto
            JOIN Categoria c ON p.id_categoria = c.id_categoria
            JOIN Pedido ped ON dp.id_pedido = ped.id_pedido
            WHERE CAST(ped.fecha_hora AS DATE) = CAST(GETDATE() AS DATE)
            GROUP BY c.nombre_cat
        ");

        // BI: Tráfico por Hora
        $traficoHora = DB::select("
            SELECT DATEPART(HOUR, fecha_hora) as hora, COUNT(*) as pedidos
            FROM Pedido
            WHERE CAST(fecha_hora AS DATE) = CAST(GETDATE() AS DATE)
            GROUP BY DATEPART(HOUR, fecha_hora)
            ORDER BY hora
        ");

        $totalInsumos = Insumo::count();
        $totalProductos = Producto::where('estado', 'Activo')->count();

        return response()->json([
            'ventas_hoy' => $ventasHoy[0] ?? ['Cantidad_Facturas' => 0, 'Ingresos_Totales' => 0],
            'inventario_critico' => $inventarioCritico,
            'ventas_por_categoria' => $ventasPorCat,
            'trafico_hora' => $traficoHora,
            'total_insumos' => $totalInsumos,
            'total_productos' => $totalProductos,
        ]);
    }

    // === CATEGORÍAS ===
    public function getCategorias()
    {
        return response()->json(Categoria::all());
    }

    // === GESTIÓN DE INSUMOS ===
    public function getInsumos()
    {
        return response()->json(Insumo::orderBy('nombre_insumo')->get());
    }

    public function storeInsumo(Request $request)
    {
        $validated = $request->validate([
            'nombre_insumo' => 'required|string|max:100',
            'unidad_medida' => 'required|string|max:100',
            'stock_actual'  => 'required|numeric|min:0',
        ]);

        $insumo = Insumo::create($validated);
        return response()->json(['message' => 'Insumo registrado', 'insumo' => $insumo], 201);
    }

    public function updateInsumo(Request $request, $id_insumo)
    {
        $validated = $request->validate([
            'nombre_insumo' => 'required|string|max:100',
            'unidad_medida' => 'required|string|max:100',
            'stock_actual'  => 'required|numeric|min:0',
            'estado'        => 'required|in:Activo,Inactivo'
        ]);

        $insumo = Insumo::findOrFail($id_insumo);
        $insumo->update($validated);
        return response()->json(['message' => 'Insumo actualizado', 'insumo' => $insumo]);
    }

    public function deleteInsumo($id_insumo)
    {
        $insumo = Insumo::findOrFail($id_insumo);
        // Verificar si está en alguna receta antes de borrar
        if (Receta::where('id_insumo', $id_insumo)->exists()) {
            return response()->json(['error' => 'No se puede eliminar porque forma parte de una receta.'], 422);
        }
        $insumo->delete();
        return response()->json(['message' => 'Insumo eliminado']);
    }

    // === GESTIÓN DE PRODUCTOS (MENÚ) ===
    public function getProductos()
    {
        return response()->json(Producto::with('categoria')->orderBy('nombre_prod')->get());
    }

    public function storeProducto(Request $request)
    {
        $validated = $request->validate([
            'nombre_prod'  => 'required|string|max:100',
            'precio'       => 'required|numeric|min:0',
            'id_categoria' => 'required|exists:Categoria,id_categoria',
            'descripcion'  => 'nullable|string|max:500',
            'imagen'       => 'nullable|image|max:2048', // 2MB max
        ]);

        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('productos', 'public');
            $validated['url_imagen'] = '/storage/' . $path;
        }

        $producto = Producto::create($validated);
        return response()->json(['message' => 'Producto registrado', 'producto' => $producto->load('categoria')], 201);
    }

    public function updateProducto(Request $request, $id_producto)
    {
        $validated = $request->validate([
            'nombre_prod'  => 'required|string|max:100',
            'precio'       => 'required|numeric|min:0',
            'id_categoria' => 'required|exists:Categoria,id_categoria',
            'descripcion'  => 'nullable|string|max:500',
            'imagen'       => 'nullable|image|max:2048',
            'estado'       => 'required|in:Activo,Inactivo'
        ]);

        $producto = Producto::findOrFail($id_producto);

        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('productos', 'public');
            $validated['url_imagen'] = '/storage/' . $path;
        }

        $producto->update($validated);
        return response()->json(['message' => 'Producto actualizado', 'producto' => $producto->load('categoria')]);
    }

    public function deleteProducto($id_producto)
    {
        $producto = Producto::findOrFail($id_producto);
        // Soft delete o cambio de estado para no romper historial de pedidos
        $producto->estado = 'Inactivo';
        $producto->save();
        return response()->json(['message' => 'Producto marcado como Inactivo']);
    }

    // === GESTIÓN DE RECETAS ===
    public function getReceta($id_producto)
    {
        $receta = Receta::with('insumo')
            ->where('id_producto', $id_producto)
            ->get();
        return response()->json($receta);
    }

    public function storeReceta(Request $request)
    {
        $validated = $request->validate([
            'id_producto'        => 'required|exists:Producto,id_producto',
            'id_insumo'          => 'required|exists:Insumo,id_insumo',
            'cantidad_necesaria' => 'required|numeric|min:0.01',
        ]);

        $receta = Receta::create($validated);
        return response()->json(['message' => 'Ingrediente enlazado a la receta', 'receta' => $receta->load('insumo')], 201);
    }

    public function updateReceta(Request $request, $id_receta)
    {
        $validated = $request->validate([
            'cantidad_necesaria' => 'required|numeric|min:0.01',
        ]);

        $receta = Receta::findOrFail($id_receta);
        $receta->update($validated);
        return response()->json(['message' => 'Cantidad actualizada en la receta', 'receta' => $receta->load('insumo')]);
    }

    public function deleteReceta($id_receta)
    {
        $receta = Receta::findOrFail($id_receta);
        $receta->delete();
        return response()->json(['message' => 'Ingrediente eliminado de la receta']);
    }

    // === GESTIÓN DE USUARIOS ===
    public function getUsuarios()
    {
        return response()->json(\App\Models\Usuario::with('rol')->get());
    }

    public function storeUsuario(Request $request)
    {
        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:100',
            'email'           => 'required|email|unique:Usuario,email',
            'pin_acceso'      => 'required|string|size:4',
            'id_rol'          => 'required|exists:Rol,id_rol',
        ]);

        // Encriptar PIN (Expert Requisite)
        $validated['pin_acceso'] = bcrypt($validated['pin_acceso']);
        
        $user = \App\Models\Usuario::create($validated);
        return response()->json(['message' => 'Usuario creado', 'usuario' => $user], 201);
    }

    // === GESTIÓN DE MERMAS ===
    public function getMermas()
    {
        return response()->json(DB::select("
            SELECT m.*, i.nombre_insumo, u.nombre_completo as usuario
            FROM Merma m
            JOIN Insumo i ON m.id_insumo = i.id_insumo
            JOIN Usuario u ON m.id_usuario = u.id_usuario
            ORDER BY m.fecha_hora DESC
        "));
    }

    public function storeMerma(Request $request)
    {
        $validated = $request->validate([
            'id_insumo'   => 'required|exists:Insumo,id_insumo',
            'cantidad'    => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:500',
        ]);

        $validated['id_usuario'] = auth()->id();
        
        DB::table('Merma')->insert($validated);
        return response()->json(['message' => 'Merma registrada y stock descontado']);
    }

    // === REPORTES FINANCIEROS (PROFIT) ===
    public function getProfitReport()
    {
        $ventas = DB::select("
            SELECT SUM(dp.cantidad * dp.precio_unitario) as ingresos
            FROM Detalle_Pedido dp
            JOIN Pedido p ON dp.id_pedido = p.id_pedido
            WHERE p.estado_pedido = 'Pagado'
        ")[0]->ingresos ?? 0;

        $costos = DB::select("
            SELECT SUM(dp.cantidad * r.cantidad_necesaria * i.costo_unitario) as costo_total
            FROM Detalle_Pedido dp
            JOIN Pedido p ON dp.id_pedido = p.id_pedido
            JOIN Receta r ON dp.id_producto = r.id_producto
            JOIN Insumo i ON r.id_insumo = i.id_insumo
            WHERE p.estado_pedido = 'Pagado'
        ")[0]->costo_total ?? 0;

        return response()->json([
            'ingresos' => (float)$ventas,
            'costos'   => (float)$costos,
            'utilidad' => (float)$ventas - (float)$costos
        ]);
    }
}
