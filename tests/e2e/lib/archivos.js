// Archivos de prueba generados en memoria (equivalentes a la carpeta «Datos de prueba» del Anexo B).
const pdf = (texto) => Buffer.from(
  `%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n`
  + `3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 100]>>endobj\n% DOCUMENTO DE PRUEBA - SIN VALIDEZ: ${texto}\ntrailer<</Root 1 0 R>>\n%%EOF\n`,
);

/** PDF válido y liviano. */
const documento = (nombre) => ({ name: nombre, mimeType: 'application/pdf', buffer: pdf(nombre) });

/** PDF de más de 5 MB (RN-11). */
const grande = () => {
  const base = pdf('archivo grande');
  return { name: 'archivo_grande_mas_de_5MB.pdf', mimeType: 'application/pdf', buffer: Buffer.concat([base, Buffer.alloc(5.2 * 1024 * 1024, 0x20)]) };
};

/** Tipo de archivo no admitido. */
const noAdmitido = () => ({ name: 'archivo_no_admitido.txt', mimeType: 'text/plain', buffer: Buffer.from('Archivo de texto de prueba') });

module.exports = { documento, grande, noAdmitido };
