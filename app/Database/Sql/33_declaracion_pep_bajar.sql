/* SIG-CCR - Reversión de la migración 33: el tipo se quita solo si ningún legajo ni ninguna línea lo usa (si no, se desactiva). */
IF EXISTS (SELECT 1 FROM dbo.documentos_legajo d JOIN dbo.tipos_documentacion t ON t.id = d.tipo_documentacion_id WHERE t.codigo = 'DDJJ_PEP')
   OR EXISTS (SELECT 1 FROM dbo.requisitos_documentales r JOIN dbo.tipos_documentacion t ON t.id = r.tipo_documentacion_id WHERE t.codigo = 'DDJJ_PEP')
    UPDATE dbo.tipos_documentacion SET activo = 0 WHERE codigo = 'DDJJ_PEP';
ELSE
BEGIN
    DELETE s FROM dbo.documentacion_sugerida s JOIN dbo.tipos_documentacion t ON t.id = s.tipo_documentacion_id WHERE t.codigo = 'DDJJ_PEP';
    DELETE FROM dbo.tipos_documentacion WHERE codigo = 'DDJJ_PEP';
END
GO
