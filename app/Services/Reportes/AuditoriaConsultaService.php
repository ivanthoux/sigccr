<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Reportes\Periodo;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\I18n\Time;

/**
 * CU-57 Consultar Registro de Auditoría (RN-45). Solo lee: los registros no se modifican por funciones ordinarias.
 * El tipo de operación agrupa las acciones por su prefijo; «decisiones automáticas» son las que registró un proceso
 * programado (A4).
 */
class AuditoriaConsultaService extends BaseService
{
    public const POR_PAGINA = 50;

    /** Tipos de operación del paso 2 y los prefijos de acción que comprende cada uno. */
    public const CATEGORIAS = [
        'acceso' => ['Acceso', ['acceso.', 'portal.acceso-denegado']],
        'administracion' => ['Administración', ['usuarios.', 'clientes.', 'pre-registros.', 'lineas.', 'documentacion.', 'calendario.', 'geografia.', 'monotributo.', 'cajas.registrar']],
        'creditos' => ['Créditos', ['solicitudes.', 'alternativas.', 'evaluaciones.', 'creditos.', 'cartera.', 'vencimientos.', 'campanas.']],
        'cobranzas' => ['Cobranzas', ['cobros.', 'pagos.', 'cajas.abrir', 'cajas.cerrar', 'cajas.consultar-movimientos', 'cajas.informe-diario', 'fondos.']],
        'refinanciaciones' => ['Refinanciaciones', ['refinanciaciones.', 'punitorios.']],
        'saldos' => ['Saldos a favor', ['saldos-favor.']],
        'parametros' => ['Parámetros', ['parametros.']],
        'copias' => ['Copias de seguridad', ['copias.', 'restauraciones.']],
        'reportes' => ['Reportes y consultas gerenciales', ['reportes.', 'tablero.', 'regimen.', 'auditoria.']],
        'portal' => ['Portal del cliente', ['portal.']],
        'automaticas' => ['Decisiones automáticas', []],
    ];

    /** Objetos afectados del paso 2 y los tipos de objeto registrados que comprende cada uno. */
    public const OBJETOS = [
        'cliente' => ['Cliente', ['clientes', 'pre_registros', 'documentos_legajo']],
        'solicitud' => ['Solicitud', ['solicitudes_credito', 'simulaciones', 'simulaciones_comparativas', 'ofertas']],
        'credito' => ['Crédito', ['creditos', 'cobros', 'ordenes_pago_electronico', 'notificaciones_pasarela']],
        'propuesta' => ['Propuesta de refinanciación', ['propuestas_refinanciacion', 'simulaciones_refinanciacion', 'condonaciones']],
        'caja' => ['Caja', ['cajas', 'jornadas_caja', 'movimientos_fondos', 'proyecciones_fondos']],
        'usuario' => ['Usuario', ['usuarios_internos', 'cuenta_acceso']],
        'parametro' => ['Parámetro', ['versiones_parametros', 'lineas_credito', 'tipos_documentacion', 'documentacion_sugerida', 'dias_no_habiles', 'categorias_monotributo', 'localidades']],
    ];

    public const RESULTADOS = ['Exitoso' => 'Exitoso', 'Rechazado' => 'Rechazado', 'Denegado' => 'Denegado', 'Fallido' => 'Fallido', 'Error' => 'Error'];

    /**
     * Pasos 3 y 4 (A1, A2).
     *
     * @param array<string, mixed> $f desde, hasta, responsable, categoria, objeto, resultado, pagina
     * @return array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, periodo: array<string, mixed>}
     */
    public function buscar(array $f): array
    {
        $hoy = $this->hoyNegocio();
        $p = Periodo::desde(['tipo' => 'rango', 'desde' => ($f['desde'] ?? '') ?: '', 'hasta' => ($f['hasta'] ?? '') ?: ''], $hoy, false, 7);
        if ($p['hasta'] > $hoy) {
            throw new ValidacionException(['hasta' => 'La fecha final no puede ser posterior a hoy.']);
        }
        $zona = config('Sigccr')->zonaNegocio;
        $w = ' WHERE a.fecha_hora >= ? AND a.fecha_hora < ?';
        $b = [Time::parse($p['desde'], $zona)->setTimezone('UTC')->format('Y-m-d H:i:s'), Time::parse($p['hasta'], $zona)->addDays(1)->setTimezone('UTC')->format('Y-m-d H:i:s')];
        if (trim((string) ($f['responsable'] ?? '')) !== '') {
            $w .= ' AND a.responsable LIKE ?';
            $b[] = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], trim((string) $f['responsable'])) . '%';
        }
        $cat = (string) ($f['categoria'] ?? '');
        if ($cat === 'automaticas') {
            $w .= ' AND a.proceso IS NOT NULL';
        } elseif (isset(self::CATEGORIAS[$cat])) {
            $partes = [];
            foreach (self::CATEGORIAS[$cat][1] as $prefijo) {
                $partes[] = str_ends_with($prefijo, '.') ? 'a.accion LIKE ?' : 'a.accion = ?';
                $b[] = str_ends_with($prefijo, '.') ? $prefijo . '%' : $prefijo;
            }
            $w .= ' AND (' . implode(' OR ', $partes) . ')';
        }
        if (isset(self::OBJETOS[$f['objeto'] ?? ''])) {
            $tipos = self::OBJETOS[$f['objeto']][1];
            $w .= ' AND a.objeto_tipo IN (' . implode(', ', array_fill(0, count($tipos), '?')) . ')';
            array_push($b, ...$tipos);
        }
        if (isset(self::RESULTADOS[$f['resultado'] ?? ''])) {
            $w .= ' AND a.resultado = ?';
            $b[] = $f['resultado'];
        }
        $total = (int) $this->db->query('SELECT COUNT(*) AS t FROM dbo.registros_auditoria a' . $w, $b)->getRow()->t;
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($paginas, max(1, (int) ($f['pagina'] ?? 1)));
        $filas = $this->db->query(
            'SELECT a.id, a.fecha_hora, a.responsable, a.proceso, a.accion, a.objeto_tipo, a.objeto_id, a.resultado, a.motivo FROM dbo.registros_auditoria a' . $w
            . ' ORDER BY a.fecha_hora DESC, a.id DESC OFFSET ' . (($pagina - 1) * self::POR_PAGINA) . ' ROWS FETCH NEXT ' . self::POR_PAGINA . ' ROWS ONLY',
            $b,
        )->getResultArray();

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'periodo' => $p];
    }

    /**
     * Pasos 5 y 6 (A4): detalle con el rol del responsable o el proceso y los parámetros aplicados.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $r = $this->db->query(
            'SELECT a.*, r.nombre AS rol, CASE WHEN cl.id IS NOT NULL THEN 1 ELSE 0 END AS es_cliente FROM dbo.registros_auditoria a '
            . 'LEFT JOIN dbo.usuarios_internos u ON u.id = a.cuenta_acceso_id LEFT JOIN dbo.roles r ON r.id = u.rol_id LEFT JOIN dbo.clientes cl ON cl.cuenta_cliente_id = a.cuenta_acceso_id WHERE a.id = ?',
            [$id],
        )->getRowArray();
        if ($r === null) {
            throw new ReglaNegocioException('El registro de auditoría no existe.');
        }
        $r['detalle_datos'] = $r['detalle'] === null ? null : json_decode((string) $r['detalle'], true);
        $r['rol'] = $r['proceso'] !== null ? null : ($r['rol'] ?? ((int) $r['es_cliente'] === 1 ? 'Cliente' : null));
        (new AuditoriaService($this->db))->registrar('auditoria.consultar', 'registros_auditoria', $id, 'Exitoso', null, ['accion' => $r['accion']]);

        return $r;
    }

    /** Tipo de operación de una acción, para el listado. */
    public static function categoria(string $accion, ?string $proceso): string
    {
        foreach (self::CATEGORIAS as [$texto, $prefijos]) {
            foreach ($prefijos as $p) {
                if (str_ends_with($p, '.') ? str_starts_with($accion, $p) : $accion === $p) {
                    return $texto . ($proceso !== null ? ' (automática)' : '');
                }
            }
        }

        return $proceso !== null ? 'Decisión automática' : 'Otra';
    }
}
