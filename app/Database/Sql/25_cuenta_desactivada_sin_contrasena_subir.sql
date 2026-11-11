/* SIG-CCR - Migración 25: cuentas desactivadas sin contraseña.
   CK_cuentas_acceso_contrasena exigía contraseña salvo en PendienteActivacion, pero CU-08 y CU-13 desactivan también
   cuentas que todavía no definieron su contraseña (un usuario que no se activó, un cliente que no usó el portal) y
   CU-09 ya contempla reactivarlas a PendienteActivacion. La desactivación fallaba con un error de SQL Server; lo
   encontraron las pruebas de punta a punta (R-026). */
ALTER TABLE dbo.cuentas_acceso DROP CONSTRAINT CK_cuentas_acceso_contrasena;
GO
ALTER TABLE dbo.cuentas_acceso ADD CONSTRAINT CK_cuentas_acceso_contrasena CHECK (estado IN ('PendienteActivacion', 'Desactivada') OR hash_contrasena IS NOT NULL);
GO
