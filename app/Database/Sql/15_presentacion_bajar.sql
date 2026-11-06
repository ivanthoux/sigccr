/* Revierte 15_presentacion_subir.sql. Las versiones porcentuales registradas impiden volver al esquema anterior. */
ALTER TABLE dbo.clientes DROP CONSTRAINT CK_clientes_monotributo;
ALTER TABLE dbo.clientes DROP CONSTRAINT CK_clientes_categoria_monotributo;
GO
ALTER TABLE dbo.clientes DROP COLUMN categoria_monotributo;
GO
DROP TABLE dbo.categorias_monotributo;
DROP TABLE dbo.documentacion_sugerida;
GO
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_gasto_administrativo;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_tipo_gasto_administrativo;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT DF_versiones_condiciones_tipo_gasto_administrativo;
GO
ALTER TABLE dbo.versiones_condiciones DROP COLUMN tipo_gasto_administrativo, gasto_administrativo_pct;
GO
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_montos;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_cuotas;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_edades;
GO
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT CK_versiones_condiciones_montos CHECK (monto_minimo > 0 AND monto_minimo <= monto_maximo);
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT CK_versiones_condiciones_cuotas CHECK (cuotas_minimas >= 1 AND cuotas_minimas <= cuotas_maximas);
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT CK_versiones_condiciones_edades CHECK (edad_minima IS NULL OR edad_maxima IS NULL OR edad_minima <= edad_maxima);
GO
