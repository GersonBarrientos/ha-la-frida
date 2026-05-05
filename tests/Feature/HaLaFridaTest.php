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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

class HaLaFridaTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @test */
    public function test_login_with_pin_only()
    {
        $response = $this->post('/login', [
            'pin_acceso' => '5678',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    /** @test */
    public function test_admin_api_stats()
    {
        $admin = Usuario::where('pin_acceso', '1234')->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure(['ventas_hoy', 'total_insumos', 'total_productos']);
    }

    /** @test */
    public function test_admin_categorias_crud()
    {
        $admin = Usuario::where('pin_acceso', '1234')->first();
        $this->actingAs($admin);

        // List
        $response = $this->getJson('/api/admin/categorias');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json()));

        // Create
        $response = $this->postJson('/api/admin/categorias', ['nombre_cat' => 'Postres']);
        $response->assertStatus(201);
        $this->assertDatabaseHas('Categoria', ['nombre_cat' => 'Postres']);
    }

    /** @test */
    public function test_admin_insumos_crud()
    {
        $admin = Usuario::where('pin_acceso', '1234')->first();
        $this->actingAs($admin);

        // Create
        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => 'Salsa Verde',
            'unidad_medida' => 'litros',
            'stock_actual' => 10,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('Insumo', ['nombre_insumo' => 'Salsa Verde']);
    }

    /** @test */
    public function test_admin_productos_crud()
    {
        $admin = Usuario::where('pin_acceso', '1234')->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Quesadilla Especial',
            'precio' => 25.00,
            'id_categoria' => 1,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('Producto', ['nombre_prod' => 'Quesadilla Especial']);
    }

    /** @test */
    public function test_admin_receta_crud()
    {
        $admin = Usuario::where('pin_acceso', '1234')->first();
        $this->actingAs($admin);

        // Get recipe for product 1
        $response = $this->getJson('/api/admin/receta/1');
        $response->assertStatus(200);

        // Add ingredient
        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => 1,
            'id_insumo' => 3,
            'cantidad_necesaria' => 30,
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function test_order_flow_and_inventory_deduction()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::where('nombre_prod', 'like', '%Tacos%')->first();

        // Get stock before
        $receta = Receta::where('id_producto', $producto->id_producto)->first();
        $insumo = $receta->insumo;
        $stockInicial = $insumo->stock_actual;

        // Send order
        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                [
                    'id_producto' => $producto->id_producto,
                    'cantidad' => 2,
                    'notas' => 'Sin cebolla'
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Mesa should be Occupied
        $this->assertEquals('Ocupada', $mesa->fresh()->estado);

        // Stock should be deducted
        $stockEsperado = $stockInicial - ($receta->cantidad_necesaria * 2);
        $this->assertEquals($stockEsperado, $insumo->fresh()->stock_actual);

        // Order should exist
        $pedido = Pedido::latest('id_pedido')->first();
        $detalle = $pedido->detalles()->first();
        $this->assertEquals('Recibido', $detalle->estado_cocina);
        $this->assertEquals('Sin cebolla', $detalle->notas);
    }

    /** @test */
    public function test_stock_insufficient_validation()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        // Force zero stock
        $receta = Receta::where('id_producto', $producto->id_producto)->first();
        $insumo = $receta->insumo;
        $insumo->update(['stock_actual' => 0]);

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                ['id_producto' => $producto->id_producto, 'cantidad' => 1]
            ]
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Stock insuficiente']);
    }

    /** @test */
    public function test_kitchen_to_billing_flow()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        // Create order
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $pedido = Pedido::latest('id_pedido')->first();
        $detalle = $pedido->detalles()->first();

        // Cook marks as ready
        $cocinero = Usuario::where('pin_acceso', '9012')->first();
        $this->actingAs($cocinero);
        $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/status", [
            'estado_cocina' => 'Listo'
        ]);

        $this->assertEquals('Listo', $detalle->fresh()->estado_cocina);

        // Waiter charges
        $this->actingAs($mesero);
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
    public function test_mesas_api()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/mesas');
        $response->assertStatus(200);
        $this->assertCount(10, $response->json());
    }

    /** @test */
    public function test_menu_api()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        $response = $this->getJson('/api/mesero/menu');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }
}
