/* ==========================================================================
   SIG-CCR - Migración 31: la referencia de un movimiento de fondos admite cualquier carácter (CU-85, H-17).
   La columna era VARCHAR(100) con la intercalación Latin-1: un carácter fuera de ella (por ejemplo, «ł», «ș» o un
   emoji) se guardaba como «?». Se lleva a NVARCHAR(100), como el concepto.
   ========================================================================== */
ALTER TABLE dbo.movimientos_fondos ALTER COLUMN referencia NVARCHAR(100) NULL;
