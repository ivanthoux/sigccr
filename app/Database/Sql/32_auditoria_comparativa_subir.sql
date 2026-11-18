/* ==========================================================================
   SIG-CCR - Migración 32 (pruebas de integración, iteración 2, bloque B).
   H-22: la posición de una línea en la simulación comparativa era TINYINT: con más de 255 líneas viables la
   comparación (CU-99) respondía error 500. Se lleva a INT.
   H-23: el motivo de la auditoría era NVARCHAR(1000), pero la justificación de una evaluación de contingencia
   (CU-25) y el fundamento de una resolución (CU-26) admiten 2000 caracteres y se registran como motivo: un texto
   de más de 1000 respondía error 500. Se lleva a NVARCHAR(2000).
   ========================================================================== */
ALTER TABLE dbo.simulaciones DROP CONSTRAINT CK_simulaciones_comparativa;
GO
ALTER TABLE dbo.simulaciones ALTER COLUMN posicion INT NULL;
GO
ALTER TABLE dbo.simulaciones ADD CONSTRAINT CK_simulaciones_comparativa CHECK ((simulacion_comparativa_id IS NULL AND posicion IS NULL) OR (simulacion_comparativa_id IS NOT NULL AND posicion >= 1));
GO
ALTER TABLE dbo.registros_auditoria ALTER COLUMN motivo NVARCHAR(2000) NULL;
