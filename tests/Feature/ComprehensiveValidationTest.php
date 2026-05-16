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
use App\Models\Categoria;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ComprehensiveValidationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ============================================
    // VALIDACIÓN: CAMPOS REQUERIDOS
    // ============================================

    /** @test */
    public function test_validation_categoria_nombre_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/categorias', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_cat');
    }

    /** @test */
    public function test_validation_insumo_nombre_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/insumos', [
            'unidad_medida' => 'kg'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_insumo');
    }

    /** @test */
    public function test_validation_producto_nombre_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'precio' => 10.00,
            'id_categoria' => 1
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_prod');
    }

    /** @test */
    public function test_validation_producto_precio_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Test',
            'id_categoria' => 1
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('precio');
    }

    /** @test */
    public function test_validation_usuario_nombre_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/usuarios', [
            'correo' => 'test@test.com',
            'pin_acceso' => '1234',
            'id_rol' => 2
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_completo');
    }

    // ============================================
    // VALIDACIÓN: TIPOS DE DATO
    // ============================================

    /** @test */
    public function test_validation_precio_must_be_numeric()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Test',
            'precio' => 'abc',
            'id_categoria' => 1
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('precio');
    }

    /** @test */
    public function test_validation_stock_must_be_numeric()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => 'Test',
            'unidad_medida' => 'kg',
            'stock_actual' => 'invalid'
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_id_must_be_integer()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/receta/abc');
        $response->assertStatus(404);
    }

    // ============================================
    // VALIDACIÓN: RESTRICCIONES DE NEGOCIO
    // ============================================

    /** @test */
    public function test_validation_precio_not_negative()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Test',
            'precio' => -5.00,
            'id_categoria' => 1
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_cantidad_necesaria_positive()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => 1,
            'id_insumo' => 1,
            'cantidad_necesaria' => -10
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_categoria_unique_constraint()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        // Crear primera
        $this->postJson('/api/admin/categorias', ['nombre_cat' => 'Unique Test']);

        // Intenta crear duplicada
        $response = $this->postJson('/api/admin/categorias', ['nombre_cat' => 'Unique Test']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre_cat');
    }

    /** @test */
    public function test_validation_order_no_items_empty()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => []
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_order_cantidad_positive()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                ['id_producto' => $producto->id_producto, 'cantidad' => -1]
            ]
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_metodo_pago_valid_values()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        // Create order first
        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $pedido = Pedido::latest('id_pedido')->first();
        $detalle = $pedido->detalles()->first();
        $detalle->update(['estado_cocina' => 'Listo']);

        // Invalid payment method
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Cripto'
        ]);
        $response->assertStatus(422);
    }

    // ============================================
    // VALIDACIÓN: LONGITUD DE CAMPOS
    // ============================================

    /** @test */
    public function test_validation_nombre_cat_max_length()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $longString = str_repeat('a', 101);
        $response = $this->postJson('/api/admin/categorias', [
            'nombre_cat' => $longString
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_nombre_insumo_max_length()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $longString = str_repeat('a', 151);
        $response = $this->postJson('/api/admin/insumos', [
            'nombre_insumo' => $longString,
            'unidad_medida' => 'kg'
        ]);
        $response->assertStatus(422);
    }

    // ============================================
    // VALIDACIÓN: FORMATOS EMAIL
    // ============================================

    /** @test */
    public function test_validation_correo_format_usuario()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/usuarios', [
            'nombre_completo' => 'Test',
            'correo' => 'invalid-email',
            'pin_acceso' => '1234',
            'id_rol' => 2
        ]);
        $response->assertStatus(422);
    }

    // ============================================
    // VALIDACIÓN: AUTENTICACIÓN
    // ============================================

    /** @test */
    public function test_validation_authenticated_user_required()
    {
        $response = $this->getJson('/api/admin/stats');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_validation_admin_role_required()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $response = $this->postJson('/api/admin/categorias', [
            'nombre_cat' => 'Test'
        ]);
        // Debería ser 403 o 401 según implementación
        $this->assertIn($response->status(), [401, 403]);
    }

    /** @test */
    public function test_validation_mesero_role_required()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $mesa = Mesa::first();
        $response = $this->getJson("/api/mesero/pedido-activo/{$mesa->id_mesa}");
        // Debería ser 403 o 401 según implementación
        $this->assertIn($response->status(), [401, 403]);
    }

    // ============================================
    // VALIDACIÓN: FK CONSTRAINTS
    // ============================================

    /** @test */
    public function test_validation_producto_categoria_must_exist()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/productos', [
            'nombre_prod' => 'Test',
            'precio' => 10.00,
            'id_categoria' => 9999
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_receta_producto_must_exist()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => 9999,
            'id_insumo' => 1,
            'cantidad_necesaria' => 100
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_receta_insumo_must_exist()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/recetas', [
            'id_producto' => 1,
            'id_insumo' => 9999,
            'cantidad_necesaria' => 100
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_validation_usuario_rol_must_exist()
    {
        $admin = Usuario::where('id_rol', 1)->first();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/usuarios', [
            'nombre_completo' => 'Test',
            'correo' => 'test@test.com',
            'pin_acceso' => '1234',
            'id_rol' => 9999
        ]);
        $response->assertStatus(422);
    }

    // ============================================
    // VALIDACIÓN: ESTADO DE PEDIDO
    // ============================================

    /** @test */
    public function test_validation_cannot_charge_if_not_ready()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        $pedido = Pedido::latest('id_pedido')->first();

        // Try to charge before items are ready
        $response = $this->postJson('/api/mesero/cobrar', [
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'Efectivo'
        ]);

        // Should fail because items are not in "Listo" state
        $this->assertIn($response->status(), [400, 422]);
    }

    /** @test */
    public function test_validation_cannot_order_on_occupied_mesa()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        // First order
        $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        // Try second order on same mesa
        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [['id_producto' => $producto->id_producto, 'cantidad' => 1]]
        ]);

        // Should fail because mesa is occupied
        $this->assertIn($response->status(), [400, 422]);
    }

    // ============================================
    // VALIDACIÓN: STRING SANITIZATION
    // ============================================

    /** @test */
    public function test_validation_notas_sanitized()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                [
                    'id_producto' => $producto->id_producto,
                    'cantidad' => 1,
                    'notas' => '<script>alert("xss")</script>'
                ]
            ]
        ]);

        if ($response->status() === 200) {
            $detalle = DetallePedido::latest('id_detalle')->first();
            // Notas should not contain script tags
            $this->assertStringNotContainsString('<script>', $detalle->notas);
        }
    }

    // ============================================
    // VALIDACIÓN: NULL HANDLING
    // ============================================

    /** @test */
    public function test_validation_optional_notas_can_be_null()
    {
        $mesero = Usuario::where('id_rol', 2)->first();
        $this->actingAs($mesero);

        $mesa = Mesa::where('estado', 'Libre')->first();
        $producto = Producto::first();

        $response = $this->postJson('/api/mesero/order', [
            'id_mesa' => $mesa->id_mesa,
            'items' => [
                [
                    'id_producto' => $producto->id_producto,
                    'cantidad' => 1,
                    'notas' => null
                ]
            ]
        ]);

        $response->assertStatus(200);
    }
}
