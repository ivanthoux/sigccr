/* ==========================================================================
   Cancelación anticipada total (D-38, RN-67, CU-97): componentes del importe cobrado y reducción de lo no
   devengado, para el comprobante y la auditoría.
   ========================================================================== */

-- cancelaciones_anticipadas: Cancelación anticipada total de un crédito (RN-67, CU-97, D-38).
CREATE TABLE dbo.cancelaciones_anticipadas (
    id INT IDENTITY(1,1) NOT NULL,
    credito_id INT NOT NULL,
    cobro_id BIGINT NOT NULL,
    fecha_pago DATE NOT NULL,
    vencidas DECIMAL(19,2) NOT NULL,
    capital_no_vencido DECIMAL(19,2) NOT NULL,
    no_capitalizable_no_vencido DECIMAL(19,2) NOT NULL CONSTRAINT DF_cancelaciones_anticipadas_no_capitalizable_no_vencido DEFAULT (0),
    interes_en_curso DECIMAL(19,2) NOT NULL,
    iva_interes_en_curso DECIMAL(19,2) NOT NULL,
    dias_en_curso SMALLINT NOT NULL,
    cuota_en_curso SMALLINT NULL,
    interes_no_devengado DECIMAL(19,2) NOT NULL,
    gastos_no_devengados DECIMAL(19,2) NOT NULL,
    iva_no_devengado DECIMAL(19,2) NOT NULL,
    total DECIMAL(19,2) NOT NULL,
    CONSTRAINT PK_cancelaciones_anticipadas PRIMARY KEY (id),
    CONSTRAINT UQ_cancelaciones_anticipadas_credito_id UNIQUE (credito_id),
    CONSTRAINT UQ_cancelaciones_anticipadas_cobro_id UNIQUE (cobro_id),
    CONSTRAINT CK_cancelaciones_importes CHECK (total > 0 AND vencidas >= 0 AND capital_no_vencido >= 0 AND no_capitalizable_no_vencido >= 0 AND interes_en_curso >= 0 AND iva_interes_en_curso >= 0 AND dias_en_curso >= 0 AND interes_no_devengado >= 0 AND gastos_no_devengados >= 0 AND iva_no_devengado >= 0)
);
GO
ALTER TABLE dbo.cancelaciones_anticipadas ADD CONSTRAINT FK_cancelaciones_anticipadas_credito_id FOREIGN KEY (credito_id) REFERENCES dbo.creditos (id);
ALTER TABLE dbo.cancelaciones_anticipadas ADD CONSTRAINT FK_cancelaciones_anticipadas_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
GO
