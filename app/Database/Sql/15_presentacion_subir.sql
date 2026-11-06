/* ==========================================================================
   Observaciones de la presentación del 02/11/2026 (D-23, D-25, D-26, D-28)
   - versiones_condiciones: rangos estrictos (el máximo mayor que el mínimo) y gasto administrativo fijo o
     porcentual. Las restricciones de rango se crean WITH NOCHECK: las versiones ya registradas no se modifican
     (RN-15) y la regla rige para las nuevas.
   - documentacion_sugerida: documentación base por categoría de ingresos, configurable (D-25).
   - categorias_monotributo y clientes.categoria_monotributo (D-26).
   ========================================================================== */

ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_montos;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_cuotas;
ALTER TABLE dbo.versiones_condiciones DROP CONSTRAINT CK_versiones_condiciones_edades;
GO
ALTER TABLE dbo.versiones_condiciones WITH NOCHECK ADD CONSTRAINT CK_versiones_condiciones_montos CHECK (monto_minimo > 0 AND monto_minimo < monto_maximo);
ALTER TABLE dbo.versiones_condiciones WITH NOCHECK ADD CONSTRAINT CK_versiones_condiciones_cuotas CHECK (cuotas_minimas >= 1 AND cuotas_minimas < cuotas_maximas);
ALTER TABLE dbo.versiones_condiciones WITH NOCHECK ADD CONSTRAINT CK_versiones_condiciones_edades CHECK (edad_minima IS NULL OR edad_maxima IS NULL OR edad_minima < edad_maxima);
GO
ALTER TABLE dbo.versiones_condiciones ADD tipo_gasto_administrativo VARCHAR(10) NOT NULL CONSTRAINT DF_versiones_condiciones_tipo_gasto_administrativo DEFAULT ('Fijo'), gasto_administrativo_pct DECIMAL(18,12) NULL;
GO
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT CK_versiones_condiciones_tipo_gasto_administrativo CHECK (tipo_gasto_administrativo IN ('Fijo', 'Porcentual'));
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT CK_versiones_condiciones_gasto_administrativo CHECK ((tipo_gasto_administrativo = 'Fijo' AND gasto_administrativo_pct IS NULL) OR (tipo_gasto_administrativo = 'Porcentual' AND gasto_administrativo_pct > 0 AND gasto_administrativo_pct <= 0.1 AND gasto_administrativo_cuota = 0));
GO

CREATE TABLE dbo.documentacion_sugerida (
    categoria_ingresos VARCHAR(20) NOT NULL,
    tipo_documentacion_id INT NOT NULL,
    CONSTRAINT PK_documentacion_sugerida PRIMARY KEY (categoria_ingresos, tipo_documentacion_id),
    CONSTRAINT CK_documentacion_sugerida_categoria_ingresos CHECK (categoria_ingresos IN ('RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto', 'Empresa'))
);
GO
ALTER TABLE dbo.documentacion_sugerida ADD CONSTRAINT FK_documentacion_sugerida_tipo_documentacion_id FOREIGN KEY (tipo_documentacion_id) REFERENCES dbo.tipos_documentacion (id);
CREATE INDEX IX_documentacion_sugerida_tipo_documentacion_id ON dbo.documentacion_sugerida (tipo_documentacion_id);
GO
-- Documentación base que hasta D-25 estaba fija en el código (RequisitosDocumentales::SUGERIDOS_POR_CATEGORIA)
INSERT INTO dbo.documentacion_sugerida (categoria_ingresos, tipo_documentacion_id)
SELECT s.categoria, t.id FROM (VALUES
    ('RelacionDependencia', 'DOC_IDENTIDAD'), ('RelacionDependencia', 'RECIBO_SUELDO'),
    ('JubiladoPensionado', 'DOC_IDENTIDAD'), ('JubiladoPensionado', 'RECIBO_HABERES'),
    ('Monotributista', 'DOC_IDENTIDAD'), ('Monotributista', 'CONST_MONOTRIBUTO'), ('Monotributista', 'PAGOS_MONOTRIBUTO'),
    ('ResponsableInscripto', 'DOC_IDENTIDAD'), ('ResponsableInscripto', 'CONST_INSCRIPCION'), ('ResponsableInscripto', 'DDJJ_GANANCIAS'),
    ('Empresa', 'ESTATUTO'), ('Empresa', 'CONST_CUIT'), ('Empresa', 'ESTADOS_CONTABLES'), ('Empresa', 'PODER_REPRESENTACION'), ('Empresa', 'DOC_IDENTIDAD')
) AS s (categoria, codigo) JOIN dbo.tipos_documentacion t ON t.codigo = s.codigo;
GO

CREATE TABLE dbo.categorias_monotributo (
    id INT IDENTITY(1,1) NOT NULL,
    categoria CHAR(1) NOT NULL,
    ingresos_brutos_anuales DECIMAL(19,2) NOT NULL,
    vigente_desde DATE NOT NULL,
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_categorias_monotributo_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_categorias_monotributo PRIMARY KEY (id),
    CONSTRAINT UQ_categorias_monotributo_vigencia UNIQUE (categoria, vigente_desde),
    CONSTRAINT CK_categorias_monotributo_categoria CHECK (categoria IN ('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K')),
    CONSTRAINT CK_categorias_monotributo_tope CHECK (ingresos_brutos_anuales > 0)
);
GO
-- Topes de ingresos brutos anuales vigentes desde el 01/08/2026 (ARCA, tabla de categorías del monotributo)
INSERT INTO dbo.categorias_monotributo (categoria, ingresos_brutos_anuales, vigente_desde) VALUES
    ('A', 12009410.45, '2026-08-01'), ('B', 17595182.74, '2026-08-01'), ('C', 24670494.31, '2026-08-01'),
    ('D', 30628651.43, '2026-08-01'), ('E', 36028231.33, '2026-08-01'), ('F', 45151659.41, '2026-08-01'),
    ('G', 53995798.87, '2026-08-01'), ('H', 81924660.37, '2026-08-01'), ('I', 91699761.90, '2026-08-01'),
    ('J', 105012519.20, '2026-08-01'), ('K', 126610838.75, '2026-08-01');
GO
ALTER TABLE dbo.clientes ADD categoria_monotributo CHAR(1) NULL;
GO
ALTER TABLE dbo.clientes ADD CONSTRAINT CK_clientes_categoria_monotributo CHECK (categoria_monotributo IS NULL OR categoria_monotributo IN ('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'));
ALTER TABLE dbo.clientes ADD CONSTRAINT CK_clientes_monotributo CHECK (categoria_monotributo IS NULL OR categoria_ingresos = 'Monotributista');
GO
