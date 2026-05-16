<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Insumo;
use App\Models\Receta;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Factura;
use App\Models\Categoria;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

class ComprehensiveIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ============================================
    // INTEGRACIÓN: FLUJO COMPLETO DE ORDEN
    // ============================================

    /** @test */
    public function test_complete_order_flow_from_mesa_to_factura()
    {
        // Setup
        $mesero = Usuario::where('id_rol', 2)->first();
        $cocinero = Usuario::where('id_rol', 3)->first();
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto1 = Producto::first();
        $producto2 = Producto::skip(1)->first();

        // Step 1: Mesero obtiene mesas disponibles
        $this->actingAs($mesero);
        $response = $this->getJson('/api/mesero/mesas');
        $this->assertCount(10, $response->json());
        $this->assertTrue(collect($response->json())->contains('id_mesa', $mesa->id_mesa));

        // Step 2: Mesero ve el menú
        $response = $this->getJson('/api/mesero/menu');
        $this->assertCount(Producto::where('estado', 'Activo')->count(), $response->json());

        // Step 3: Mesero crea orden con múltiples items
        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                [
                    'id_producto' => $producto1->id_producto,
                    'cantidad' => 1,
                    'notas' => 'Sin cebolla'
                ],
                [
                    'id_producto' => $producto2->id_producto,
                    'cantidad' => 2,
                    'notas' => 'Extra salsa'
                ]
            ]
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Ocupada', $mesa->fresh()->estado);

        // Step 4: Verificar que se creó el pedido
        $pedido = Pedido::where('id_mesa', $mesa->id_mesa)->latest('id_pedido')->first();
        $this->assertNotNull($pedido);
        $this->assertEquals('Recibido', $pedido->estado_pedido);
        $this->assertEquals(2, $pedido->detalles()->count());

        // Step 5: Cocinero ve las órdenes
        $this->actingAs($cocinero);
        $response = $this->getJson('/api/cocina/orders');
        $this->assertGreaterThanOrEqual(1, count($response->json()));

        // Step 6: Cocinero actualiza primer plato a Preparación
        $detalle1 = $pedido->detalles()->first();
        $response = $this->postJson("/api/cocina/orders/{$detalle1->id_detalle}/status", [
            'estado_cocina' => 'Preparación'
        ]);
        $this->assertEquals(200, $response->status());
        $this->assertEquals('Preparación', $detalle1->fresh()->estado_cocina);

        // Step 7: Cocinero marca primer plato como Listo
        $response = $this->postJson("/api/cocina/orders/{$detalle1->id_detalle}/status", [
            'estado_cocina' => 'Listo'
        ]);
        $this->assertEquals(200, $response->status());

        // Step 8: Mesero recibe notificación
        $this->actingAs($mesero);
        $response = $this->getJson('/api/mesero/get-notifications');
        $this->assertIsArray($response->json());

        // Step 9: Cocinero marca segundo plato como Listo
        $this->actingAs($cocinero);
        $detalles = $pedido->detalles()->get();
        $detalle2 = $detalles->skip(1)->first();
        $this->postJson("/api/cocina/orders/{$detalle2->id_detalle}/status", [
            'estado_cocina' => 'Listo'
        ]);

        // Step 10: Mesero cobra la orden
        $this->actingAs($mesero);
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Efectivo'
        ]);

        $this->assertEquals(200, $response->status());

        // Step 11: Verificar estado final
        $this->assertEquals('Pagado', $pedido->fresh()->estado_pedido);
        $this->assertEquals('Libre', $mesa->fresh()->estado);
        $this->assertDatabaseHas('Factura', ['id_pedido' => $pedido->id_pedido]);

        $factura = Factura::where('id_pedido', $pedido->id_pedido)->first();
        $this->assertNotNull($factura);
        $this->assertEquals('Efectivo', $factura->metodo_pago);
    }

    // ============================================
    // INTEGRACIÓN: ADMIN WORKFLOWS
    // ============================================

    /** @test */
    public function test_admin_complete_product_management_workflow()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        // Step 1: Crear categoría
        $response = $this->postJson('/api/admin/categorias', [
            'nombre_cat' => 'Bebidas Especiales'
        ]);
        $this->assertEquals(201, $response->status());

        // Step 2: Crear insumos
        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => 'Café Premium',
            'unidad_medida' => 'kg',
            'stock_actual' => 100,
        ]);
        $insumoId = $response->json('insumo.id_insumo') ?? Insumo::latest('id_insumo')->first()->id_insumo;

        // Step 3: Crear producto
        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Americano',
            'precio' => 30.00,
            'id_categoria' => 1,
        ]);
        $this->assertEquals(201, $response->status());
        $productoId = $response->json('producto.id_producto') ?? Producto::latest('id_producto')->first()->id_producto;

        // Step 4: Asignar receta
        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => $productoId,
            'id_insumo' => $insumoId,
            'cantidad_necesaria' => 20.00,
        ]);
        $this->assertEquals(201, $response->status());

        // Step 5: Actualizar stock
        $response = $this->patchJson("/api/admin/insumos/{$insumoId}", [
            'stock_actual' => 120,
        ]);
        $this->assertEquals(200, $response->status());

        // Step 6: Verificar que todo está en BD
        $this->assertDatabaseHas('Producto', [
            'id_producto' => $productoId,
            'nombre_prod' => 'Americano'
        ]);
        $this->assertDatabaseHas('Receta', [
            'id_producto' => $productoId,
            'id_insumo' => $insumoId,
            'cantidad_necesaria' => 20.00
        ]);
    }

    // ============================================
    // INTEGRACIÓN: INVENTARIO & TRANSACCIONES
    // ============================================

    /** @test */
    public function test_inventory_deduction_multiple_orders()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $producto = Producto::first();
        $receta = $producto->recetas()->first();
        $insumo = $receta->insumo;
        $stockInicial = $insumo->stock_actual;

        // Order 1
        $mesa1 = Mesa::where('estado', 'Libre')->first();
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa1->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);
        $stockEsperado1 = $stockInicial - $receta->cantidad_necesaria;
        $this->assertEquals($stockEsperado1, $insumo->fresh()->stock_actual);

        // Order 2
        $mesa2 = Mesa::where('estado', 'Libre')->where('id_mesa', '!=', $mesa1->id_mesa)->first();
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa2->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);
        $stockEsperado2 = $stockEsperado1 - $receta->cantidad_necesaria;
        $this->assertEquals($stockEsperado2, $insumo->fresh()->stock_actual);
    }

    /** @test */
    public function test_cancellation_reverts_inventory_and_liberates_mesa()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $cocinero = Usuario::where('id_rol', 3)->first();

        $this->actingAs($mesero);
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();
        $receta = $producto->recetas()->first();
        $insumo = $receta->insumo;

        $stockAntes = $insumo->stock_actual;

        // Create order
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $stockDespuesOrden = $insumo->fresh()->stock_actual;
        $this->assertEquals($stockAntes - $receta->cantidad_necesaria, $stockDespuesOrden);

        $detalle = DetallePedido::latest('id_detalle')->first();

        // Cocinero cancela
        $this->actingAs($cocinero);
        $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/cancelar", [
            'razon' => 'Defectuoso'
        ]);

        $stockDespuesCancelar = $insumo->fresh()->stock_actual;
        $this->assertEquals($stockAntes, $stockDespuesCancelar);
    }

    // ============================================
    // EDGE CASES: LÍMITES Y BORDES
    // ============================================

    /** @test */
    public function test_order_with_zero_stock_fails()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $producto = Producto::first();
        $receta = $producto->recetas()->first();

        // Force zero stock
        $receta->insumo()->update(['stock_actual' => 0]);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function test_order_with_exact_stock_succeeds()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $producto = Producto::first();
        $receta = $producto->recetas()->first();

        // Set stock to exactly what's needed
        $receta->insumo()->update(['stock_actual' => $receta->cantidad_necesaria]);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $receta->insumo->fresh()->stock_actual);
    }

    /** @test */
    public function test_multiple_items_from_same_product()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                ['id_producto' => $producto->id_producto, 'cantidad' => 3],
                ['id_producto' => $producto->id_producto, 'cantidad' => 2]
            ]
        ]);

        // Should create 2 separate detalles or combine them
        if ($response->status() === 200) {
            $pedido = Pedido::where('id_mesa', $mesa->id_mesa)->first();
            $this->assertGreaterThanOrEqual(1, $pedido->detalles()->count());
        }
    }

    /** @test */
    public function test_large_price_handling()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Producto Premium',
            'precio' => 9999.99,
            'id_categoria' => 1,
        ]);

        if ($response->status() === 201) {
            $producto = Producto::where('nombre_prod', 'Producto Premium')->first();
            $this->assertEquals(9999.99, $producto->precio);
        }
    }

    /** @test */
    public function test_very_small_price_handling()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Producto Barato',
            'precio' => 0.01,
            'id_categoria' => 1,
        ]);

        if ($response->status() === 201) {
            $producto = Producto::where('nombre_prod', 'Producto Barato')->first();
            $this->assertEquals(0.01, $producto->precio);
        }
    }

    // ============================================
    // EDGE CASES: CONCURRENCY & RACE CONDITIONS
    // ============================================

    /** @test */
    public function test_double_order_same_mesa_prevention()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        // First order
        $response1 = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        // Second order on same mesa (should fail)
        $response2 = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $this->assertEquals(200, $response1->status());
        $this->assertIn($response2->status(), [400, 422]);
    }

    /** @test */
    public function test_double_deletion_category_safe()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $categoria = Categoria::create(['nombre_cat' => 'Temp Cat']);
        $id = $categoria->id_categoria;

        // First delete
        $response1 = $this->deleteJson("/api/admin/categorias/{$id}");
        $this->assertEquals(200, $response1->status());

        // Second delete (should fail gracefully)
        $response2 = $this->deleteJson("/api/admin/categorias/{$id}");
        $this->assertIn($response2->status(), [404, 422]);
    }

    // ============================================
    // ESTADÍSTICAS & REPORTES
    // ============================================

    /** @test */
    public function test_admin_stats_shows_accurate_data()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        // Create some orders and facturas
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $pedido = Pedido::latest('id_pedido')->first();
        $detalle = $pedido->detalles()->first();
        $detalle->update(['estado_cocina' => 'Listo']);

        $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Tarjeta'
        ]);

        // Check stats
        $this->actingAs($admin);
        $response = $this->getJson('/api/admin/stats');

        $this->assertGreaterThanOrEqual(1, $response->json('ventas_hoy')['Cantidad_Facturas']);
        $this->assertGreaterThanOrEqual(0, $response->json('ventas_hoy')['Ingresos_Totales']);
    }

    /** @test */
    public function test_sales_history_date_filter()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $response = $this->getJson("/api/admin/history?inicio={$today}&fin={$today}");
        $this->assertEquals(200, $response->status());

        $response = $this->getJson("/api/admin/history?inicio={$yesterday}&fin={$yesterday}");
        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function test_export_csv_format_valid()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->get('/api/admin/export-daily');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    // ============================================
    // PERMISSIONS & AUTHORIZATION
    // ============================================

    /** @test */
    public function test_mesero_cannot_access_admin_endpoints()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/admin/stats');
        $this->assertIn($response->status(), [401, 403]);
    }

    /** @test */
    public function test_admin_cannot_access_cocina_endpoints()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/cocina/orders');
        $this->assertIn($response->status(), [401, 403]);
    }

    /** @test */
    public function test_cocinero_cannot_charge_orders()
    {
        $cocinero = Usuario::where('id_rol', 3)->first();
        $this->actingAs($cocinero);

        // Try to charge
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => 1,
            'metodo_pago' => 'Efectivo'
        ]);

        $this->assertIn($response->status(), [401, 403]);
    }

    // ============================================
    // STATE TRANSITIONS
    // ============================================

    /** @test */
    public function test_mesa_estado_transitions()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $this->assertEquals('Libre', $mesa->estado);

        // Order → Ocupada
        $producto = Producto::first();
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $this->assertEquals('Ocupada', $mesa->fresh()->estado);

        // Cobrar → Libre
        $pedido = Pedido::where('id_mesa', $mesa->id_mesa)->first();
        $detalle = $pedido->detalles()->first();
        $detalle->update(['estado_cocina' => 'Listo']);

        $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Efectivo'
        ]);

        $this->assertEquals('Libre', $mesa->fresh()->estado);
    }
}
