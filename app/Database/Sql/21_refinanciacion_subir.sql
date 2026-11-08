/* ==========================================================================
   Refinanciación y condonación (D-36, RN-36 a RN-38, RN-70; CU-39 a CU-44, CU-74, CU-96)
   - simulaciones_refinanciacion: fecha de corte, capital, concepto no capitalizable y versión de parámetros; sus
     cuotas en simulaciones_refinanciacion_cuotas, para verificar al registrar la propuesta que la deuda no varió.
   - propuestas_cuotas.punitorio: punitorios exigibles a la fecha de corte; los posteriores se incorporan al aplicar.
   - propuestas_refinanciacion: tipo, fecha y responsable de la cancelación y constancia del desistimiento.
   - aceptaciones_refinanciacion: fecha de la constancia y punitorios incorporados al aplicar.
   - condonaciones: cliente; condonaciones_cuotas.iva: IVA del punitorio condonado.
   Las tablas alcanzadas no tienen filas hasta esta iteración: las columnas obligatorias se agregan sin valor
   predeterminado.
   ========================================================================== */

ALTER TABLE dbo.simulaciones_refinanciacion ADD fecha_corte DATE NOT NULL;
ALTER TABLE dbo.simulaciones_refinanciacion ADD capital DECIMAL(19,2) NOT NULL;
ALTER TABLE dbo.simulaciones_refinanciacion ADD no_capitalizable DECIMAL(19,2) NOT NULL;
ALTER TABLE dbo.simulaciones_refinanciacion ADD version_parametros_id INT NOT NULL;
GO
ALTER TABLE dbo.simulaciones_refinanciacion ADD CONSTRAINT FK_simulaciones_refinanciacion_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
CREATE INDEX IX_simulaciones_refinanciacion_version_parametros_id ON dbo.simulaciones_refinanciacion (version_parametros_id);
GO

-- simulaciones_refinanciacion_cuotas: Cuotas comprendidas en una simulación y su composición, para verificar al registrar la propuesta que la deuda no varió (CU-40 A2, D-36).
CREATE TABLE dbo.simulaciones_refinanciacion_cuotas (
    simulacion_id INT NOT NULL,
    cuota_id BIGINT NOT NULL,
    capital_pendiente DECIMAL(19,2) NOT NULL,
    no_capitalizable DECIMAL(19,2) NOT NULL,
    punitorio DECIMAL(19,2) NOT NULL,
    CONSTRAINT PK_simulaciones_refinanciacion_cuotas PRIMARY KEY (simulacion_id, cuota_id)
);
GO
ALTER TABLE dbo.simulaciones_refinanciacion_cuotas ADD CONSTRAINT FK_simulaciones_refinanciacion_cuotas_simulacion_id FOREIGN KEY (simulacion_id) REFERENCES dbo.simulaciones_refinanciacion (id);
ALTER TABLE dbo.simulaciones_refinanciacion_cuotas ADD CONSTRAINT FK_simulaciones_refinanciacion_cuotas_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
CREATE INDEX IX_simulaciones_refinanciacion_cuotas_cuota_id ON dbo.simulaciones_refinanciacion_cuotas (cuota_id);
GO

ALTER TABLE dbo.propuestas_cuotas ADD punitorio DECIMAL(19,2) NOT NULL CONSTRAINT DF_propuestas_cuotas_punitorio DEFAULT (0);
GO

ALTER TABLE dbo.propuestas_refinanciacion ADD tipo_cancelacion VARCHAR(15) NULL;
ALTER TABLE dbo.propuestas_refinanciacion ADD fecha_cancelacion DATETIMEOFFSET(3) NULL;
ALTER TABLE dbo.propuestas_refinanciacion ADD usuario_cancelacion_id INT NULL;
ALTER TABLE dbo.propuestas_refinanciacion ADD fecha_desistimiento DATE NULL;
ALTER TABLE dbo.propuestas_refinanciacion ADD medio_desistimiento NVARCHAR(100) NULL;
GO
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT CK_propuestas_refinanciacion_tipo_cancelacion CHECK (tipo_cancelacion IS NULL OR tipo_cancelacion IN ('Desistimiento', 'Administrativa'));
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT CK_propuestas_datos_cancelacion CHECK (estado <> 'Cancelada' OR (tipo_cancelacion IS NOT NULL AND fecha_cancelacion IS NOT NULL AND usuario_cancelacion_id IS NOT NULL AND (tipo_cancelacion = 'Administrativa' OR (fecha_desistimiento IS NOT NULL AND medio_desistimiento IS NOT NULL))));
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_usuario_cancelacion_id FOREIGN KEY (usuario_cancelacion_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_propuestas_refinanciacion_usuario_cancelacion_id ON dbo.propuestas_refinanciacion (usuario_cancelacion_id);
GO

ALTER TABLE dbo.aceptaciones_refinanciacion ADD fecha_constancia DATE NOT NULL;
ALTER TABLE dbo.aceptaciones_refinanciacion ADD punitorios_incorporados DECIMAL(19,2) NOT NULL CONSTRAINT DF_aceptaciones_refinanciacion_punitorios_incorporados DEFAULT (0);
GO

ALTER TABLE dbo.condonaciones ADD cliente_id INT NOT NULL;
GO
ALTER TABLE dbo.condonaciones ADD CONSTRAINT FK_condonaciones_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
CREATE INDEX IX_condonaciones_cliente_id ON dbo.condonaciones (cliente_id);
ALTER TABLE dbo.condonaciones_cuotas ADD iva DECIMAL(19,4) NOT NULL CONSTRAINT DF_condonaciones_cuotas_iva DEFAULT (0);
GO
ALTER TABLE dbo.condonaciones_cuotas ADD CONSTRAINT CK_condonaciones_cuotas_iva CHECK (iva >= 0);
GO
