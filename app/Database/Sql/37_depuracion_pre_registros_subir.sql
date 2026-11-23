/* SIG-CCR - Migración 37: permiso DELETE del rol de la aplicación sobre los pre-registros (D-64; Ley 25.326, art. 4, inc. 7).
   El control de vencimientos (CU-89) elimina los pre-registros que no se completaron pasados 90 días. Las pruebas unitarias
   no lo detectan porque no usan SQL Server: docs/modelo-datos/verificar.py controla que cada tabla que la aplicación borra
   tenga este permiso. */
GRANT DELETE ON dbo.pre_registros TO rol_sigccr_app;
GO
