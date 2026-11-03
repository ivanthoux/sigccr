<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;

/**
 * CU-100 Configurar Catálogo de Documentación (D-25): tipos de documento del legajo y documentación base que se
 * propone para cada categoría de ingresos al configurar una línea. Los tipos no se eliminan: se desactivan, porque los
 * documentos y las versiones de condiciones ya registrados los referencian.
 */
class DocumentacionService extends BaseService
{
    public const APLICA_A = ['PersonaHumana' => 'Personas humanas', 'PersonaJuridica' => 'Personas jurídicas', 'Ambos' => 'Ambos'];

    /** @return list<array{id: int, codigo: string, nombre: string, aplica_a: string, es_clave: int, activo: int, usos: int}> */
    public function tipos(): array
    {
        return array_map(static fn (array $t): array => ['id' => (int) $t['id'], 'es_clave' => (int) $t['es_clave'], 'activo' => (int) $t['activo'], 'usos' => (int) $t['usos']] + $t, $this->db->query(
            'SELECT t.id, t.codigo, t.nombre, t.aplica_a, t.es_clave, t.activo, '
            . '(SELECT COUNT(*) FROM dbo.documentos_legajo d WHERE d.tipo_documentacion_id = t.id) AS usos '
            . 'FROM dbo.tipos_documentacion t ORDER BY t.activo DESC, t.id',
        )->getResultArray());
    }

    /**
     * Documentación base por categoría (la propone el formulario de líneas, CU-16 y CU-17).
     *
     * @return array<string, list<string>> categoría => códigos de tipos activos
     */
    public function sugeridos(): array
    {
        $r = array_fill_keys(array_merge(...array_map('array_keys', array_values(RequisitosDocumentales::CATEGORIAS))), []);
        $filas = $this->db->query(
            'SELECT s.categoria_ingresos, t.codigo FROM dbo.documentacion_sugerida s JOIN dbo.tipos_documentacion t ON t.id = s.tipo_documentacion_id '
            . 'WHERE t.activo = 1 ORDER BY t.id',
        )->getResultArray();
        foreach ($filas as $f) {
            $r[(string) $f['categoria_ingresos']][] = (string) $f['codigo'];
        }

        return $r;
    }

    /**
     * Alta de un tipo de documento (A1: nombre repetido).
     *
     * @param array<string, mixed> $d nombre, aplica_a, es_clave
     */
    public function registrarTipo(array $d): int
    {
        $datos = $this->validarTipo($d);

        return $this->transaccion(function () use ($datos): int {
            if ($this->db->table('tipos_documentacion')->where('nombre', $datos['nombre'])->countAllResults() > 0) {
                throw new ValidacionException(['nombre' => 'Ya existe un tipo de documento con ese nombre.']);
            }
            $base = RequisitosDocumentales::codigoPara($datos['nombre']);
            $codigo = $base;
            for ($n = 2; $this->db->table('tipos_documentacion')->where('codigo', $codigo)->countAllResults() > 0; $n++) {
                $codigo = substr($base, 0, 27) . '_' . $n;
            }
            $this->db->table('tipos_documentacion')->insert($datos + ['codigo' => $codigo, 'activo' => 1]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('documentacion.registrar-tipo', 'tipos_documentacion', $id, 'Exitoso', null, $datos + ['codigo' => $codigo]);

            return $id;
        });
    }

    /**
     * Modificación de un tipo: nombre, documento clave y estado. A quién aplica no cambia si hay documentos cargados,
     * porque dejarían de corresponder a sus clientes. La identificación del alta (D-14) no se puede desactivar.
     *
     * @param array<string, mixed> $d nombre, aplica_a, es_clave, activo
     */
    public function modificarTipo(int $id, array $d): void
    {
        $datos = $this->validarTipo($d) + ['activo' => empty($d['activo']) ? 0 : 1];
        $this->transaccion(function () use ($id, $datos): void {
            $actual = $this->bloquear('tipos_documentacion', [$id])[0] ?? null;
            if ($actual === null) {
                throw new ReglaNegocioException('El tipo de documento no existe.');
            }
            if ($this->db->table('tipos_documentacion')->where('nombre', $datos['nombre'])->where('id <>', $id)->countAllResults() > 0) {
                throw new ValidacionException(['nombre' => 'Ya existe otro tipo de documento con ese nombre.']);
            }
            $identificacion = array_merge(...array_values(RequisitosDocumentales::IDENTIFICACION));
            if ($datos['activo'] === 0 && in_array($actual['codigo'], $identificacion, true)) {
                throw new ReglaNegocioException('Este documento identifica al cliente en el alta (D-14): no se puede desactivar.');
            }
            if ($datos['aplica_a'] !== $actual['aplica_a'] && $this->db->table('documentos_legajo')->where('tipo_documentacion_id', $id)->countAllResults() > 0) {
                throw new ValidacionException(['aplica_a' => 'Ya hay documentos de este tipo en legajos de clientes: no se puede cambiar a quién aplica.']);
            }
            $this->db->table('tipos_documentacion')->where('id', $id)->update($datos);
            (new AuditoriaService($this->db))->registrar('documentacion.modificar-tipo', 'tipos_documentacion', $id, 'Exitoso', null, [
                'antes' => array_intersect_key($actual, $datos), 'despues' => $datos,
            ]);
        });
    }

    /**
     * Documentación base por categoría (reemplaza la configuración anterior).
     *
     * @param array<string, mixed> $porCategoria categoría => list<código>
     */
    public function guardarSugeridos(array $porCategoria): void
    {
        $limpio = [];
        foreach ($porCategoria as $cat => $codigos) {
            $limpio[(string) $cat] = array_values(array_unique(array_map('strval', (array) $codigos)));
        }
        $tipos = $this->db->table('tipos_documentacion')->select('id, codigo, aplica_a')->where('activo', 1)->get()->getResultArray();
        $errores = RequisitosDocumentales::validarSugeridos($limpio, array_column($tipos, 'aplica_a', 'codigo'));
        if ($errores !== []) {
            throw new ValidacionException(array_combine(array_map(static fn ($c) => "sugeridos_{$c}", array_keys($errores)), $errores), 'RN-11');
        }
        $ids = array_column($tipos, 'id', 'codigo');
        $this->transaccion(function () use ($limpio, $ids): void {
            $this->db->query('DELETE FROM dbo.documentacion_sugerida');
            foreach ($limpio as $cat => $codigos) {
                foreach ($codigos as $c) {
                    $this->db->table('documentacion_sugerida')->insert(['categoria_ingresos' => $cat, 'tipo_documentacion_id' => (int) $ids[$c]]);
                }
            }
            (new AuditoriaService($this->db))->registrar('documentacion.sugeridos', 'documentacion_sugerida', null, 'Exitoso', null, $limpio);
        });
    }

    /**
     * @param array<string, mixed> $d
     * @return array{nombre: string, aplica_a: string, es_clave: int}
     */
    private function validarTipo(array $d): array
    {
        $nombre = Campos::texto($d['nombre'] ?? '');
        $aplica = (string) ($d['aplica_a'] ?? '');
        $e = [];
        if (mb_strlen($nombre) < 5 || mb_strlen($nombre) > 120) {
            $e['nombre'] = 'Ingrese el nombre del documento (de 5 a 120 caracteres).';
        }
        if (!isset(self::APLICA_A[$aplica])) {
            $e['aplica_a'] = 'Indique a qué tipo de cliente corresponde.';
        }
        if ($e !== []) {
            throw new ValidacionException($e, 'RN-11');
        }

        return ['nombre' => $nombre, 'aplica_a' => $aplica, 'es_clave' => empty($d['es_clave']) ? 0 : 1];
    }
}
