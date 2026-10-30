/* ==========================================================================
   Domicilio con provincia y localidad (D-18)
   - Catálogos de provincias (código INDEC) y localidades (georef) en servicios comunes.
   - clientes.localidad_id y clientes.codigo_postal; domicilio conserva calle y número.
   ========================================================================== */

CREATE TABLE dbo.provincias (
    id INT NOT NULL,
    nombre NVARCHAR(80) NOT NULL,
    CONSTRAINT PK_provincias PRIMARY KEY (id),
    CONSTRAINT UQ_provincias_nombre UNIQUE (nombre)
);
GO
CREATE TABLE dbo.localidades (
    id INT IDENTITY(1,1) NOT NULL,
    provincia_id INT NOT NULL,
    nombre NVARCHAR(150) NOT NULL,
    departamento NVARCHAR(100) NULL,
    codigo VARCHAR(12) NULL,
    activa BIT NOT NULL CONSTRAINT DF_localidades_activa DEFAULT (1),
    CONSTRAINT PK_localidades PRIMARY KEY (id),
    CONSTRAINT UQ_localidades_nombre UNIQUE (provincia_id, nombre, departamento)
);
GO
ALTER TABLE dbo.localidades ADD CONSTRAINT FK_localidades_provincia_id FOREIGN KEY (provincia_id) REFERENCES dbo.provincias (id);
CREATE UNIQUE INDEX UQ_localidades_codigo ON dbo.localidades (codigo) WHERE codigo IS NOT NULL;
GO
ALTER TABLE dbo.clientes ADD localidad_id INT NULL, codigo_postal VARCHAR(8) NULL;
GO
ALTER TABLE dbo.clientes ADD CONSTRAINT FK_clientes_localidad_id FOREIGN KEY (localidad_id) REFERENCES dbo.localidades (id);
CREATE INDEX IX_clientes_localidad_id ON dbo.clientes (localidad_id);
GO
