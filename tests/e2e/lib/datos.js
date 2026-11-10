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
