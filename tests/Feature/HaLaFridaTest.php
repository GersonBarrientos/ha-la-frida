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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class HaLaFridaTest extends TestCase
{
    // Usamos RefreshDatabase para que cada test empiece de cero y carguemos seeds
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cargamos los datos iniciales necesarios
        $this->seed();
    }

    /** @test */
    public function test_login_with_pin_only()
    {
        $mesero = Usuario::where('pin_acceso', '5678')->first();

        $response = $this->post('/login', [
            'pin_acceso' => '5678',
        ]);

        $response->assertRedirect('/mesero/dashboard');
        $this->assertAuthenticatedAs($mesero);
    }

    /** @test */
    public function test_order_flow_and_inventory_deduction()
    {
        // 1. Login como mesero
        $mesero = Usuario::where('pin_acceso', '5678')->first();
        $this->actingAs($mesero);

        // 2. Elegir una mesa libre
        $mesa = Mesa::where('estado', 'Libre')->first();
        
        // 3. Elegir un producto (ej: Tacos al Pastor)
        $producto = Producto::where('nombre_prod', 'like', '%Tacos%')->first();
        
        // Verificar stock inicial de un insumo de la receta
        $receta = Receta::where('id_producto', $producto->id_producto)->first();
        $insumo = $receta->insumo;
        $stockInicial = $insumo->stock_actual;

        // 4. Enviar orden a cocina
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
        
        // 5. Verificar que la mesa cambió a Ocupada
        $this->assertEquals('Ocupada', $mesa->fresh()->estado);

        // 6. Verificar que el trigger restó inventario (si el trigger está en INSERT)
        $stockEsperado = $stockInicial - ($receta->cantidad_necesaria * 2);
        $this->assertEquals($stockEsperado, $insumo->fresh()->stock_actual);

        // 7. Verificar que el pedido existe en cocina como 'Recibido'
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
        
        // Forzar stock a cero
        $receta = Receta::where('id_producto', $producto->id_producto)->first();
        $insumo = $receta->insumo;
        $insumo->update(['stock_actual' => 0]);

        // Intentar pedir
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
        // 1. Crear un pedido previo
        $this->actingAs(Usuario::where('pin_acceso', '5678')->first());
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();
        
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);
        
        $pedido = Pedido::latest('id_pedido')->first();
        $detalle = $pedido->detalles()->first();

        // 2. Cocinero marca como Listo
        $this->actingAs(Usuario::where('pin_acceso', '9012')->first());
        $this->postJson("/api/cocina/orders/{$detalle->id_detalle}/status", [
            'estado_cocina' => 'Listo'
        ]);
        
        $this->assertEquals('Listo', $detalle->fresh()->estado_cocina);

        // 3. Mesero cobra el pedido
        $this->actingAs(Usuario::where('pin_acceso', '5678')->first());
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Efectivo'
        ]);

        $response->assertStatus(200);

        // 4. Verificar cierre del ciclo
        $this->assertEquals('Pagado', $pedido->fresh()->estado_pedido);
        $this->assertEquals('Libre', $mesa->fresh()->estado);
        
        // Verificar factura creada
        $this->assertDatabaseHas('Factura', ['id_pedido' => $pedido->id_pedido]);
    }
}
