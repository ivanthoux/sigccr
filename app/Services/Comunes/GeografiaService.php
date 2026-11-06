<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Libraries\Clientes\CatalogoGeografico;
use App\Services\BaseService;

/**
 * Catálogo de provincias y localidades del domicilio (D-18): consultas para los formularios, carga base de la
 * instalación e importación del catálogo oficial de localidades censales (comando geografia:importar).
 */
class GeografiaService extends BaseService
{
    /** @return list<array{id: int, nombre: string}> */
    public function provincias(): array
    {
        return array_map(static fn ($p) => ['id' => (int) $p['id'], 'nombre' => (string) $p['nombre']], $this->db->table('provincias')->orderBy('nombre')->get()->getResultArray());
    }

    /**
     * Localidades activas de una provincia. Se indica el departamento de las homónimas para distinguirlas.
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function localidades(int $provinciaId): array
    {
        $filas = $this->db->table('localidades')->select('id, nombre, departamento')
            ->where('provincia_id', $provinciaId)->where('activa', 1)->orderBy('nombre')->orderBy('departamento')->get()->getResultArray();
        $repetidas = array_count_values(array_map(static fn ($f) => CatalogoGeografico::clave((string) $f['nombre']), $filas));

        return array_map(static fn ($f) => [
            'id' => (int) $f['id'],
            'nombre' => $repetidas[CatalogoGeografico::clave((string) $f['nombre'])] > 1 && $f['departamento'] !== null
                ? "{$f['nombre']} (depto. {$f['departamento']})" : (string) $f['nombre'],
        ], $filas);
    }

    /** @return array{id: int, nombre: string, departamento: ?string, provincia_id: int, provincia: string, activa: int}|null */
    public function localidad(int $id): ?array
    {
        $f = $this->db->table('localidades l')->select('l.id, l.nombre, l.departamento, l.provincia_id, l.activa, p.nombre AS provincia')
            ->join('provincias p', 'p.id = l.provincia_id')->where('l.id', $id)->get()->getRowArray();

        return $f === null ? null : ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'departamento' => $f['departamento'], 'provincia_id' => (int) $f['provincia_id'], 'provincia' => (string) $f['provincia'], 'activa' => (int) $f['activa']];
    }

    /** Provincias y localidades base de la instalación (SigccrSeeder). No duplica las existentes. */
    public function cargarBase(): int
    {
        return $this->transaccion(function (): int {
            $this->asegurarProvincias();

            return $this->incorporar(array_map(static fn ($l) => ['codigo' => '', 'provincia_id' => $l[0], 'nombre' => $l[1], 'departamento' => $l[2]], CatalogoGeografico::LOCALIDADES_BASE))['nuevas'];
        });
    }

    /**
     * Importa el catálogo oficial: agrega las localidades nuevas y completa el código y el departamento de las que ya
     * existen con el mismo nombre en la provincia (las cargadas con la instalación). No elimina ninguna, porque pueden
     * estar en domicilios registrados.
     *
     * @param list<array{codigo: string, nombre: string, provincia_id: int, departamento: ?string}> $localidades
     * @return array{nuevas: int, actualizadas: int}
     */
    public function importar(array $localidades): array
    {
        return $this->transaccion(function () use ($localidades): array {
            $this->asegurarProvincias();
            $r = $this->incorporar($localidades);
            (new AuditoriaService($this->db))->registrar('geografia.importar', 'localidades', null, 'Exitoso', null, $r + ['leidas' => count($localidades)], 'geografia:importar');

            return $r;
        });
    }

    private function asegurarProvincias(): void
    {
        $existentes = array_map('intval', array_column($this->db->table('provincias')->select('id')->get()->getResultArray(), 'id'));
        foreach (CatalogoGeografico::PROVINCIAS as $id => $nombre) {
            if (!in_array($id, $existentes, true)) {
                $this->db->table('provincias')->insert(['id' => $id, 'nombre' => $nombre]);
            }
        }
    }

    /**
     * @param list<array{codigo: string, nombre: string, provincia_id: int, departamento: ?string}> $localidades
     * @return array{nuevas: int, actualizadas: int}
     */
    private function incorporar(array $localidades): array
    {
        $porCodigo = [];
        $porNombre = [];
        $porNombreDepto = [];
        foreach ($this->db->table('localidades')->select('id, provincia_id, nombre, departamento, codigo')->get()->getResultArray() as $f) {
            $k = $f['provincia_id'] . '|' . CatalogoGeografico::clave((string) $f['nombre']);
            if ($f['codigo'] !== null) {
                $porCodigo[$f['codigo']] = (int) $f['id'];
            }
            $porNombre[$k][] = $f;
            $porNombreDepto[$k . '|' . CatalogoGeografico::clave((string) ($f['departamento'] ?? ''))] = true;
        }
        $nuevas = [];
        $actualizadas = 0;
        foreach ($localidades as $l) {
            if ($l['codigo'] !== '' && isset($porCodigo[$l['codigo']])) {
                continue;
            }
            $k = $l['provincia_id'] . '|' . CatalogoGeografico::clave($l['nombre']);
            $kd = $k . '|' . CatalogoGeografico::clave((string) ($l['departamento'] ?? ''));
            // Una localidad base (sin código) con el mismo nombre en la provincia recibe el código del catálogo
            $candidatas = array_values(array_filter($porNombre[$k] ?? [], static fn ($f) => $f['codigo'] === null));
            if ($l['codigo'] !== '' && count($candidatas) === 1) {
                $this->db->table('localidades')->where('id', (int) $candidatas[0]['id'])->update(['codigo' => $l['codigo'], 'departamento' => $l['departamento'] ?? $candidatas[0]['departamento']]);
                foreach ($porNombre[$k] as $i => $f) {
                    if ((int) $f['id'] === (int) $candidatas[0]['id']) {
                        $porNombre[$k][$i]['codigo'] = $l['codigo'];
                    }
                }
                $porCodigo[$l['codigo']] = (int) $candidatas[0]['id'];
                $actualizadas++;
                continue;
            }
            if (isset($porNombreDepto[$kd])) {
                continue;
            }
            $porNombreDepto[$kd] = true;
            $porNombre[$k][] = ['id' => 0, 'codigo' => $l['codigo'] === '' ? null : $l['codigo'], 'departamento' => $l['departamento']];
            $nuevas[] = ['provincia_id' => $l['provincia_id'], 'nombre' => $l['nombre'], 'departamento' => $l['departamento'], 'codigo' => $l['codigo'] === '' ? null : $l['codigo'], 'activa' => 1];
        }
        foreach (array_chunk($nuevas, 200) as $lote) {
            $this->db->table('localidades')->insertBatch($lote);
        }

        return ['nuevas' => count($nuevas), 'actualizadas' => $actualizadas];
    }
}
