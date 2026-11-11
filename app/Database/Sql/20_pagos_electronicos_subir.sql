/* ==========================================================================
   Pagos electrónicos (CU-34, CU-38): enlace de pago que devolvió la pasarela, para volver a compartirlo con el
   cliente sin generar una orden duplicada (CU-34 A7).
   ========================================================================== */

ALTER TABLE dbo.ordenes_pago_electronico ADD url_pago NVARCHAR(500) NULL;
GO
