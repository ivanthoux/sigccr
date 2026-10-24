<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\Seguridad\CatalogoPermisos;
use CodeIgniter\Database\Seeder;

/**
 * Permisos del panel y su asignación a los roles (RN-01, RN-06), según CatalogoPermisos (3.6.2). Es idempotente:
 * agrega los permisos nuevos, actualiza descripciones y deja la asignación de cada rol igual a la matriz.
 */
class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->transException(true)->transStart();

        $ids = [];
        foreach (CatalogoPermisos::PERMISOS as $cu => [$codigo]) {
            $fila = $this->db->table('permisos')->select('id, descripcion')->where('codigo', $codigo)->get()->getRowArray();
            $descripcion = CatalogoPermisos::descripcion($cu);
            if ($fila === null) {
                $this->db->table('permisos')->insert(['codigo' => $codigo, 'descripcion' => $descripcion]);
                $ids[$codigo] = (int) $this->db->insertID();
            } else {
                $ids[$codigo] = (int) $fila['id'];
                if ($fila['descripcion'] !== $descripcion) {
                    $this->db->table('permisos')->where('id', $ids[$codigo])->update(['descripcion' => $descripcion]);
                }
            }
        }

        foreach (array_keys(CatalogoPermisos::MATRIZ) as $rol) {
            $rolId = (int) $this->db->table('roles')->select('id')->where('codigo', $rol)->get()->getRowArray()['id'];
            $deseados = array_map(static fn (string $c) => $ids[$c], CatalogoPermisos::permisosDelRol($rol));
            $actuales = array_map('intval', array_column(
                $this->db->table('roles_permisos')->select('permiso_id')->where('rol_id', $rolId)->get()->getResultArray(),
                'permiso_id',
            ));
            foreach (array_diff($deseados, $actuales) as $permisoId) {
                $this->db->table('roles_permisos')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
            $sobrantes = array_values(array_diff($actuales, $deseados));
            if ($sobrantes !== []) {
                $this->db->table('roles_permisos')->where('rol_id', $rolId)->whereIn('permiso_id', $sobrantes)->delete();
            }
        }

        $this->db->transComplete();
    }
}
