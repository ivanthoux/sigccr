<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Exceptions\ValidacionException;
use App\Libraries\Auditoria\Cambios;
use App\Libraries\Clientes\CategoriasMonotributo;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;

/**
 * CU-101 Actualizar Categorías del Monotributo (D-26): topes de ingresos brutos anuales de las categorías A a K,
 * registrados como una escala con fecha de vigencia. ARCA los actualiza cada semestre (febrero y agosto).
 */
class MonotributoService extends BaseService
{
    /**
     * Escala vigente a una fecha: el tope más reciente de cada categoría con vigencia hasta esa fecha.
     *
     * @return array<string, array{tope: string, vigente_desde: string}> categoría => tope
     */
    public function vigentes(?string $fecha = null): array
    {
        $filas = $this->db->query(
            'SELECT x.categoria, x.ingresos_brutos_anuales, x.vigente_desde FROM (SELECT c.*, ROW_NUMBER() OVER (PARTITION BY c.categoria ORDER BY c.vigente_desde DESC) AS n '
            . 'FROM dbo.categorias_monotributo c WHERE c.vigente_desde <= CAST(? AS date)) x WHERE x.n = 1 ORDER BY x.categoria',
            [$fecha ?? $this->hoyNegocio()],
        )->getResultArray();
        $r = [];
        foreach ($filas as $f) {
            $r[trim((string) $f['categoria'])] = ['tope' => (string) $f['ingresos_brutos_anuales'], 'vigente_desde' => substr((string) $f['vigente_desde'], 0, 10)];
        }

        return $r;
    }

    /** @return list<array{vigente_desde: string, topes: array<string, string>}> escalas registradas, la más reciente primero */
    public function historial(): array
    {
        $r = [];
        foreach ($this->db->table('categorias_monotributo')->orderBy('vigente_desde', 'DESC')->orderBy('categoria')->get()->getResultArray() as $f) {
            $desde = substr((string) $f['vigente_desde'], 0, 10);
            $r[$desde]['vigente_desde'] = $desde;
            $r[$desde]['topes'][trim((string) $f['categoria'])] = (string) $f['ingresos_brutos_anuales'];
        }

        return array_values($r);
    }

    /**
     * Nueva escala (pasos 3 a 6; A1 fecha pasada o repetida, A2 topes no crecientes).
     *
     * @param array<string, mixed> $topes categoría => importe en formato local
     */
    public function registrarEscala(string $fechaTexto, array $topes): string
    {
        $fecha = Campos::fecha($fechaTexto);
        $v = CategoriasMonotributo::validarEscala($topes);
        $errores = $v['errores'];
        if ($fecha === null) {
            $errores['vigente_desde'] = 'Ingrese la fecha desde la que rige la escala (dd/mm/aaaa).';
        } elseif ($fecha < $this->hoyNegocio()) {
            $errores['vigente_desde'] = 'La escala no puede regir desde una fecha pasada: los clientes ya evaluados conservan la vigente entonces.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'D-26');
        }

        return $this->transaccion(function () use ($fecha, $v): string {
            if ($this->db->table('categorias_monotributo')->where('vigente_desde', $fecha)->countAllResults() > 0) {
                throw new ValidacionException(['vigente_desde' => 'Ya hay una escala registrada desde esa fecha.']);
            }
            // D-48: la escala que regía hasta esa fecha, para registrar el tope anterior y el nuevo de cada categoría
            $antes = array_map(static fn (array $c): string => $c['tope'], $this->vigentes($fecha));
            foreach ($v['topes'] as $cat => $tope) {
                $this->db->table('categorias_monotributo')->insert(['categoria' => $cat, 'ingresos_brutos_anuales' => $tope, 'vigente_desde' => $fecha]);
            }
            (new AuditoriaService($this->db))->registrarCambios('monotributo.registrar-escala', 'categorias_monotributo', null, Cambios::entre(
                array_combine(array_map(static fn ($c): string => "tope.{$c}", array_keys($antes)), $antes),
                array_combine(array_map(static fn ($c): string => "tope.{$c}", array_keys($v['topes'])), $v['topes']),
            ), null, ['vigente_desde' => $fecha]);

            return (string) $fecha;
        });
    }
}
