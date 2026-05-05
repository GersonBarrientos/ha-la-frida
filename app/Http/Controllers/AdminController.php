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
        // Ventas del Día (PostgreSQL compatible)
        $ventasHoy = DB::table('Factura')
            ->whereDate('fecha_pago', now()->toDateString())
            ->selectRaw('COUNT(id_factura) as "Cantidad_Facturas", COALESCE(SUM(total), 0) as "Ingresos_Totales"')
            ->first();

        // Inventario Crítico
        $inventarioCritico = Insumo::where('stock_actual', '<=', 10)
            ->where('estado', 'Activo')
            ->get();

        // BI: Ventas por Categoría (hoy)
        $ventasPorCat = DB::table('Detalle_Pedido as dp')
            ->join('Producto as p', 'dp.id_producto', '=', 'p.id_producto')
            ->join('Categoria as c', 'p.id_categoria', '=', 'c.id_categoria')
            ->join('Pedido as ped', 'dp.id_pedido', '=', 'ped.id_pedido')
            ->whereDate('ped.fecha_hora', now()->toDateString())
            ->select('c.nombre_cat')
            ->selectRaw('SUM(dp.cantidad * dp.precio_unitario) as total')
            ->groupBy('c.nombre_cat')
            ->get();

        // BI: Tráfico por Hora (hoy)
        $traficoHora = DB::table('Pedido')
            ->whereDate('fecha_hora', now()->toDateString())
            ->selectRaw('EXTRACT(HOUR FROM fecha_hora)::int as hora, COUNT(*) as pedidos')
            ->groupByRaw('EXTRACT(HOUR FROM fecha_hora)')
            ->orderBy('hora')
            ->get();

        $totalInsumos = Insumo::count();
        $totalProductos = Producto::where('estado', 'Activo')->count();

        return response()->json([
            'ventas_hoy' => $ventasHoy ?? (object)['Cantidad_Facturas' => 0, 'Ingresos_Totales' => 0],
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
        return response()->json(Categoria::orderBy('nombre_cat')->get());
    }

    public function storeCategoria(Request $request)
    {
        $validated = $request->validate([
            'nombre_cat' => 'required|string|max:100|unique:Categoria,nombre_cat',
        ]);

        $categoria = Categoria::create($validated);
        return response()->json(['message' => 'Categoría creada', 'categoria' => $categoria], 201);
    }

    public function deleteCategoria($id_categoria)
    {
        $cat = Categoria::findOrFail($id_categoria);
        if (Producto::where('id_categoria', $id_categoria)->exists()) {
            return response()->json(['error' => 'No se puede eliminar, tiene productos asignados.'], 422);
        }
        $cat->delete();
        return response()->json(['message' => 'Categoría eliminada']);
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
            'correo'          => 'required|email|unique:Usuario,correo',
            'pin_acceso'      => 'required|string|size:4',
            'id_rol'          => 'required|exists:Rol,id_rol',
        ]);

        $user = \App\Models\Usuario::create($validated);
        return response()->json(['message' => 'Usuario creado', 'usuario' => $user], 201);
    }

    // === REPORTES FINANCIEROS (PROFIT) ===
    public function getProfitReport()
    {
        $ventas = DB::table('Detalle_Pedido as dp')
            ->join('Pedido as p', 'dp.id_pedido', '=', 'p.id_pedido')
            ->where('p.estado_pedido', 'Pagado')
            ->selectRaw('COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) as ingresos')
            ->value('ingresos') ?? 0;

        return response()->json([
            'ingresos' => (float)$ventas,
            'costos'   => 0,
            'utilidad' => (float)$ventas
        ]);
    }
}
