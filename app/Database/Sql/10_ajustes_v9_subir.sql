/* ==========================================================================
   Ajustes de la v9 por las devoluciones de la presentación del 26/10/2026
   D-10 Simulación comparativa de líneas (CU-99)      -> SS-04
   D-11 Cobro con varios medios de pago (RN-29)       -> SS-05
   D-12 Pago parcial ordinario de cuotas (RN-29)      -> SS-05
   Los límites de importe por línea (D-09) ya existen en versiones_condiciones (monto_minimo, monto_maximo).
   ========================================================================== */

/* ---------------------------------------------------------------- D-11: medios de cada cobro */

-- cobros_medios: Medios con los que se pagó un cobro y el importe aplicado con cada uno. El efectivo se informa en una
-- sola fila por cobro y conserva lo recibido (el cambio es la diferencia); los demás medios llevan su número de operación
-- y no generan cambio (RN-29, D-11). El cobro electrónico tiene una sola fila PagoElectronico.
CREATE TABLE dbo.cobros_medios (
    id BIGINT IDENTITY(1,1) NOT NULL,
    cobro_id BIGINT NOT NULL,
    medio VARCHAR(15) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    importe_recibido DECIMAL(19,2) NOT NULL,
    numero_operacion VARCHAR(60) NULL,
    CONSTRAINT PK_cobros_medios PRIMARY KEY (id),
    CONSTRAINT CK_cobros_medios_medio CHECK (medio IN ('Efectivo', 'TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico')),
    CONSTRAINT CK_cobros_medios_importe CHECK (importe > 0 AND importe_recibido >= importe),
    CONSTRAINT CK_cobros_medios_cambio CHECK (medio = 'Efectivo' OR importe_recibido = importe),
    CONSTRAINT CK_cobros_medios_operacion CHECK (medio IN ('Efectivo', 'PagoElectronico') OR numero_operacion IS NOT NULL)
);
GO
ALTER TABLE dbo.cobros_medios ADD CONSTRAINT FK_cobros_medios_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
CREATE INDEX IX_cobros_medios_cobro ON dbo.cobros_medios (cobro_id) INCLUDE (medio, importe);
CREATE UNIQUE INDEX UQ_cobros_medios_efectivo ON dbo.cobros_medios (cobro_id) WHERE medio = 'Efectivo';
CREATE UNIQUE INDEX UQ_cobros_medios_operacion ON dbo.cobros_medios (cobro_id, medio, numero_operacion) WHERE numero_operacion IS NOT NULL;
GO

-- El medio y el número de operación pasan del cobro a sus medios; el cobro conserva el cambio entregado.
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_canal_datos;
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_operacion;
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_medio;
DROP INDEX IX_cobros_jornada ON dbo.cobros;
GO
ALTER TABLE dbo.cobros DROP COLUMN medio, numero_operacion;
ALTER TABLE dbo.cobros ADD cambio DECIMAL(19,2) NOT NULL CONSTRAINT DF_cobros_cambio DEFAULT (0);
GO
CREATE INDEX IX_cobros_jornada ON dbo.cobros (jornada_caja_id) INCLUDE (importe, cambio, estado);
GO
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_canal_datos CHECK ((canal = 'Ventanilla' AND jornada_caja_id IS NOT NULL AND usuario_id IS NOT NULL) OR (canal = 'Electronico' AND transaccion_pasarela_id IS NOT NULL AND cambio = 0));
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_cambio CHECK (cambio >= 0);
GO

-- Cada medio de un cobro en ventanilla genera su movimiento de caja; el arqueo sigue sumando solo el efectivo (RN-32).
DROP INDEX UQ_movimientos_caja_cobro_id ON dbo.movimientos_caja;
ALTER TABLE dbo.movimientos_caja DROP CONSTRAINT CK_movimientos_caja_cobro;
GO
ALTER TABLE dbo.movimientos_caja ADD cobro_medio_id BIGINT NULL;
GO
ALTER TABLE dbo.movimientos_caja ADD CONSTRAINT FK_movimientos_caja_cobro_medio_id FOREIGN KEY (cobro_medio_id) REFERENCES dbo.cobros_medios (id);
ALTER TABLE dbo.movimientos_caja ADD CONSTRAINT CK_movimientos_caja_cobro CHECK ((tipo = 'Cobro' AND cobro_id IS NOT NULL AND cobro_medio_id IS NOT NULL) OR (tipo <> 'Cobro' AND cobro_id IS NULL AND cobro_medio_id IS NULL));
CREATE UNIQUE INDEX UQ_movimientos_caja_cobro_medio_id ON dbo.movimientos_caja (cobro_medio_id) WHERE cobro_medio_id IS NOT NULL;
GO

/* ---------------------------------------------------------------- D-12: pago parcial ordinario */

-- La marca deja de ser excepcional: indica que la imputación no canceló el concepto de la cuota, en un cobro ordinario
-- parcial (D-12) o en las imputaciones de RN-56 y RN-57.
EXEC sp_rename 'dbo.imputaciones.parcial_excepcional', 'parcial', 'COLUMN';
EXEC sp_rename 'dbo.DF_imputaciones_parcial_excepcional', 'DF_imputaciones_parcial', 'OBJECT';
GO

/* ---------------------------------------------------------------- D-10: simulación comparativa */

-- simulaciones_comparativas: Simulación de todas las líneas admitidas para un cliente con una sola capacidad de pago
-- (CU-99). La alternativa recomendada de cada línea viable se guarda como una simulación vinculada, que puede
-- formalizarse con CU-22; las líneas no viables se conservan con su motivo. No genera solicitud ni deuda (RN-17).
CREATE TABLE dbo.simulaciones_comparativas (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    version_parametros_id INT NOT NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_simulaciones_comparativas_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    ingresos_considerados DECIMAL(19,2) NOT NULL,
    obligaciones_vigentes DECIMAL(19,2) NOT NULL,
    capacidad_pago DECIMAL(19,2) NOT NULL,
    importe_pedido DECIMAL(19,2) NULL,
    cuotas_pedidas SMALLINT NULL,
    criterio VARCHAR(10) NOT NULL,
    lineas_no_viables NVARCHAR(MAX) NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_simulaciones_comparativas PRIMARY KEY (id),
    CONSTRAINT CK_simulaciones_comparativas_criterio CHECK (criterio IN ('Monto', 'Costo')),
    CONSTRAINT CK_simulaciones_comparativas_pedido CHECK ((importe_pedido IS NULL OR importe_pedido > 0) AND (cuotas_pedidas IS NULL OR cuotas_pedidas > 0)),
    CONSTRAINT CK_simulaciones_comparativas_no_viables CHECK (lineas_no_viables IS NULL OR ISJSON(lineas_no_viables) = 1)
);
GO
ALTER TABLE dbo.simulaciones_comparativas ADD CONSTRAINT FK_simulaciones_comparativas_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.simulaciones_comparativas ADD CONSTRAINT FK_simulaciones_comparativas_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.simulaciones_comparativas ADD CONSTRAINT FK_simulaciones_comparativas_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_simulaciones_comparativas_cliente ON dbo.simulaciones_comparativas (cliente_id, fecha_generacion);
GO
ALTER TABLE dbo.simulaciones ADD simulacion_comparativa_id INT NULL, posicion TINYINT NULL;
GO
ALTER TABLE dbo.simulaciones ADD CONSTRAINT FK_simulaciones_simulacion_comparativa_id FOREIGN KEY (simulacion_comparativa_id) REFERENCES dbo.simulaciones_comparativas (id);
ALTER TABLE dbo.simulaciones ADD CONSTRAINT CK_simulaciones_comparativa CHECK ((simulacion_comparativa_id IS NULL AND posicion IS NULL) OR (simulacion_comparativa_id IS NOT NULL AND posicion >= 1));
CREATE INDEX IX_simulaciones_simulacion_comparativa_id ON dbo.simulaciones (simulacion_comparativa_id) WHERE simulacion_comparativa_id IS NOT NULL;
GO
