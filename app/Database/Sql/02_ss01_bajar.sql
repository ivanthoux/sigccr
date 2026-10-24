ALTER TABLE dbo.autorizaciones_temporales DROP CONSTRAINT FK_autorizaciones_temporales_cuenta_acceso_id;
ALTER TABLE dbo.cuentas_cliente DROP CONSTRAINT FK_cuentas_cliente_cuenta;
ALTER TABLE dbo.usuarios_internos DROP CONSTRAINT FK_usuarios_internos_rol_id;
ALTER TABLE dbo.usuarios_internos DROP CONSTRAINT FK_usuarios_internos_cuenta;
ALTER TABLE dbo.roles_permisos DROP CONSTRAINT FK_roles_permisos_rol_id;
ALTER TABLE dbo.roles_permisos DROP CONSTRAINT FK_roles_permisos_permiso_id;
GO
DROP TABLE dbo.autorizaciones_temporales;
DROP TABLE dbo.cuentas_cliente;
DROP TABLE dbo.usuarios_internos;
DROP TABLE dbo.cuentas_acceso;
DROP TABLE dbo.roles_permisos;
DROP TABLE dbo.permisos;
DROP TABLE dbo.roles;
