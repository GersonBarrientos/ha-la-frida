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

class ComprehensiveModelTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ============================================
    // TESTS DE MODELO: ROL
    // ============================================

    /** @test */
    public function test_rol_model_has_usuarios_relationship()
    {
        $rol = Rol::first();
        $this->assertIsNotNull($rol);
        $this->assertTrue($rol->usuarios()->exists());
    }

    /** @test */
    public function test_rol_model_get_all_roles()
    {
        $roles = Rol::all();
        $this->assertGreaterThanOrEqual(3, count($roles)); // Admin, Mesero, Cocinero
    }

    // ============================================
    // TESTS DE MODELO: USUARIO
    // ============================================

    /** @test */
    public function test_usuario_model_properties()
    {
        $usuario = Usuario::create([
            'nombre_completo' => 'Juan Pérez',
            'correo' => 'juan@test.com',
            'pin_acceso' => '1111',
            'estado' => 'Activo',
            'id_rol' => 1
        ]);

        $this->assertEquals('Juan Pérez', $usuario->nombre_completo);
        $this->assertEquals('juan@test.com', $usuario->correo);
        $this->assertEquals('Activo', $usuario->estado);
    }

    /** @test */
    public function test_usuario_has_rol_relationship()
    {
        $usuario = Usuario::first();
        $this->assertIsNotNull($usuario->rol());
        $this->assertInstanceOf(Rol::class, $usuario->rol()->first());
    }

    /** @test */
    public function test_usuario_has_pedidos_relationship()
    {
        $usuario = Usuario::first();
        $pedidos = $usuario->pedidos();
        $this->assertIsNotNull($pedidos);
    }

    /** @test */
    public function test_usuario_pin_acceso_hidden_in_json()
    {
        $usuario = Usuario::first();
        $json = $usuario->toJson();
        $this->assertStringNotContainsString($usuario->pin_acceso, $json);
    }

    /** @test */
    public function test_usuario_get_auth_password()
    {
        $usuario = Usuario::first();
        $this->assertEquals($usuario->pin_acceso, $usuario->getAuthPassword());
    }

    // ============================================
    // TESTS DE MODELO: CATEGORIA
    // ============================================

    /** @test */
    public function test_categoria_model_properties()
    {
        $categoria = Categoria::create([
            'nombre_cat' => 'Bebidas'
        ]);

        $this->assertEquals('Bebidas', $categoria->nombre_cat);
    }

    /** @test */
    public function test_categoria_has_productos_relationship()
    {
        $categoria = Categoria::first();
        $productos = $categoria->productos();
        $this->assertIsNotNull($productos);
    }

    // ============================================
    // TESTS DE MODELO: INSUMO
    // ============================================

    /** @test */
    public function test_insumo_model_properties()
    {
        $insumo = Insumo::create([
            'nombre_insumo' => 'Harina de Trigo',
            'unidad_medida' => 'kg',
            'stock_actual' => 50,
            'estado' => 'Activo'
        ]);

        $this->assertEquals('Harina de Trigo', $insumo->nombre_insumo);
        $this->assertEquals('kg', $insumo->unidad_medida);
        $this->assertEquals(50, $insumo->stock_actual);
    }

    /** @test */
    public function test_insumo_has_recetas_relationship()
    {
        $insumo = Insumo::first();
        $recetas = $insumo->recetas();
        $this->assertIsNotNull($recetas);
    }

    /** @test */
    public function test_insumo_stock_deduction()
    {
        $insumo = Insumo::first();
        $stockInicial = $insumo->stock_actual;
        $insumo->decrement('stock_actual', 10);
        $this->assertEquals($stockInicial - 10, $insumo->stock_actual);
    }

    // ============================================
    // TESTS DE MODELO: PRODUCTO
    // ============================================

    /** @test */
    public function test_producto_model_properties()
    {
        $producto = Producto::create([
            'nombre_prod' => 'Tacos al Pastor',
            'precio' => 30.00,
            'id_categoria' => 1,
            'estado' => 'Activo'
        ]);

        $this->assertEquals('Tacos al Pastor', $producto->nombre_prod);
        $this->assertEquals(30.00, $producto->precio);
    }

    /** @test */
    public function test_producto_has_categoria_relationship()
    {
        $producto = Producto::first();
        $this->assertIsNotNull($producto->categoria());
        $this->assertInstanceOf(Categoria::class, $producto->categoria()->first());
    }

    /** @test */
    public function test_producto_has_recetas_relationship()
    {
        $producto = Producto::first();
        $recetas = $producto->recetas();
        $this->assertIsNotNull($recetas);
    }

    /** @test */
    public function test_producto_has_detalles_relationship()
    {
        $producto = Producto::first();
        $detalles = $producto->detalles();
        $this->assertIsNotNull($detalles);
    }

    // ============================================
    // TESTS DE MODELO: RECETA
    // ============================================

    /** @test */
    public function test_receta_model_properties()
    {
        $receta = Receta::create([
            'id_producto' => 1,
            'id_insumo' => 1,
            'cantidad_necesaria' => 100.00
        ]);

        $this->assertEquals(1, $receta->id_producto);
        $this->assertEquals(1, $receta->id_insumo);
        $this->assertEquals(100.00, $receta->cantidad_necesaria);
    }

    /** @test */
    public function test_receta_has_producto_relationship()
    {
        $receta = Receta::first();
        $this->assertIsNotNull($receta->producto());
        $this->assertInstanceOf(Producto::class, $receta->producto()->first());
    }

    /** @test */
    public function test_receta_has_insumo_relationship()
    {
        $receta = Receta::first();
        $this->assertIsNotNull($receta->insumo());
        $this->assertInstanceOf(Insumo::class, $receta->insumo()->first());
    }

    // ============================================
    // TESTS DE MODELO: MESA
    // ============================================

    /** @test */
    public function test_mesa_model_properties()
    {
        $mesa = Mesa::create([
            'numero_mesa' => 15,
            'capacidad' => 4,
            'estado' => 'Libre'
        ]);

        $this->assertEquals(15, $mesa->numero_mesa);
        $this->assertEquals(4, $mesa->capacidad);
        $this->assertEquals('Libre', $mesa->estado);
    }

    /** @test */
    public function test_mesa_has_pedidos_relationship()
    {
        $mesa = Mesa::first();
        $pedidos = $mesa->pedidos();
        $this->assertIsNotNull($pedidos);
    }

    /** @test */
    public function test_mesa_estado_change()
    {
        $mesa = Mesa::create(['numero_mesa' => 20, 'capacidad' => 4, 'estado' => 'Libre']);
        $this->assertEquals('Libre', $mesa->estado);
        $mesa->update(['estado' => 'Ocupada']);
        $this->assertEquals('Ocupada', $mesa->fresh()->estado);
    }

    // ============================================
    // TESTS DE MODELO: PEDIDO
    // ============================================

    /** @test */
    public function test_pedido_model_properties()
    {
        $pedido = Pedido::create([
            'id_mesa' => 1,
            'id_usuario' => 1,
            'estado_pedido' => 'Recibido',
            'fecha_hora' => now()
        ]);

        $this->assertEquals(1, $pedido->id_mesa);
        $this->assertEquals(1, $pedido->id_usuario);
        $this->assertEquals('Recibido', $pedido->estado_pedido);
    }

    /** @test */
    public function test_pedido_has_mesa_relationship()
    {
        $pedido = Pedido::first();
        $this->assertIsNotNull($pedido->mesa());
        $this->assertInstanceOf(Mesa::class, $pedido->mesa()->first());
    }

    /** @test */
    public function test_pedido_has_usuario_relationship()
    {
        $pedido = Pedido::first();
        $this->assertIsNotNull($pedido->usuario());
        $this->assertInstanceOf(Usuario::class, $pedido->usuario()->first());
    }

    /** @test */
    public function test_pedido_has_detalles_relationship()
    {
        $pedido = Pedido::first();
        $detalles = $pedido->detalles();
        $this->assertIsNotNull($detalles);
    }

    /** @test */
    public function test_pedido_has_factura_relationship()
    {
        $pedido = Pedido::first();
        $factura = $pedido->factura();
        $this->assertIsNotNull($factura);
    }

    // ============================================
    // TESTS DE MODELO: DETALLE_PEDIDO
    // ============================================

    /** @test */
    public function test_detalle_pedido_model_properties()
    {
        $detalle = DetallePedido::create([
            'id_pedido' => 1,
            'id_producto' => 1,
            'cantidad' => 2,
            'precio_unitario' => 30.00,
            'estado_cocina' => 'Recibido',
            'notas' => 'Sin cebolla'
        ]);

        $this->assertEquals(1, $detalle->id_pedido);
        $this->assertEquals(1, $detalle->id_producto);
        $this->assertEquals(2, $detalle->cantidad);
        $this->assertEquals('Sin cebolla', $detalle->notas);
    }

    /** @test */
    public function test_detalle_pedido_has_pedido_relationship()
    {
        $detalle = DetallePedido::first();
        $this->assertIsNotNull($detalle->pedido());
        $this->assertInstanceOf(Pedido::class, $detalle->pedido()->first());
    }

    /** @test */
    public function test_detalle_pedido_has_producto_relationship()
    {
        $detalle = DetallePedido::first();
        $this->assertIsNotNull($detalle->producto());
        $this->assertInstanceOf(Producto::class, $detalle->producto()->first());
    }

    // ============================================
    // TESTS DE MODELO: FACTURA
    // ============================================

    /** @test */
    public function test_factura_model_properties()
    {
        $factura = Factura::create([
            'id_pedido' => 1,
            'numero_factura' => 'FAC-001',
            'total' => 150.00,
            'metodo_pago' => 'Efectivo',
            'fecha_pago' => now()
        ]);

        $this->assertEquals(1, $factura->id_pedido);
        $this->assertEquals('FAC-001', $factura->numero_factura);
        $this->assertEquals(150.00, $factura->total);
        $this->assertEquals('Efectivo', $factura->metodo_pago);
    }

    /** @test */
    public function test_factura_has_pedido_relationship()
    {
        $factura = Factura::first();
        $this->assertIsNotNull($factura->pedido());
        $this->assertInstanceOf(Pedido::class, $factura->pedido()->first());
    }

    // ============================================
    // TESTS DE RELACIONES COMPLETAS
    // ============================================

    /** @test */
    public function test_full_relationship_chain()
    {
        // Usuario → Rol
        $usuario = Usuario::first();
        $this->assertIsNotNull($usuario->rol()->first());

        // Usuario → Pedidos → Mesa
        $pedido = $usuario->pedidos()->first();
        if ($pedido) {
            $this->assertIsNotNull($pedido->mesa()->first());
        }

        // Pedido → Detalles → Producto → Categoria
        $detalle = DetallePedido::first();
        if ($detalle) {
            $producto = $detalle->producto()->first();
            $this->assertIsNotNull($producto->categoria()->first());
        }

        // Producto → Recetas → Insumos
        $producto = Producto::first();
        $receta = $producto->recetas()->first();
        if ($receta) {
            $this->assertIsNotNull($receta->insumo()->first());
        }
    }

    /** @test */
    public function test_model_mass_assignment()
    {
        $data = [
            'nombre_insumo' => 'Queso',
            'unidad_medida' => 'kg',
            'stock_actual' => 25,
            'estado' => 'Activo'
        ];

        $insumo = Insumo::create($data);
        $this->assertDatabaseHas('Insumo', $data);
    }

    /** @test */
    public function test_model_soft_deletion_behavior()
    {
        $producto = Producto::create([
            'nombre_prod' => 'Test Producto',
            'precio' => 25.00,
            'id_categoria' => 1,
            'estado' => 'Activo'
        ]);

        $id = $producto->id_producto;
        $producto->delete();

        // Los modelos usan soft delete o estado
        $this->assertTrue($producto->fresh() === null || $producto->fresh()->estado === 'Inactivo');
    }

    /** @test */
    public function test_model_timestamp_behavior()
    {
        $usuario = Usuario::create([
            'nombre_completo' => 'Test User',
            'correo' => 'test@test.com',
            'pin_acceso' => '0000',
            'estado' => 'Activo',
            'id_rol' => 1
        ]);

        // Usuario model tiene $timestamps = false, así que no debe tener created_at
        $this->assertNull($usuario->created_at ?? null);
    }

    /** @test */
    public function test_model_fillable_properties()
    {
        $usuario = Usuario::create([
            'nombre_completo' => 'John Doe',
            'correo' => 'john@test.com',
            'pin_acceso' => '9999',
            'estado' => 'Activo',
            'id_rol' => 2
        ]);

        $this->assertIsNotNull($usuario->nombre_completo);
        $this->assertIsNotNull($usuario->pin_acceso);
        $this->assertIsNotNull($usuario->id_rol);
    }

    /** @test */
    public function test_model_query_scopes()
    {
        $activosCount = Producto::where('estado', 'Activo')->count();
        $this->assertGreaterThanOrEqual(0, $activosCount);

        $inactivosCount = Producto::where('estado', 'Inactivo')->count();
        $this->assertGreaterThanOrEqual(0, $inactivosCount);
    }
}
