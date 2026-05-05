-- ==============================================================================
-- SISTEMA DE BASE DE DATOS: HA LA FRIDA
-- MOTOR: SQL SERVER (T-SQL)
-- DESCRIPCIÓN: Creación Completa (Tablas, Vistas, Triggers, SPs)
-- ==============================================================================

-- ==============================================================================
-- 1. CREACIÓN DE LA BASE DE DATOS
-- ==============================================================================
USE master;
GO

CREATE DATABASE Ha_La_Frida_DB;
GO

USE Ha_La_Frida_DB;
GO

-- ==============================================================================
-- 2. CREACIÓN DE TABLAS CATÁLOGO Y PRINCIPALES
-- ==============================================================================

CREATE TABLE Rol(
    id_rol INT PRIMARY KEY IDENTITY,
    descripcion VARCHAR(255) NOT NULL
);

CREATE TABLE Categoria (
    id_categoria INT PRIMARY KEY IDENTITY NOT NULL,
    nombre_cat VARCHAR(100) NOT NULL
);

CREATE TABLE Mesa(
    id_mesa INT PRIMARY KEY IDENTITY NOT NULL,
    capacidad INT NOT NULL CHECK (capacidad > 0),
    estado VARCHAR(100) DEFAULT 'Libre' NOT NULL
);

CREATE TABLE Insumo (
    id_insumo INT PRIMARY KEY IDENTITY NOT NULL,
    nombre_insumo VARCHAR(100) NOT NULL,
    unidad_medida VARCHAR(100) NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL CHECK (stock_actual >= 0),
    fecha_vencimiento DATE NULL,
    estado VARCHAR(20) DEFAULT 'Activo' NOT NULL
);

CREATE TABLE Usuario (
    id_usuario INT PRIMARY KEY IDENTITY NOT NULL,
    nombre_completo VARCHAR(500) NOT NULL,
    correo VARCHAR(255) UNIQUE NOT NULL,
    pin_acceso VARCHAR(500) UNIQUE NOT NULL,
    estado VARCHAR(20) DEFAULT 'Activo' NOT NULL,
    id_rol INT NOT NULL,
    CONSTRAINT FK_Usuario_Rol FOREIGN KEY(id_rol) REFERENCES Rol(id_rol)
);

CREATE TABLE Producto(
    id_producto INT PRIMARY KEY IDENTITY NOT NULL,
    nombre_prod VARCHAR(100) NOT NULL,
    descripcion VARCHAR(500) NULL,
    precio DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
    url_imagen VARCHAR(500) NULL,
    estado VARCHAR(20) DEFAULT 'Activo' NOT NULL,
    id_categoria INT NOT NULL,
    CONSTRAINT FK_Producto_Categoria FOREIGN KEY(id_categoria) REFERENCES Categoria(id_categoria)
);

-- ==============================================================================
-- 3. CREACIÓN DE TABLAS TRANSACCIONALES
-- ==============================================================================

CREATE TABLE Receta(
    id_receta INT PRIMARY KEY IDENTITY NOT NULL,
    id_producto INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad_necesaria DECIMAL(10,2) NOT NULL CHECK (cantidad_necesaria > 0),
    CONSTRAINT FK_Receta_Producto FOREIGN KEY(id_producto) REFERENCES Producto(id_producto),
    CONSTRAINT FK_Receta_Insumo FOREIGN KEY(id_insumo) REFERENCES Insumo(id_insumo)
);

CREATE TABLE Pedido(
    id_pedido INT PRIMARY KEY IDENTITY NOT NULL,
    estado_pedido VARCHAR(100) DEFAULT 'Recibido' NOT NULL,
    fecha_hora DATETIME DEFAULT GETDATE() NOT NULL,
    id_mesa INT NOT NULL,
    id_usuario INT NOT NULL,
    CONSTRAINT FK_Pedido_Mesa FOREIGN KEY(id_mesa) REFERENCES Mesa(id_mesa),
    CONSTRAINT FK_Pedido_Usuario FOREIGN KEY(id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Factura (
    id_factura INT PRIMARY KEY IDENTITY NOT NULL,
    numero_factura VARCHAR(100) UNIQUE NOT NULL,
    total DECIMAL(10, 2) NOT NULL CHECK (total >= 0),
    metodo_pago VARCHAR(50) NOT NULL,
    fecha_pago DATETIME DEFAULT GETDATE() NOT NULL,
    id_pedido INT NOT NULL,
    CONSTRAINT FK_Factura_Pedido FOREIGN KEY(id_pedido) REFERENCES Pedido(id_pedido)
);

CREATE TABLE Detalle_Pedido(
    id_detalle INT PRIMARY KEY IDENTITY NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL CHECK (precio_unitario >= 0),
    notas VARCHAR(500) NULL,
    estado_cocina VARCHAR(100) DEFAULT 'Recibido' NOT NULL,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    CONSTRAINT FK_Detalle_Pedido FOREIGN KEY(id_pedido) REFERENCES Pedido(id_pedido),
    CONSTRAINT FK_Detalle_Producto FOREIGN KEY(id_producto) REFERENCES Producto(id_producto)
);
GO

-- ==============================================================================
-- 4. VISTAS (VIEWS) PARA REPORTES
-- ==============================================================================

-- Vista: Ventas del Día
CREATE VIEW vw_VentasDelDia AS
SELECT 
    CAST(fecha_pago AS DATE) AS Fecha,
    COUNT(id_factura) AS Cantidad_Facturas,
    SUM(total) AS Ingresos_Totales
FROM Factura
WHERE CAST(fecha_pago AS DATE) = CAST(GETDATE() AS DATE)
GROUP BY CAST(fecha_pago AS DATE);
GO

-- Vista: Inventario Crítico (Avisa cuando quedan 10 unidades/gramos o menos)
CREATE VIEW vw_InventarioCritico AS
SELECT 
    id_insumo, 
    nombre_insumo, 
    stock_actual, 
    unidad_medida
FROM Insumo
WHERE stock_actual <= 10.00 AND estado = 'Activo';
GO

-- ==============================================================================
-- 5. DISPARADORES (TRIGGERS)
-- ==============================================================================

-- Trigger: Descontar Inventario Automáticamente al insertar un pedido
CREATE TRIGGER trg_DescontarInventario
ON Detalle_Pedido
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Cruza lo que se acaba de insertar con la receta y actualiza el inventario
    UPDATE i
    SET i.stock_actual = i.stock_actual - (i_det.cantidad * r.cantidad_necesaria)
    FROM Insumo i
    INNER JOIN Receta r ON i.id_insumo = r.id_insumo
    INNER JOIN inserted i_det ON r.id_producto = i_det.id_producto;
END;
GO

-- ==============================================================================
-- 6. PROCEDIMIENTOS ALMACENADOS (STORED PROCEDURES)
-- ==============================================================================

-- Procedimiento: Registrar Pedido y Ocupar Mesa
CREATE PROCEDURE sp_RegistrarPedido
    @p_id_mesa INT,
    @p_id_usuario INT,
    @p_id_pedido INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Verifica regla de negocio: La mesa debe estar libre
    IF EXISTS (SELECT 1 FROM Mesa WHERE id_mesa = @p_id_mesa AND estado = 'Libre')
    BEGIN
        INSERT INTO Pedido (id_mesa, id_usuario, estado_pedido)
        VALUES (@p_id_mesa, @p_id_usuario, 'Recibido');
        
        SET @p_id_pedido = SCOPE_IDENTITY();
        
        UPDATE Mesa SET estado = 'Ocupada' WHERE id_mesa = @p_id_mesa;
    END
    ELSE
    BEGIN
        RAISERROR('Regla de negocio: La mesa no está Libre o no existe.', 16, 1);
    END
END;
GO

-- Procedimiento: Procesar Pago y Cerrar Mesa
CREATE PROCEDURE sp_ProcesarPago
    @p_id_pedido INT,
    @p_metodo_pago VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @total_calculado DECIMAL(10,2);
    DECLARE @id_mesa INT;
    DECLARE @numero_factura VARCHAR(100);

    -- Calcula el total sumando (cantidad * precio_unitario) de los detalles
    SELECT @total_calculado = ISNULL(SUM(cantidad * precio_unitario), 0)
    FROM Detalle_Pedido
    WHERE id_pedido = @p_id_pedido;

    SELECT @id_mesa = id_mesa FROM Pedido WHERE id_pedido = @p_id_pedido;
    
    -- Crea un número de factura basado en la fecha y el pedido
    SET @numero_factura = 'FACT-' + FORMAT(GETDATE(), 'yyyyMMdd') + '-' + CAST(@p_id_pedido AS VARCHAR);

    -- Inserción de la Factura
    INSERT INTO Factura (numero_factura, total, metodo_pago, id_pedido)
    VALUES (@numero_factura, @total_calculado, @p_metodo_pago, @p_id_pedido);

    -- Actualiza estados para cerrar el ciclo
    UPDATE Pedido SET estado_pedido = 'Pagado' WHERE id_pedido = @p_id_pedido;
    UPDATE Mesa SET estado = 'Libre' WHERE id_mesa = @id_mesa;
END;
GO
