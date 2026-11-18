/* SIG-CCR - Reversión de la migración 31 (los caracteres fuera de Latin-1 se convierten en «?»). */
ALTER TABLE dbo.movimientos_fondos ALTER COLUMN referencia VARCHAR(100) NULL;
