<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Datos semilla de la instalación (3.5.1, 4.3.5):  php spark db:seed SigccrSeeder -g migraciones
 * El usuario Administrador inicial se crea aparte:  php spark db:seed AdministradorInicialSeeder -g migraciones
 */
class SigccrSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(PermisosSeeder::class);
        $this->call(TiposDocumentacionSeeder::class);
        $this->call(ParametrosInicialesSeeder::class);
    }
}
