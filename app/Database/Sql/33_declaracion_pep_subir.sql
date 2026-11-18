/* ==========================================================================
   SIG-CCR - Migración 33: declaración jurada de persona expuesta políticamente (D-55; RN-80, Resolución UIF 35/2023).
   El cliente puede darse de alta sin ella; se exige en el legajo para liquidar un crédito (CU-27).
   ========================================================================== */
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_documentacion WHERE codigo = 'DDJJ_PEP')
    INSERT INTO dbo.tipos_documentacion (codigo, nombre, aplica_a, es_clave, activo) VALUES ('DDJJ_PEP', N'Declaración jurada de persona expuesta políticamente', 'Ambos', 0, 1);
GO
