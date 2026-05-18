<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/debug-db', function () {
    $debug = [
        'connection' => config('database.default'),
        'host' => config('database.connections.' . config('database.default') . '.host', 'N/A'),
        'database' => config('database.connections.' . config('database.default') . '.database', 'N/A'),
        'user_count' => \Illuminate\Support\Facades\DB::table('Usuario')->count(),
        'users' => \Illuminate\Support\Facades\DB::table('Usuario')->select('nombre_completo', 'correo')->get(),
    ];

    try {
        $costos = \Illuminate\Support\Facades\DB::table('Detalle_Pedido as dp')
            ->join('Pedido as p', 'dp.id_pedido', '=', 'p.id_pedido')
            ->join('Receta as r', 'dp.id_producto', '=', 'r.id_producto')
            ->join('Insumo as i', 'r.id_insumo', '=', 'i.id_insumo')
            ->where('p.estado_pedido', 'Pagado')
            ->selectRaw('COALESCE(SUM(dp.cantidad * r.cantidad_necesaria * i.costo_unitario), 0) as costos')
            ->value('costos');
        $debug['test_profit_query'] = $costos;
    } catch (\Exception $e) {
        $debug['error_profit_query'] = $e->getMessage();
    }

    try {
        $insumos = \Illuminate\Support\Facades\DB::table('Insumo')->first();
        $debug['test_insumos_table'] = $insumos;
    } catch (\Exception $e) {
        $debug['error_insumos_table'] = $e->getMessage();
    }

    try {
        $usuarios = \Illuminate\Support\Facades\DB::table('Usuario')->first();
        $debug['test_usuarios_table'] = $usuarios;
    } catch (\Exception $e) {
        $debug['error_usuarios_table'] = $e->getMessage();
    }

    return response()->json($debug);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $user->load('rol');
        $rolDesc = strtolower($user->rol?->descripcion ?? '');
        $rolId = (int)$user->id_rol;

        if (str_contains($rolDesc, 'cocina') || str_contains($rolDesc, 'cocinero') || $rolId === 3) return redirect('/cocina');
        if (str_contains($rolDesc, 'mesero') || $rolId === 2) return redirect('/mesero');
        if (str_contains($rolDesc, 'admin') || $rolId === 1) return redirect('/admin');

        return redirect('/');
    })->name('dashboard');

    Route::get('/admin', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::get('/mesero', function () {
        return Inertia::render('Mesero/Dashboard');
    })->name('mesero.dashboard');

    Route::get('/cocina', function () {
        return Inertia::render('Cocina/Dashboard');
    })->name('cocina.dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API Rutas Admin (Solo Rol 1: Admin)
    Route::middleware('check-role:1')->group(function () {
        Route::get('/api/admin/stats', [\App\Http\Controllers\AdminController::class, 'getStats']);

        Route::get('/api/admin/categorias', [\App\Http\Controllers\AdminController::class, 'getCategorias']);
        Route::post('/api/admin/categorias', [\App\Http\Controllers\AdminController::class, 'storeCategoria']);
        Route::put('/api/admin/categorias/{id_categoria}', [\App\Http\Controllers\AdminController::class, 'updateCategoria']);
        Route::delete('/api/admin/categorias/{id_categoria}', [\App\Http\Controllers\AdminController::class, 'deleteCategoria']);

        Route::get('/api/admin/insumos', [\App\Http\Controllers\AdminController::class, 'getInsumos']);
        Route::post('/api/admin/insumos', [\App\Http\Controllers\AdminController::class, 'storeInsumo']);
        Route::put('/api/admin/insumos/{id_insumo}', [\App\Http\Controllers\AdminController::class, 'updateInsumo']);
        Route::delete('/api/admin/insumos/{id_insumo}', [\App\Http\Controllers\AdminController::class, 'deleteInsumo']);

        Route::get('/api/admin/productos', [\App\Http\Controllers\AdminController::class, 'getProductos']);
        Route::post('/api/admin/productos', [\App\Http\Controllers\AdminController::class, 'storeProducto']);
        Route::put('/api/admin/productos/{id_producto}', [\App\Http\Controllers\AdminController::class, 'updateProducto']);
        Route::delete('/api/admin/productos/{id_producto}', [\App\Http\Controllers\AdminController::class, 'deleteProducto']);

        Route::get('/api/admin/receta/{id_producto}', [\App\Http\Controllers\AdminController::class, 'getReceta']);
        Route::post('/api/admin/recetas', [\App\Http\Controllers\AdminController::class, 'storeReceta']);
        Route::put('/api/admin/recetas/{id_receta}', [\App\Http\Controllers\AdminController::class, 'updateReceta']);
        Route::delete('/api/admin/receta/{id_receta}', [\App\Http\Controllers\AdminController::class, 'deleteReceta']);

        Route::get('/api/admin/usuarios', [\App\Http\Controllers\AdminController::class, 'getUsuarios']);
        Route::post('/api/admin/usuarios', [\App\Http\Controllers\AdminController::class, 'storeUsuario']);
        Route::put('/api/admin/usuarios/{id_usuario}', [\App\Http\Controllers\AdminController::class, 'updateUsuario']);
        Route::delete('/api/admin/usuarios/{id_usuario}', [\App\Http\Controllers\AdminController::class, 'deleteUsuario']);
        Route::get('/api/admin/history', [\App\Http\Controllers\ReportsController::class, 'getSalesHistory']);
        Route::get('/api/admin/export-daily', [\App\Http\Controllers\ReportsController::class, 'exportDailyCSV']);
        Route::get('/api/admin/profit', [\App\Http\Controllers\AdminController::class, 'getProfitReport']);
        Route::get('/api/admin/factura/{id_factura}', [\App\Http\Controllers\ReportsController::class, 'downloadFactura']);
    });

    // API Rutas Mesero (Solo Rol 2: Mesero)
    Route::middleware('check-role:2')->group(function () {
        Route::get('/api/mesero/mesas', [\App\Http\Controllers\OrderController::class, 'getMesas']);
        Route::get('/api/mesero/menu', [\App\Http\Controllers\OrderController::class, 'getMenu']);
        Route::post('/api/mesero/order', [\App\Http\Controllers\OrderController::class, 'submitOrder']);
        Route::post('/api/mesero/cobrar', [\App\Http\Controllers\OrderController::class, 'cobrarPedido']);
        Route::get('/api/mesero/factura/{id_factura}', [\App\Http\Controllers\ReportsController::class, 'downloadFactura']);
        Route::get('/api/mesero/get-kitchen-load', [\App\Http\Controllers\OrderController::class, 'getKitchenLoad']);
        Route::get('/api/mesero/get-notifications', [\App\Http\Controllers\OrderController::class, 'getNotifications']);
        Route::get('/api/mesero/pedido-activo/{id_mesa}', [\App\Http\Controllers\OrderController::class, 'getPedidoActivo']);
    });

    // API Rutas Cocina (Solo Rol 3: Cocinero)
    Route::middleware('check-role:3')->group(function () {
        Route::get('/api/cocina/orders', [\App\Http\Controllers\KitchenController::class, 'getActiveOrders']);
        Route::post('/api/cocina/orders/{id_detalle}/status', [\App\Http\Controllers\KitchenController::class, 'updateStatus']);
        Route::post('/api/cocina/orders/{id_detalle}/cancelar', [\App\Http\Controllers\KitchenController::class, 'cancelarDetalle']);
    });
});

require __DIR__.'/auth.php';
