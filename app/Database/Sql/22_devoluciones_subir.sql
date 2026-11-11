/* ==========================================================================
   Saldos a favor y devoluciones (D-37, RN-57, RN-73; CU-76 a CU-78)
   - autorizaciones_devolucion: constancia de la solicitud del cliente, entidad y evidencia de titularidad de la
     cuenta destino, y datos de la anulación (CU-77 A5).
   - ejecuciones_devolucion: importe devuelto, cuenta y fecha de la transferencia y detalle del resultado (CU-78).
   Las tablas no tienen filas hasta esta iteración: las columnas obligatorias se agregan sin valor predeterminado.
   ========================================================================== */

ALTER TABLE dbo.autorizaciones_devolucion ADD fecha_solicitud DATE NOT NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD medio_solicitud NVARCHAR(100) NOT NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD entidad_bancaria NVARCHAR(100) NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD evidencia_titularidad NVARCHAR(300) NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD motivo_anulacion NVARCHAR(500) NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD fecha_anulacion DATETIMEOFFSET(3) NULL;
ALTER TABLE dbo.autorizaciones_devolucion ADD usuario_anulacion_id INT NULL;
GO
ALTER TABLE dbo.autorizaciones_devolucion ADD CONSTRAINT CK_autorizaciones_devolucion_titularidad CHECK (destino = 'MedioOriginal' OR (entidad_bancaria IS NOT NULL AND evidencia_titularidad IS NOT NULL));
ALTER TABLE dbo.autorizaciones_devolucion ADD CONSTRAINT CK_autorizaciones_devolucion_anulacion CHECK (estado <> 'Anulada' OR (motivo_anulacion IS NOT NULL AND fecha_anulacion IS NOT NULL AND usuario_anulacion_id IS NOT NULL));
ALTER TABLE dbo.autorizaciones_devolucion ADD CONSTRAINT FK_autorizaciones_devolucion_usuario_anulacion_id FOREIGN KEY (usuario_anulacion_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_autorizaciones_devolucion_usuario_anulacion_id ON dbo.autorizaciones_devolucion (usuario_anulacion_id);
GO

ALTER TABLE dbo.ejecuciones_devolucion ADD importe DECIMAL(19,2) NOT NULL;
ALTER TABLE dbo.ejecuciones_devolucion ADD cbu_destino CHAR(22) NULL;
ALTER TABLE dbo.ejecuciones_devolucion ADD fecha_transferencia DATE NULL;
ALTER TABLE dbo.ejecuciones_devolucion ADD detalle NVARCHAR(500) NULL;
GO
