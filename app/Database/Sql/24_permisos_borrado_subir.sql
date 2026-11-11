/* SIG-CCR - Migración 24: permiso DELETE del rol de la aplicación (4.3.5).
   El rol rol_sigccr_app tiene SELECT, INSERT y UPDATE sobre el esquema y DELETE solo donde la aplicación borra filas.
   Faltaba en cuatro tablas que se borran desde la iteración 2 y 3; las pruebas unitarias no lo detectan porque no
   usan SQL Server, y lo encontraron las pruebas de punta a punta:
   - documentacion_sugerida: CU-100 reemplaza la documentación propuesta de cada categoría (D-25).
   - ordenes_pago_cuotas y ordenes_pago_electronico: CU-34 descarta la orden que la pasarela no llegó a crear.
   - tramos_punitorios: CU-90 quita los tramos posteriores a la fecha efectiva de un pago electrónico (RN-55).
   docs/modelo-datos/verificar.py controla que cada tabla que la aplicación borra tenga este permiso. */
GRANT DELETE ON dbo.documentacion_sugerida TO rol_sigccr_app;
GRANT DELETE ON dbo.ordenes_pago_cuotas TO rol_sigccr_app;
GRANT DELETE ON dbo.ordenes_pago_electronico TO rol_sigccr_app;
GRANT DELETE ON dbo.tramos_punitorios TO rol_sigccr_app;
GO
