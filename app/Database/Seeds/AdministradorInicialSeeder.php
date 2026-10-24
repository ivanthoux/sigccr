<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Crea el primer Administrador (RN-05) durante la instalación. Solicita los datos por consola; la contraseña debe
 * tener entre 15 y 128 caracteres (RN-62) y se guarda con Argon2id.
 *   php spark db:seed AdministradorInicialSeeder -g migraciones
 */
class AdministradorInicialSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('usuarios_internos u')->join('roles r', 'r.id = u.rol_id')->where('r.codigo', 'Administrador')->countAllResults() > 0) {
            CLI::write('Ya existe un Administrador; no se crea otro.', 'yellow');

            return;
        }
        $correo = CLI::prompt('Correo del Administrador', null, 'required|valid_email');
        $nombre = CLI::prompt('Nombre', null, 'required');
        $apellido = CLI::prompt('Apellido', null, 'required');
        $documento = CLI::prompt('Número de DNI', null, 'required|numeric');
        while (true) {
            $clave = $this->leerOculto('Contraseña (15 a 128 caracteres)');
            if (mb_strlen($clave) < 15 || mb_strlen($clave) > 128) {
                CLI::write('La contraseña debe tener entre 15 y 128 caracteres (RN-62).', 'red');

                continue;
            }
            if ($this->leerOculto('Repetir la contraseña') !== $clave) {
                CLI::write('Las contraseñas no coinciden.', 'red');

                continue;
            }
            break;
        }

        $ahora = Time::now('UTC')->format('Y-m-d H:i:s.vP');
        $rolId = (int) $this->db->table('roles')->select('id')->where('codigo', 'Administrador')->get()->getRowArray()['id'];
        $this->db->transException(true)->transStart();
        $this->db->table('cuentas_acceso')->insert([
            'tipo_cuenta' => 'UsuarioInterno',
            'correo' => mb_strtolower($correo),
            'hash_contrasena' => password_hash($clave, PASSWORD_ARGON2ID),
            'correo_verificado' => 1,
            'estado' => 'Habilitada',
            'creado_en' => $ahora,
        ]);
        $id = (int) $this->db->insertID();
        $this->db->table('usuarios_internos')->insert([
            'id' => $id, 'rol_id' => $rolId, 'nombre' => $nombre, 'apellido' => $apellido,
            'tipo_documento' => 'DNI', 'nro_documento' => $documento,
        ]);
        $this->db->transComplete();
        CLI::write("Administrador creado (cuenta #{$id}).", 'green');
    }

    /**
     * Lee una línea sin mostrarla en pantalla (stty -echo) cuando la entrada es una terminal.
     */
    private function leerOculto(string $mensaje): string
    {
        CLI::print($mensaje . ': ');
        $terminal = function_exists('posix_isatty') && posix_isatty(STDIN);
        if ($terminal) {
            shell_exec('stty -echo');
        }
        $valor = rtrim((string) fgets(STDIN), "\r\n");
        if ($terminal) {
            shell_exec('stty echo');
            CLI::newLine();
        }

        return $valor;
    }
}
