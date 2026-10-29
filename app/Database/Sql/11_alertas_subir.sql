/* CU-83 Consultar Alertas: registro de las alertas vistas por cada usuario (postcondición) y del responsable de su
   resolución (A2). Las alertas se resuelven al resolverse la situación que las originó (RF-11). */

-- alertas_vistas: primera vez que cada usuario interno vio el detalle de una alerta.
CREATE TABLE dbo.alertas_vistas (
    alerta_id BIGINT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_alertas_vistas_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_alertas_vistas PRIMARY KEY (alerta_id, usuario_id)
);
GO
ALTER TABLE dbo.alertas_vistas ADD CONSTRAINT FK_alertas_vistas_alerta_id FOREIGN KEY (alerta_id) REFERENCES dbo.alertas (id);
ALTER TABLE dbo.alertas_vistas ADD CONSTRAINT FK_alertas_vistas_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_alertas_vistas_usuario_id ON dbo.alertas_vistas (usuario_id);
GO

-- Responsable de la resolución: un usuario (nombre) o un proceso automático (su nombre de comando).
ALTER TABLE dbo.alertas ADD resuelta_por NVARCHAR(150) NULL;
GO
ALTER TABLE dbo.alertas ADD CONSTRAINT CK_alertas_resolucion CHECK ((estado = 'Pendiente' AND fecha_resolucion IS NULL) OR (estado = 'Resuelta' AND fecha_resolucion IS NOT NULL));
CREATE INDEX IX_alertas_objeto ON dbo.alertas (objeto_tipo, objeto_id) WHERE estado = 'Pendiente';
GO
