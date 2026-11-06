<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\Comunes\GeografiaService;
use CodeIgniter\Database\Seeder;

/**
 * Provincias y localidades base del domicilio (D-18). El catálogo completo de localidades censales se importa con
 * php spark geografia:importar.
 */
class GeografiaSeeder extends Seeder
{
    public function run(): void
    {
        (new GeografiaService($this->db))->cargarBase();
    }
}
