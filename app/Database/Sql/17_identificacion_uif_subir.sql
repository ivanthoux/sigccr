/* ==========================================================================
   Identificación del cliente para la prevención del lavado de activos (D-31, RN-80, Resolución UIF 200/2024)
   - personas_humanas.nacionalidad.
   - clientes.persona_expuesta: declaración jurada sobre la condición de persona expuesta políticamente, con su
     fecha. NULL indica un cliente registrado antes de esta regla que todavía no la declaró.
   ========================================================================== */

ALTER TABLE dbo.personas_humanas ADD nacionalidad NVARCHAR(60) NULL;
GO
ALTER TABLE dbo.clientes ADD persona_expuesta BIT NULL, fecha_declaracion_pep DATETIMEOFFSET(3) NULL;
GO
ALTER TABLE dbo.clientes ADD CONSTRAINT CK_clientes_declaracion_pep CHECK ((persona_expuesta IS NULL AND fecha_declaracion_pep IS NULL) OR (persona_expuesta IS NOT NULL AND fecha_declaracion_pep IS NOT NULL));
GO
