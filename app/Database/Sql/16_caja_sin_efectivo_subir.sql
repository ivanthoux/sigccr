/* ==========================================================================
   Caja y cobros sin efectivo (D-30, RN-77)
   - La jornada se abre sin fondo inicial y se cierra conciliando, por medio de pago, lo registrado con lo
     acreditado en las cuentas de la entidad (RN-28, RN-32): conciliaciones_jornada.
   - No se registran efectivo, cambio, fondo inicial ni desembolsos por caja. Las restricciones se crean WITH
     NOCHECK: los registros anteriores se conservan (RN-44) y la regla rige para los nuevos.
   ========================================================================== */

ALTER TABLE dbo.jornadas_caja DROP CONSTRAINT CK_jornadas_caja_cierre;
GO
ALTER TABLE dbo.jornadas_caja ADD CONSTRAINT CK_jornadas_caja_cierre CHECK ((estado = 'Abierta' AND cierre IS NULL) OR (estado = 'Cerrada' AND cierre IS NOT NULL));
ALTER TABLE dbo.jornadas_caja ADD CONSTRAINT DF_jornadas_caja_fondo_inicial DEFAULT (0) FOR fondo_inicial;
ALTER TABLE dbo.jornadas_caja WITH NOCHECK ADD CONSTRAINT CK_jornadas_caja_sin_efectivo CHECK (fondo_inicial = 0 AND efectivo_declarado IS NULL);
ALTER TABLE dbo.cobros WITH NOCHECK ADD CONSTRAINT CK_cobros_sin_cambio CHECK (cambio = 0);
ALTER TABLE dbo.cobros_medios WITH NOCHECK ADD CONSTRAINT CK_cobros_medios_sin_efectivo CHECK (medio <> 'Efectivo');
ALTER TABLE dbo.movimientos_caja WITH NOCHECK ADD CONSTRAINT CK_movimientos_caja_solo_cobros CHECK (tipo = 'Cobro');
ALTER TABLE dbo.liquidaciones WITH NOCHECK ADD CONSTRAINT CK_liquidaciones_transferencia CHECK (medio_desembolso = 'Transferencia');
GO

CREATE TABLE dbo.conciliaciones_jornada (
    id INT IDENTITY(1,1) NOT NULL,
    jornada_caja_id INT NOT NULL,
    medio VARCHAR(20) NOT NULL,
    total_registrado DECIMAL(19,2) NOT NULL,
    total_acreditado DECIMAL(19,2) NOT NULL,
    diferencia DECIMAL(19,2) NOT NULL,
    explicacion NVARCHAR(500) NULL,
    CONSTRAINT PK_conciliaciones_jornada PRIMARY KEY (id),
    CONSTRAINT UQ_conciliaciones_jornada_medio UNIQUE (jornada_caja_id, medio),
    CONSTRAINT CK_conciliaciones_jornada_medio CHECK (medio IN ('TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico')),
    CONSTRAINT CK_conciliaciones_jornada_totales CHECK (total_registrado >= 0 AND total_acreditado >= 0 AND diferencia = total_acreditado - total_registrado),
    CONSTRAINT CK_conciliaciones_jornada_explicacion CHECK (diferencia = 0 OR explicacion IS NOT NULL)
);
GO
ALTER TABLE dbo.conciliaciones_jornada ADD CONSTRAINT FK_conciliaciones_jornada_jornada_caja_id FOREIGN KEY (jornada_caja_id) REFERENCES dbo.jornadas_caja (id);
GO
