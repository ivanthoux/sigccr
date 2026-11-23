/* SIG-CCR - Reversión de la migración 36: se elimina la tabla de solicitudes de derechos del titular. */
DROP TABLE IF EXISTS dbo.solicitudes_derechos;
GO
