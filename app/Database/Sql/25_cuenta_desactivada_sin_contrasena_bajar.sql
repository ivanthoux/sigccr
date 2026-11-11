/* Revierte 25_cuenta_desactivada_sin_contrasena_subir.sql (falla si hay cuentas desactivadas sin contraseña). */
ALTER TABLE dbo.cuentas_acceso DROP CONSTRAINT CK_cuentas_acceso_contrasena;
GO
ALTER TABLE dbo.cuentas_acceso ADD CONSTRAINT CK_cuentas_acceso_contrasena CHECK (estado = 'PendienteActivacion' OR hash_contrasena IS NOT NULL);
GO
