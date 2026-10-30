/* ==========================================================================
   Alineación del esquema con la especificación del modelo de datos (docs/modelo-datos/spec.py)
   - alertas_vistas.usuario_id pasa a ser una referencia validada por el servicio, como alertas.usuario_destino_id:
     una clave foránea hacia usuarios_internos haría depender SS-10 de SS-01 (3.7.1, D-06).
   - Índices de soporte de las claves foráneas agregadas por la migración 10 (4.3.4).
   ========================================================================== */

ALTER TABLE dbo.alertas_vistas DROP CONSTRAINT FK_alertas_vistas_usuario_id;
GO
CREATE INDEX IX_movimientos_caja_cobro_id ON dbo.movimientos_caja (cobro_id);
CREATE INDEX IX_simulaciones_comparativas_usuario_id ON dbo.simulaciones_comparativas (usuario_id);
CREATE INDEX IX_simulaciones_comparativas_version_parametros_id ON dbo.simulaciones_comparativas (version_parametros_id);
GO
