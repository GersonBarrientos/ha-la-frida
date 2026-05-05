-- ==============================================================================
-- PATCH: CLIENT DATA AND IMPROVEMENTS
-- ==============================================================================

USE Ha_La_Frida_DB;
GO

-- 1. Agregar campos de cliente a Pedido
ALTER TABLE Pedido ADD nombre_cliente VARCHAR(255) NULL;
ALTER TABLE Pedido ADD nit_cliente VARCHAR(50) NULL;
GO

-- 2. Agregar campos de cliente a Factura
ALTER TABLE Factura ADD nombre_cliente VARCHAR(255) NULL;
ALTER TABLE Factura ADD nit_cliente VARCHAR(50) NULL;
GO

-- 3. Actualizar Procedimiento sp_ProcesarPago para incluir datos del cliente
DROP PROCEDURE IF EXISTS sp_ProcesarPago;
GO

CREATE PROCEDURE sp_ProcesarPago
    @p_id_pedido INT,
    @p_metodo_pago VARCHAR(50),
    @p_nombre_cliente VARCHAR(255) = NULL,
    @p_nit_cliente VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @total_calculado DECIMAL(10,2);
    DECLARE @id_mesa INT;
    DECLARE @numero_factura VARCHAR(100);

    -- Si no se pasan datos, intentar tomarlos del pedido
    IF @p_nombre_cliente IS NULL
        SELECT @p_nombre_cliente = nombre_cliente FROM Pedido WHERE id_pedido = @p_id_pedido;
    
    IF @p_nit_cliente IS NULL
        SELECT @p_nit_cliente = nit_cliente FROM Pedido WHERE id_pedido = @p_id_pedido;

    -- Calcula el total
    SELECT @total_calculado = ISNULL(SUM(cantidad * precio_unitario), 0)
    FROM Detalle_Pedido
    WHERE id_pedido = @p_id_pedido;

    SELECT @id_mesa = id_mesa FROM Pedido WHERE id_pedido = @p_id_pedido;
    
    -- Número de factura secuencial (mejorado con ceros)
    SET @numero_factura = 'FACT-' + FORMAT(GETDATE(), 'yyyyMMdd') + '-' + RIGHT('0000' + CAST(@p_id_pedido AS VARCHAR), 4);

    -- Inserción de la Factura
    INSERT INTO Factura (numero_factura, total, metodo_pago, id_pedido, nombre_cliente, nit_cliente)
    VALUES (@numero_factura, @total_calculado, @p_metodo_pago, @p_id_pedido, @p_nombre_cliente, @p_nit_cliente);

    -- Actualiza estados
    UPDATE Pedido SET estado_pedido = 'Pagado' WHERE id_pedido = @p_id_pedido;
    UPDATE Mesa SET estado = 'Libre' WHERE id_mesa = @id_mesa;
END;
GO
