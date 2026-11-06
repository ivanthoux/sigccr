/* Revierte 16_caja_sin_efectivo_subir.sql. */
DROP TABLE dbo.conciliaciones_jornada;
GO
ALTER TABLE dbo.liquidaciones DROP CONSTRAINT CK_liquidaciones_transferencia;
ALTER TABLE dbo.movimientos_caja DROP CONSTRAINT CK_movimientos_caja_solo_cobros;
ALTER TABLE dbo.cobros_medios DROP CONSTRAINT CK_cobros_medios_sin_efectivo;
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_sin_cambio;
ALTER TABLE dbo.jornadas_caja DROP CONSTRAINT CK_jornadas_caja_sin_efectivo;
ALTER TABLE dbo.jornadas_caja DROP CONSTRAINT DF_jornadas_caja_fondo_inicial;
ALTER TABLE dbo.jornadas_caja DROP CONSTRAINT CK_jornadas_caja_cierre;
GO
ALTER TABLE dbo.jornadas_caja WITH NOCHECK ADD CONSTRAINT CK_jornadas_caja_cierre CHECK ((estado = 'Abierta' AND cierre IS NULL) OR (estado = 'Cerrada' AND cierre IS NOT NULL AND efectivo_declarado IS NOT NULL AND diferencia IS NOT NULL));
GO
