/* ==========================================================================
   SIG-CCR - Migración 35 (pruebas de integración, iteración 4, bloque A).
   H-29: el responsable de la auditoría era NVARCHAR(150), pero las operaciones sin sesión del portal (pre-registro,
   verificación del correo) registran como responsable el correo de la persona, que admite 254 caracteres: un correo
   de más de 150 respondía error 500. Se lleva a NVARCHAR(254).
   ========================================================================== */
ALTER TABLE dbo.registros_auditoria ALTER COLUMN responsable NVARCHAR(254) NOT NULL;
