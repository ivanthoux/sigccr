/* Revierte 24_permisos_borrado_subir.sql. */
REVOKE DELETE ON dbo.documentacion_sugerida TO rol_sigccr_app;
REVOKE DELETE ON dbo.ordenes_pago_cuotas TO rol_sigccr_app;
REVOKE DELETE ON dbo.ordenes_pago_electronico TO rol_sigccr_app;
REVOKE DELETE ON dbo.tramos_punitorios TO rol_sigccr_app;
GO
