/* ==========================================================================
   SIG-CCR - Migración 30: el origen de las copias admite PreviaRestauracion (CU-63 paso 7).
   La migración 29 agregó el valor a la restricción, pero la columna seguía en VARCHAR(10) y la copia previa a una
   restauración no se podía registrar. Se lleva a VARCHAR(20), como en el modelo de datos.
   ========================================================================== */
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_origen;
GO
ALTER TABLE dbo.copias_seguridad ALTER COLUMN origen VARCHAR(20) NOT NULL;
GO
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_origen CHECK (origen IN ('Manual', 'Automatica', 'PreviaRestauracion'));
