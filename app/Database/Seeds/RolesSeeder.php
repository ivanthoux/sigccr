<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Roles (RN-06). Los permisos y su asignación a cada rol los carga PermisosSeeder (matriz de participación, 3.6.2).
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['codigo' => 'Administrador', 'nombre' => 'Administrador', 'descripcion' => 'Administra usuarios, roles, cajas y copias de seguridad.'],
            ['codigo' => 'Gerente', 'nombre' => 'Gerente', 'descripcion' => 'Resuelve solicitudes y propuestas, anula cobros y configura parámetros.'],
            ['codigo' => 'AnalistaCredito', 'nombre' => 'Analista de Crédito', 'descripcion' => 'Registra clientes, solicitudes, evaluaciones de contingencia y propuestas.'],
            ['codigo' => 'Cajero', 'nombre' => 'Cajero', 'descripcion' => 'Opera la caja, registra cobros y desembolsos en efectivo.'],
        ];
        foreach ($roles as $r) {
            if ($this->db->table('roles')->where('codigo', $r['codigo'])->countAllResults() === 0) {
                $this->db->table('roles')->insert($r);
            }
        }
    }
}
