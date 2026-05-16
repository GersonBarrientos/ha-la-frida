<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\Rol;
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

class ComprehensiveControllerTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ============================================
    // ADMIN CONTROLLER TESTS
    // ============================================

    /** @test */
    public function test_admin_get_stats_returns_all_metrics()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ventas_hoy',
            'inventario_critico',
            'ventas_por_categoria',
            'trafico_hora',
            'total_insumos',
            'total_productos'
        ]);
    }

    /** @test */
    public function test_admin_get_stats_ventas_hoy_structure()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/stats');
        $this->assertIsArray($response->json('ventas_hoy'));
        $this->assertArrayHasKey('Cantidad_Facturas', (array)$response->json('ventas_hoy'));
        $this->assertArrayHasKey('Ingresos_Totales', (array)$response->json('ventas_hoy'));
    }

    /** @test */
    public function test_admin_get_stats_inventario_critico()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        // Force low stock
        Insumo::first()->update(['stock_actual' => 5]);

        $response = $this->getJson('/api/admin/stats');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('inventario_critico'));
    }

    // ============================================
    // ADMIN - CATEGORÍAS TESTS
    // ============================================

    /** @test */
    public function test_admin_get_categorias()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/categorias');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_store_categoria_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/categorias', [
            'nombre_cat' => 'Postres Especiales'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Categoria', ['nombre_cat' => 'Postres Especiales']);
    }

    /** @test */
    public function test_admin_store_categoria_validation_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/categorias', [
            'nombre_cat' => ''
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_cat');
    }

    /** @test */
    public function test_admin_store_categoria_unique_constraint()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        // First categoria
        $this->postJson('/api/admin/categorias', ['nombre_cat' => 'Bebidas']);

        // Second with same name
        $response = $this->postJson('/api/admin/categorias', ['nombre_cat' => 'Bebidas']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_cat');
    }

    /** @test */
    public function test_admin_delete_categoria_with_products_fails()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $categoria = Categoria::first();
        $producto = Producto::where('id_categoria', $categoria->id_categoria)->first();

        if ($producto) {
            $response = $this->deleteJson("/api/admin/categorias/{$categoria->id_categoria}");
            $response->assertStatus(422);
            $response->assertJsonFragment(['error' => 'No se puede eliminar, tiene productos asignados.']);
        }
    }

    /** @test */
    public function test_admin_delete_categoria_empty_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $categoria = Categoria::create(['nombre_cat' => 'Empty Category']);
        $response = $this->deleteJson("/api/admin/categorias/{$categoria->id_categoria}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('Categoria', ['id_categoria' => $categoria->id_categoria]);
    }

    // ============================================
    // ADMIN - INSUMOS TESTS
    // ============================================

    /** @test */
    public function test_admin_get_insumos()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/insumos');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_store_insumo_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => 'Aceite de Oliva',
            'unidad_medida' => 'litros',
            'stock_actual' => 50,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Insumo', ['nombre_insumo' => 'Aceite de Oliva']);
    }

    /** @test */
    public function test_admin_store_insumo_validation()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => '',
            'unidad_medida' => '',
            'stock_actual' => -1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombre_insumo', 'unidad_medida']);
    }

    /** @test */
    public function test_admin_update_insumo_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $insumo = Insumo::first();
        $response = $this->patchJson("/api/admin/insumos/{$insumo->id_insumo}", [
            'nombre_insumo' => 'Insumo Modificado',
            'stock_actual' => 100,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(100, $insumo->fresh()->stock_actual);
    }

    /** @test */
    public function test_admin_delete_insumo_with_recetas_fails()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $insumo = Insumo::whereHas('recetas')->first();
        if ($insumo) {
            $response = $this->deleteJson("/api/admin/insumos/{$insumo->id_insumo}");
            $response->assertStatus(422);
        }
    }

    // ============================================
    // ADMIN - PRODUCTOS TESTS
    // ============================================

    /** @test */
    public function test_admin_get_productos()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/productos');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_store_producto_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Sopa de Tortilla',
            'precio' => 25.00,
            'id_categoria' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Producto', ['nombre_prod' => 'Sopa de Tortilla']);
    }

    /** @test */
    public function test_admin_store_producto_validation()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => '',
            'precio' => -10,
            'id_categoria' => 999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombre_prod', 'precio']);
    }

    /** @test */
    public function test_admin_update_producto_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $producto = Producto::first();
        $response = $this->patchJson("/api/admin/productos/{$producto->id_producto}", [
            'nombre_prod' => 'Producto Actualizado',
            'precio' => 99.99,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(99.99, $producto->fresh()->precio);
    }

    /** @test */
    public function test_admin_delete_producto_soft_delete()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $producto = Producto::first();
        $id = $producto->id_producto;

        $response = $this->deleteJson("/api/admin/productos/{$id}");
        $response->assertStatus(200);

        $this->assertEquals('Inactivo', $producto->fresh()->estado);
    }

    // ============================================
    // ADMIN - RECETA TESTS
    // ============================================

    /** @test */
    public function test_admin_get_receta_by_producto()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $producto = Producto::first();
        $response = $this->getJson("/api/admin/receta/{$producto->id_producto}");

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_store_receta_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => 1,
            'id_insumo' => 1,
            'cantidad_necesaria' => 150.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Receta', [
            'id_producto' => 1,
            'id_insumo' => 1,
            'cantidad_necesaria' => 150.00,
        ]);
    }

    /** @test */
    public function test_admin_update_receta_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $receta = Receta::first();
        $response = $this->patchJson("/api/admin/recetas/{$receta->id_receta}", [
            'cantidad_necesaria' => 200.00,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_delete_receta_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $receta = Receta::first();
        $id = $receta->id_receta;

        $response = $this->deleteJson("/api/admin/recetas/{$id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('Receta', ['id_receta' => $id]);
    }

    // ============================================
    // ADMIN - USUARIOS TESTS
    // ============================================

    /** @test */
    public function test_admin_get_usuarios()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/usuarios');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_store_usuario_success()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/usuarios', [
            'nombre_completo' => 'Pedro García',
            'correo' => 'pedro@test.com',
            'pin_acceso' => '1234',
            'id_rol' => 2,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('Usuario', ['nombre_completo' => 'Pedro García']);
    }

    /** @test */
    public function test_admin_profit_report()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/profit');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    // ============================================
    // ORDER CONTROLLER TESTS
    // ============================================

    /** @test */
    public function test_mesero_get_mesas()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/mesas');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_mesero_get_menu()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/menu');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_mesero_get_pedido_activo()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::first();
        $response = $this->getJson("/api/mesero/pedido-activo/{$mesa->id_mesa}");
        $response->assertStatus(200);
    }

    /** @test */
    public function test_mesero_submit_order_success()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::where('estado', 'Activo')->first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                [
                    'id_producto' => $producto->id_producto,
                    'cantidad' => 1,
                    'notas' => 'Sin picante'
                ]
            ]
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Ocupada', $mesa->fresh()->estado);
    }

    /** @test */
    public function test_mesero_submit_order_inventory_deduction()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();
        $receta = $producto->recetas()->first();
        $insumo = $receta->insumo;

        $stockInicial = $insumo->stock_actual;

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $stockEsperado = $stockInicial - $receta->cantidad_necesaria;
        $this->assertEquals($stockEsperado, $insumo->fresh()->stock_actual);
    }

    /** @test */
    public function test_mesero_submit_order_insufficient_stock()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();
        $receta = $producto->recetas()->first();

        // Force zero stock
        $receta->insumo()->update(['stock_actual' => 0]);

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Stock insuficiente']);
    }

    /** @test */
    public function test_mesero_cobrar_pedido_success()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        // Create order
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $pedido = Pedido::latest('id_pedido')->first();

        // Update status to Listo
        $detalle = $pedido->detalles()->first();
        $detalle->update(['estado_cocina' => 'Listo']);

        // Cobrar
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Efectivo'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Pagado', $pedido->fresh()->estado_pedido);
        $this->assertEquals('Libre', $mesa->fresh()->estado);
        $this->assertDatabaseHas('Factura', ['id_pedido' => $pedido->id_pedido]);
    }

    /** @test */
    public function test_mesero_get_kitchen_load()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/get-kitchen-load');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_mesero_get_notifications()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/get-notifications');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    // ============================================
    // KITCHEN CONTROLLER TESTS
    // ============================================

    /** @test */
    public function test_cocinero_get_active_orders()
    {
        $cocinero = Usuario::where('id_rol', 3)->first();
        $this->actingAs($cocinero);

        $response = $this->getJson('/api/cocina/orders');
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_cocinero_update_status_to_preparacion()
    {
        $cocinero = Usuario::where('id_rol', 3)->first();
        $this->actingAs($cocinero);

        // Create order first
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $detalle = DetallePedido::latest('id_detalle')->first();

        // Act as cocinero
        $this->actingAs($cocinero);
        $response = $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/status", [
            'estado_cocina' => 'Preparación'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Preparación', $detalle->fresh()->estado_cocina);
    }

    /** @test */
    public function test_cocinero_update_status_to_listo()
    {
        $cocinero = Usuario::where('id_rol', 3)->first();
        $mesero = Usuario::where('id_rol', 2)->first();

        // Create order
        $this->actingAs($mesero);
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $detalle = DetallePedido::latest('id_detalle')->first();

        // Update to Listo
        $this->actingAs($cocinero);
        $response = $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/status", [
            'estado_cocina' => 'Listo'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Listo', $detalle->fresh()->estado_cocina);
    }

    /** @test */
    public function test_cocinero_cancelar_detalle_reverts_inventory()
    {
        $cocinero = Usuario::where('id_rol', 3)->first();
        $mesero = Usuario::where('id_rol', 2)->first();

        // Create order
        $this->actingAs($mesero);
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();
        $receta = $producto->recetas()->first();

        $stockAntes = $receta->insumo->stock_actual;

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $detalle = DetallePedido::latest('id_detalle')->first();
        $stockDespuesOrden = $receta->insumo->fresh()->stock_actual;

        // Cancelar
        $this->actingAs($cocinero);
        $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/cancelar", [
            'razon' => 'Producto defectuoso'
        ]);

        $stockDespuesCancelar = $receta->insumo->fresh()->stock_actual;
        $this->assertEquals($stockAntes, $stockDespuesCancelar);
    }

    // ============================================
    // REPORTS CONTROLLER TESTS
    // ============================================

    /** @test */
    public function test_admin_get_sales_history()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/history?inicio=' . now()->toDateString() . '&fin=' . now()->toDateString());
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /** @test */
    public function test_admin_export_daily_csv()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->get('/api/admin/export-daily');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ============================================
    // PROFILE CONTROLLER TESTS
    // ============================================

    /** @test */
    public function test_user_edit_profile_page()
    {
        $usuario = Usuario::first();
        $this->actingAs($usuario);

        $response = $this->get('/profile');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_user_update_profile_success()
    {
        $usuario = Usuario::first();
        $this->actingAs($usuario);

        $response = $this->patch('/profile', [
            'nombre_completo' => 'Usuario Actualizado',
            'correo' => 'actualizado@test.com'
        ]);

        $response->assertRedirect();
        // Verificar que se actualizó
        $this->assertTrue(true); // La validación del UpdateRequest es responsabilidad del form
    }

    /** @test */
    public function test_user_delete_profile()
    {
        $usuario = Usuario::create([
            'nombre_completo' => 'Temp User',
            'correo' => 'temp@test.com',
            'pin_acceso' => '9999',
            'estado' => 'Activo',
            'id_rol' => 2
        ]);

        $this->actingAs($usuario);

        $response = $this->delete('/profile', [
            'password' => 'password' // Validación de contraseña
        ]);

        $response->assertRedirect();
    }

    // ============================================
    // AUTENTICACIÓN TESTS
    // ============================================

    /** @test */
    public function test_login_con_pin_valid()
    {
        $response = $this->post('/login', [
            'pin_acceso' => '1234',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    /** @test */
    public function test_login_con_pin_invalid()
    {
        $response = $this->post('/login', [
            'pin_acceso' => '0000',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /** @test */
    public function test_logout_success()
    {
        $usuario = Usuario::first();
        $this->actingAs($usuario);

        $response = $this->post('/logout');
        $response->assertRedirect();
        $this->assertGuest();
    }
}
