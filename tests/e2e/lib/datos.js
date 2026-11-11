// Datos del Anexo A de la prueba funcional integral (docs/pruebas/prueba_funcional_integral_1_a_3.md).
// El Administrador inicial lo crea tests/e2e/preparar.sh con estos mismos datos.

const usuarios = {
  admin: { rol: 'Administrador', rolNombre: 'Administrador', nombre: 'Iván Ezequiel', apellido: 'Thoux', dni: '20999888', correo: 'admin@prueba.local', clave: 'Administracion-Prueba-2026' },
  gerente: { rol: 'Gerente', rolNombre: 'Gerente', nombre: 'Laura', apellido: 'Pérez', dni: '26111222', correo: 'gerente@prueba.local', clave: 'Gerencia-Prueba-2026' },
  analista: { rol: 'AnalistaCredito', rolNombre: 'Analista de Crédito', nombre: 'Martín', apellido: 'López', dni: '28333444', correo: 'analista@prueba.local', clave: 'Analisis-Prueba-2026' },
  cajero: { rol: 'Cajero', rolNombre: 'Cajero', nombre: 'Paula', apellido: 'Romero', dni: '30555666', correo: 'cajero@prueba.local', clave: 'Caja-Uno-Prueba-2026' },
  cajero2: { rol: 'Cajero', rolNombre: 'Cajero', nombre: 'Tomás', apellido: 'Silva', dni: '32777888', correo: 'cajero2@prueba.local', clave: 'Caja-Dos-Prueba-2026' },
  cajero3: { rol: 'Cajero', rolNombre: 'Cajero', nombre: 'Rocío', apellido: 'Báez', dni: '34999000', correo: 'cajero3@prueba.local', clave: 'Caja-Tres-Prueba-2026' },
};

/** Usuarios que registra el Administrador en R-008, en orden. */
const internos = ['gerente', 'analista', 'cajero', 'cajero2', 'cajero3'];

const contrasenasInvalidas = { corta: 'Corta12345', comprometida: 'password1234567' };

module.exports = { usuarios, internos, contrasenasInvalidas };

// A.2 Clientes persona humana (domicilio común: Misiones, Apóstoles, 3350, «Av. San Martín» con el número).
const h = (codigo, nombre, apellido, dni, cuil, nacimiento, estadoCivil, correo, telefono, numero, categoria, ingresos, ocupacion, empleador, extra = {}) => ({
  codigo, tipo: 'PersonaHumana', nombre, apellido, dni, cuil, nacimiento, estadoCivil, correo, telefono, domicilio: `Av. San Martín ${numero}`,
  categoria, ingresos, ocupacion, empleador, nacionalidad: 'Argentina', pep: false, comunicaciones: true, ...extra,
});
const clientes = {
  C01: h('C-01', 'Ana', 'Gómez', '30111233', '27301112330', '1985-03-14', 'Casado', 'ana.gomez@prueba.local', '3758 41-1001', 101, 'RelacionDependencia', '1500000', 'Docente', 'Escuela Normal N.º 3'),
  C02: h('C-02', 'Bruno', 'Fernández', '31222335', '20312223350', '1986-07-02', 'Soltero', 'bruno.fernandez@prueba.local', '3758 41-1002', 202, 'RelacionDependencia', '1500000', 'Empleado administrativo', 'Cooperativa Agrícola Apóstoles'),
  C03: h('C-03', 'Carla', 'Sosa', '32333445', '27323334450', '1987-11-21', 'Soltero', 'carla.sosa@prueba.local', '3758 41-1003', 303, 'RelacionDependencia', '1500000', 'Enfermera', 'Hospital de Área Apóstoles'),
  C04: h('C-04', 'Diego', 'Ramírez', '33444550', '20334445500', '1988-05-09', 'Casado', 'diego.ramirez@prueba.local', '3758 41-1004', 404, 'RelacionDependencia', '1500000', 'Técnico electricista', 'Energía de Misiones S.A.'),
  C05: h('C-05', 'Elena', 'Benítez', '34555665', '27345556651', '1989-01-30', 'Divorciado', 'elena.benitez@prueba.local', '3758 41-1005', 505, 'JubiladoPensionado', '1500000', 'Jubilada', ''),
  C06: h('C-06', 'Federico', 'Acosta', '35666775', '20356667752', '1990-08-17', 'Soltero', 'federico.acosta@prueba.local', '3758 41-1006', 606, 'RelacionDependencia', '1500000', 'Vendedor', 'Comercial del Sur S.R.L.'),
  C07: h('C-07', 'Gabriela', 'Duarte', '36777893', '27367778933', '1991-12-05', 'Soltero', 'falla.definitiva@prueba.local', '3758 41-1007', 707, 'RelacionDependencia', '1500000', 'Cajera', 'Supermercado Norte'),
  C08: h('C-08', 'Hernán', 'Ojeda', '37888994', '20378889945', '1992-04-23', 'Casado', 'falla.transitoria@prueba.local', '3758 41-1008', 808, 'RelacionDependencia', '1500000', 'Chofer', 'Transporte Apóstoles S.R.L.'),
  C09: h('C-09', 'Inés', 'Villalba', '38999102', '27389991029', '1993-09-11', 'Soltero', 'incierto@prueba.local', '3758 41-1009', 909, 'RelacionDependencia', '1500000', 'Administrativa', 'Municipalidad de Apóstoles'),
  C10: h('C-10', 'Julián', 'Ríos', '29111203', '20291112030', '1982-02-28', 'Casado', 'julian.rios@prueba.local', '3758 41-1010', 1010, 'RelacionDependencia', '1500000', 'Contador', 'Estudio Ríos y Asociados'),
  C11: h('C-11', 'Lucía', 'Martínez', '40111304', '27401113040', '1996-06-19', 'Soltero', 'lucia.martinez@prueba.local', '3758 41-1011', 1111, 'RelacionDependencia', '150000', 'Ayudante de cocina', 'Restaurante El Paso', { comunicaciones: false }),
  C12: h('C-12', 'Marcos', 'Paredes', '27222403', '20272224030', '1979-10-03', 'Casado', 'marcos.paredes@prueba.local', '3758 41-1012', 1212, 'Monotributista', '1200000', 'Comerciante', 'Almacén propio', { monotributo: 'B' }),
  C13: h('C-13', 'Nora', 'Kowalski', '25333502', '27253335020', '1976-01-12', 'Viudo', 'nora.kowalski@prueba.local', '3758 41-1013', 1313, 'RelacionDependencia', '1500000', 'Concejal', 'Concejo Deliberante de Apóstoles', { pep: true, nacionalidad: 'Paraguaya' }),
  C14: h('C-14', 'Sofía', 'Menor', '48000000', '27480000000', '2010-05-15', 'Soltero', 'sofia.menor@prueba.local', '3758 41-1014', 1414, 'RelacionDependencia', '300000', 'Estudiante', ''),
};

// A.3 Clientes persona jurídica
const empresas = {
  P01: { codigo: 'P-01', tipo: 'PersonaJuridica', razonSocial: 'Yerbatera del Sur S.A.', cuit: '30701112330', constitucion: '2015-04-10', correo: 'pyme@prueba.local', telefono: '3758 42-5000', domicilio: 'Ruta 14 km 820', ingresos: '8000000', actividad: 'Elaboración de yerba mate', representante: 'Ricardo Benítez', representanteDni: '22111333', cargo: 'Presidente', pep: false, comunicaciones: true },
  P02: { codigo: 'P-02', tipo: 'PersonaJuridica', razonSocial: 'Transporte Apóstoles S.R.L.', cuit: '30703334470', constitucion: '2025-09-01', correo: 'transporte@prueba.local', telefono: '3758 42-6000', domicilio: 'Belgrano 455', ingresos: '3000000', actividad: 'Transporte de cargas', representante: 'Mónica Ayala', representanteDni: '24555777', cargo: 'Socia gerente', pep: false, comunicaciones: true },
};

const portal = { C01: 'Portal-Ana-2026-OK', C02: 'Portal-Bruno-2026-OK', P01: 'Portal-Yerba-2026-OK' };

module.exports.clientes = clientes;
module.exports.empresas = empresas;
module.exports.portal = portal;

// A.4 Líneas de crédito
const lineas = {
  L01: { codigo: 'MOTOS', nombre: 'Motos', descripcion: 'Financiación de motos nuevas y usadas', humana: true, juridica: false, categorias: ['RelacionDependencia', 'JubiladoPensionado', 'Monotributista'], montoMin: '300000', montoMax: '1500000', cuotasMin: '6', cuotasMax: '24', tna: '60', otorgamiento: '3', gastoTipo: 'Fijo', gasto: '500', edadMin: '18', edadMax: '75', antiguedad: '' },
  L02: { codigo: 'PERSONAL', nombre: 'Personal', descripcion: 'Préstamo personal de libre destino', humana: true, juridica: false, categorias: ['RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto'], montoMin: '100000', montoMax: '2000000', cuotasMin: '3', cuotasMax: '36', tna: '70', otorgamiento: '2', gastoTipo: 'Porcentual', gasto: '0,5', edadMin: '21', edadMax: '70', antiguedad: '' },
  L03: { codigo: 'PYME-CT', nombre: 'Capital de trabajo Pyme', descripcion: 'Capital de trabajo para empresas', humana: false, juridica: true, categorias: ['Empresa'], montoMin: '500000', montoMax: '5000000', cuotasMin: '6', cuotasMax: '24', tna: '55', otorgamiento: '2,5', gastoTipo: 'Fijo', gasto: '1000', edadMin: '', edadMax: '', antiguedad: '24' },
};

/** Fecha de hoy (o desplazada en días) en la zona de negocio, como AAAA-MM-DD. */
function fecha(dias = 0) {
  const d = new Date(Date.now() + dias * 86400000);
  return d.toLocaleDateString('sv-SE', { timeZone: 'America/Argentina/Buenos_Aires' });
}

module.exports.lineas = lineas;
module.exports.fecha = fecha;

/** Fecha en formato dd/mm/aaaa a partir de AAAA-MM-DD. */
const ddmmaaaa = (iso) => iso.split('-').reverse().join('/');

// A.6 Parámetros válidos
const parametros = {
  estructuracion: { porcentaje_afectacion: '30', alicuota_iva: '21', vigencia_alternativas_dias: '7', paso_redondeo: '1000', escalones: '100 75 50', tope_global_cliente: '' },
  morosidad: { tasa_punitoria_diaria: '0,12', tope_sobre_tna: '50', tolerancia_dias: '3', umbral_morosidad_avanzada_dias: '60', limite_condonacion: '100', refinanciacion_tasa_minima: '30', refinanciacion_tasa_maxima: '80', refinanciacion_cuotas_maximas: '36' },
  motivo: 'Configuración inicial para la prueba integral',
};

module.exports.ddmmaaaa = ddmmaaaa;
module.exports.parametros = parametros;

// A.10 Créditos de la iteración 3 (Motos, 12 cuotas) y desembolsos
const desembolsos = {
  C01: { importe: 500000, cbu: '0170099240000000123457', evidencia: 'Constancia de CBU del Banco Francés presentada por la titular', operacion: 'DES-0001' },
  C02: { importe: 600000, cbu: '0110599520000000678908', evidencia: 'Constancia de CBU del Banco Nación', operacion: 'DES-0002' },
  C03: { importe: 400000, cbu: '0720123988000000456784', evidencia: 'Constancia de CBU del Banco Santander', operacion: 'DES-0003' },
  C04: { importe: 300000, cbu: '1500001531000000987653', evidencia: 'Constancia de CBU del HSBC', operacion: 'DES-0004' },
  C10: { importe: 450000, cbu: '0140321155000000112233', evidencia: 'Constancia de CBU del Banco Provincia', operacion: 'DES-0005' },
  C06: { importe: 450000, cbu: '0340007466000000334455', evidencia: 'Constancia de CBU del Banco Patagonia', operacion: 'DES-0006' },
};
const cbuInvalido = '2850590909400904181357';
const cvu = '0000003110000123456781';

module.exports.desembolsos = desembolsos;
module.exports.cbuInvalido = cbuInvalido;
module.exports.cvu = cvu;
