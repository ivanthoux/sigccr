/* SIG-CCR - Reversión de la migración 37: se quita el permiso DELETE sobre los pre-registros. */
REVOKE DELETE ON dbo.pre_registros FROM rol_sigccr_app;
GO
