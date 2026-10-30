<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Solicitudes\CalendarioOperativo;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;

/** CU-82 Actualizar Calendario Operativo. Los vencimientos de aprobaciones ya calculados no cambian (RN-49). */
class CalendarioService extends BaseService
{
    /** @return list<array{fecha: string, descripcion: string, tipo: string}> */
    public function delAnio(int $anio): array
    {
        return $this->db->query('SELECT fecha, descripcion, tipo FROM dbo.dias_no_habiles WHERE YEAR(fecha) = ? ORDER BY fecha', [$anio])->getResultArray();
    }

    /**
     * Pasos 3 a 7 (A1 a A3): registra las fechas válidas e informa las rechazadas.
     *
     * @param list<array{fecha?: string, descripcion?: string, tipo?: string}> $filas
     * @return array{agregadas: int, rechazadas: list<string>}
     */
    public function agregar(array $filas): array
    {
        return $this->transaccion(function () use ($filas): array {
            $registradas = array_map(static fn ($f) => substr((string) $f['fecha'], 0, 10), $this->db->query('SELECT fecha FROM dbo.dias_no_habiles WITH (UPDLOCK, HOLDLOCK) WHERE fecha >= CAST(? AS date)', [$this->hoyNegocio()])->getResultArray());
            $r = CalendarioOperativo::validar($filas, $this->hoyNegocio(), $registradas);
            if ($r['validas'] === []) {
                throw new ValidacionException(['fechas' => $r['rechazadas'] === [] ? 'Ingrese al menos una fecha con su descripción.' : 'No se registró ninguna fecha. ' . implode('; ', $r['rechazadas']) . '.']);
            }
            foreach ($r['validas'] as $f) {
                $this->db->table('dias_no_habiles')->insert($f);
            }
            (new AuditoriaService($this->db))->registrar('calendario.agregar', 'dias_no_habiles', null, 'Exitoso', null, ['fechas' => array_column($r['validas'], 'fecha'), 'rechazadas' => $r['rechazadas']]);

            return ['agregadas' => count($r['validas']), 'rechazadas' => $r['rechazadas']];
        });
    }

    /** Paso 3: baja de un día no hábil futuro (A1). */
    public function darDeBaja(string $fechaTexto): void
    {
        $fecha = Campos::fecha($fechaTexto);
        if ($fecha === null || $fecha < $this->hoyNegocio()) {
            throw new ReglaNegocioException('Solo se pueden dar de baja días de hoy en adelante: modificar fechas pasadas alteraría cómputos ya realizados.');
        }
        $this->transaccion(function () use ($fecha): void {
            $f = $this->db->query('SELECT fecha, descripcion FROM dbo.dias_no_habiles WITH (UPDLOCK) WHERE fecha = ?', [$fecha])->getRowArray();
            if ($f === null) {
                throw new ReglaNegocioException('La fecha no está registrada como día no hábil.');
            }
            $this->db->table('dias_no_habiles')->where('fecha', $fecha)->delete();
            (new AuditoriaService($this->db))->registrar('calendario.baja', 'dias_no_habiles', $fecha, 'Exitoso', null, ['descripcion' => $f['descripcion']]);
        });
    }
}
