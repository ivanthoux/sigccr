/* Revierte 19_revocacion_subir.sql. Falla si existen créditos revocados, cuotas sin efecto o cobros de restitución. */
DROP TABLE dbo.revocaciones;
GO
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_tipo;
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_tipo CHECK (tipo IN ('Ordinario', 'CancelacionAnticipada'));
ALTER TABLE dbo.cuotas DROP CONSTRAINT CK_cuotas_estado;
ALTER TABLE dbo.cuotas ADD CONSTRAINT CK_cuotas_estado CHECK (estado IN ('Pendiente', 'Vencida', 'Pagada', 'Sustituida'));
ALTER TABLE dbo.creditos DROP CONSTRAINT CK_creditos_estado;
ALTER TABLE dbo.creditos ADD CONSTRAINT CK_creditos_estado CHECK (estado IN ('Activo', 'EnMora', 'Cancelado', 'Refinanciado'));
GO
