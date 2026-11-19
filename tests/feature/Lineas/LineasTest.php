<?php

declare(strict_types=1);

namespace Tests\Feature\Lineas;

use App\Exceptions\ConflictoConcurrenciaException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Feature\Esperado;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-18 a IN-21 · Líneas de crédito (CU-16 a CU-19, CU-73) e IN-22 · Alertas (CU-83). Plan de pruebas 7.9.2 y
 * Anexos B.6 y B.7.
 *
 * @internal
 */
final class LineasTest extends IntegracionTestCase
{
    /** @return array<string, array<string, mixed>> definición de cada campo (Anexo B.6) */
    public static function campos(): array
    {
        $anio = (int) date('Y') + 2;

        return [
            'codigo' => ['tipo' => 'COD', 'obligatorio' => true, 'mensajes' => [
                'obligatorio' => 'Complete la identificación de la línea.',
                'formato' => 'La identificación admite de 2 a 20 letras, números, guiones o guiones bajos.',
            ]],
            'nombre' => ['tipo' => 'TXT', 'obligatorio' => true, 'max' => 100, 'mensajes' => [
                'obligatorio' => 'Complete el nombre.', 'largo' => 'El nombre admite hasta 100 caracteres.',
            ]],
            'descripcion' => ['tipo' => 'TXT', 'obligatorio' => false, 'max' => 500, 'mensajes' => ['largo' => 'La descripción admite hasta 500 caracteres.']],
            'tna' => ['tipo' => 'PCT', 'obligatorio' => true, 'maximo' => '500', 'maximo_incluido' => true, 'mensajes' => [
                'obligatorio' => 'Complete la TNA.', 'formato' => 'Ingrese la TNA en porcentaje, por ejemplo 65 o 65,5.',
                'maximo' => 'La TNA no puede superar el 500 %.', 'cero' => 'La TNA debe ser mayor que cero.',
            ]],
            // D-54: hasta 50 % del capital, incluido
            'gasto_otorgamiento_pct' => ['tipo' => 'PCT', 'obligatorio' => true, 'maximo' => '50', 'maximo_incluido' => true, 'mensajes' => [
                'obligatorio' => 'Complete el gasto de otorgamiento (0 si no tiene).',
                'formato' => 'Ingrese el gasto de otorgamiento en porcentaje del capital.',
                'maximo' => 'El gasto de otorgamiento no puede superar el 50 % del capital.',
            ]],
            'tipo_gasto_administrativo' => [
                // O-03 (D-52): un valor fuera de la lista, vacío o ausente se rechaza
                'tipo' => 'SEL', 'obligatorio' => true, 'valores' => ['Fijo', 'Porcentual'],
                'mensajes' => ['obligatorio' => 'Seleccione cómo se cobra el gasto administrativo.', 'formato' => 'Seleccione cómo se cobra el gasto administrativo.'],
                'otros' => ['gasto_administrativo_pct' => '0,5'],
            ],
            'gasto_administrativo_cuota' => ['tipo' => 'IMP', 'obligatorio' => true, 'mensajes' => [
                'obligatorio' => 'Complete el gasto administrativo por cuota (0 si no tiene).', 'formato' => 'Ingrese un importe válido, por ejemplo 500,00.',
            ]],
            'gasto_administrativo_pct' => [
                'tipo' => 'PCT', 'obligatorio' => true, 'maximo' => '10', 'maximo_incluido' => true,
                'otros' => ['tipo_gasto_administrativo' => 'Porcentual'],
                'mensajes' => [
                    'obligatorio' => 'Complete el porcentaje del capital que se cobra en cada cuota.',
                    'formato' => 'Ingrese el porcentaje del capital, por ejemplo 0,5.',
                    'cero' => 'El porcentaje debe ser mayor que cero; si la línea no tiene gasto, elija «Importe fijo por cuota» con 0.',
                    'maximo' => 'El gasto administrativo no puede superar el 10 % del capital por cuota.',
                ],
            ],
            'monto_minimo' => [
                'tipo' => 'IMP', 'obligatorio' => true, 'otros' => ['monto_maximo' => '99999999999999999,99'],
                'mensajes' => [
                    'obligatorio' => 'Complete el importe mínimo prestable.', 'formato' => 'Ingrese un importe válido, por ejemplo 300.000,00.',
                    'cero' => 'El importe mínimo debe ser mayor que cero.',
                ],
            ],
            'monto_maximo' => [
                'tipo' => 'IMP', 'obligatorio' => true, 'otros' => ['monto_minimo' => '0,01'],
                'mensajes' => [
                    'obligatorio' => 'Complete el importe máximo prestable.', 'formato' => 'Ingrese un importe válido, por ejemplo 1.500.000,00.',
                    'cero' => 'El importe máximo debe ser mayor que el mínimo (RN-14, D-23).',
                ],
            ],
            'cuotas_minimas' => [
                'tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 1, 'otros' => ['cuotas_maximas' => '120'],
                'mensajes' => ['obligatorio' => 'Ingrese la cantidad mínima de cuotas (al menos 1).', 'formato' => 'Ingrese la cantidad mínima de cuotas (al menos 1).'],
            ],
            'cuotas_maximas' => [
                'tipo' => 'ENT', 'obligatorio' => true, 'minimo' => 2, 'maximo' => 120, 'otros' => ['cuotas_minimas' => '1'],
                'mensajes' => [
                    'obligatorio' => 'Ingrese la cantidad máxima de cuotas.', 'formato' => 'Ingrese la cantidad máxima de cuotas.',
                    'minimo' => 'La cantidad máxima de cuotas debe ser mayor que la mínima (RN-14, D-23).',
                    'maximo' => 'La cantidad máxima de cuotas no puede superar 120.',
                ],
            ],
            'edad_minima' => [
                'tipo' => 'ENT', 'obligatorio' => false, 'minimo' => 18, 'maximo' => 100, 'normal' => '21', 'otros' => ['edad_maxima' => ''],
                'mensajes' => ['formato' => 'Ingrese la edad mínima en años.', 'minimo' => 'La edad mínima debe estar entre 18 y 100 años.', 'maximo' => 'La edad mínima debe estar entre 18 y 100 años.'],
            ],
            'edad_maxima' => [
                'tipo' => 'ENT', 'obligatorio' => false, 'minimo' => 18, 'maximo' => 100, 'normal' => '75', 'otros' => ['edad_minima' => ''],
                'mensajes' => ['formato' => 'Ingrese la edad máxima en años.', 'minimo' => 'La edad máxima debe estar entre 18 y 100 años.', 'maximo' => 'La edad máxima debe estar entre 18 y 100 años.'],
            ],
            'antiguedad_minima_meses' => [
                'tipo' => 'ENT', 'obligatorio' => false, 'minimo' => 0, 'normal' => '12',
                'mensajes' => ['formato' => 'Ingrese la antigüedad mínima en meses.'],
            ],
            'admite_persona_humana' => ['tipo' => 'CHK'],
            'vigencia_inicio' => [
                'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => 2020,
                'mensajes' => ['obligatorio' => 'Complete la fecha de inicio de vigencia.', 'formato' => 'Ingrese una fecha válida (dd/mm/aaaa).'],
            ],
            'vigencia_fin' => [
                'tipo' => 'FEC', 'obligatorio' => true, 'anio_valido' => $anio, 'admite_pasado' => false,
                'mensajes' => ['obligatorio' => 'Complete la fecha de fin de vigencia.', 'formato' => 'Ingrese una fecha válida (dd/mm/aaaa).'],
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed, 2: Esperado, 3: array<string, mixed>}> */
    public static function casos(): array
    {
        return self::casosDelCatalogo('IN-18', self::campos());
    }

    /**
     * @dataProvider casos
     * @param array<string, mixed> $otros
     */
    public function testRegistrarCampoConCadaClaseDeDato(string $campo, mixed $valor, Esperado $esperado, array $otros): void
    {
        $this->comoRol('Gerente');
        $antes = $this->ultimoId('lineas_credito');

        $r = $this->enviar('admin/lineas', self::conValor(Fabrica::datosLinea(), $campo, $valor, $otros));

        if ($esperado->acepta) {
            $this->assertAceptado($r, "{$campo}: {$esperado}");
            $linea = $this->linea($this->idDeLaRedireccion($r));
            $this->assertValorGuardado($esperado->guardado, $linea[$campo], $campo);
            $this->assertSame('Activa', $linea['estado']);

            return;
        }
        $this->assertCampoRechazado($r, $campo, $esperado->mensaje, "{$campo}: {$esperado}");
        $this->assertSame($antes, $this->ultimoId('lineas_credito'), 'Un rechazo no debe crear la línea');
    }

    /** COD-05 y nombre repetido. */
    public function testIdentificacionYNombreUnicos(): void
    {
        $this->comoRol('Gerente');
        $primera = $this->fabrica->linea();

        $this->assertCampoRechazado($this->enviar('admin/lineas', ['codigo' => mb_strtolower($primera['datos']['codigo'])] + Fabrica::datosLinea()), 'codigo', 'Ya existe una línea con esa identificación.');
        $this->assertCampoRechazado($this->enviar('admin/lineas', ['nombre' => $primera['datos']['nombre']] + Fabrica::datosLinea()), 'nombre', 'Ya existe una línea con ese nombre.');
    }

    /** Reglas que cruzan campos: tipos de cliente, categorías, documentación y vigencia (RN-11, RN-14, D-14). */
    public function testReglasEntreCampos(): void
    {
        $this->comoRol('Gerente');
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $casos = [
            'ningún tipo de cliente' => [['admite_persona_humana' => '', 'admite_persona_juridica' => ''], 'tipos_cliente', 'Seleccione al menos un tipo de cliente admitido.'],
            'sin categorías' => [['categorias' => []], 'categorias', 'Seleccione al menos una categoría de ingresos admitida.'],
            'categorías inexistentes' => [['categorias' => ['Inexistente', ['x']]], 'categorias', 'Seleccione al menos una categoría de ingresos admitida.'],
            'categoría sin documentación' => [['requisitos' => ['RelacionDependencia' => [], 'Empresa' => ['DOC_IDENTIDAD']]], 'requisitos', 'Indique la documentación exigida para: Relación de dependencia.'],
            'documentación inexistente' => [['requisitos' => ['RelacionDependencia' => ['NO_EXISTE'], 'Empresa' => ['DOC_IDENTIDAD']]], 'requisitos', 'Indique la documentación exigida para: Relación de dependencia.'],
            'sin documento de identidad' => [['requisitos' => ['RelacionDependencia' => ['RECIBO_SUELDO'], 'Empresa' => ['DOC_IDENTIDAD']]], 'requisitos', 'El documento de identidad es obligatorio en todas las categorías; falta en: Relación de dependencia.'],
            'fin antes del inicio' => [['vigencia_inicio' => $hoy->addDays(10)->format('d/m/Y'), 'vigencia_fin' => $hoy->addDays(5)->format('d/m/Y')], 'vigencia_fin', 'La fecha de fin no puede ser anterior a la de inicio (RN-14).'],
            'fin pasado' => [['vigencia_inicio' => $hoy->subDays(10)->format('d/m/Y'), 'vigencia_fin' => $hoy->subDays(1)->format('d/m/Y')], 'vigencia_fin', 'La fecha de fin de vigencia ya pasó.'],
            'máximo igual al mínimo' => [['monto_minimo' => '100.000', 'monto_maximo' => '100.000,00'], 'monto_maximo', 'El importe máximo debe ser mayor que el mínimo (RN-14, D-23).'],
            'cuotas iguales' => [['cuotas_minimas' => '12', 'cuotas_maximas' => '12'], 'cuotas_maximas', 'La cantidad máxima de cuotas debe ser mayor que la mínima (RN-14, D-23).'],
            'edades iguales' => [['edad_minima' => '30', 'edad_maxima' => '30'], 'edad_maxima', 'La edad máxima debe ser mayor que la mínima (RN-14, D-23).'],
        ];
        foreach ($casos as $caso => [$cambios, $campo, $mensaje]) {
            $this->assertCampoRechazado($this->enviar('admin/lineas', $cambios + Fabrica::datosLinea()), $campo, $mensaje, $caso);
        }

        // Fin de vigencia hoy: se acepta. Categorías de un tipo de cliente no admitido: se descartan
        $r = $this->enviar('admin/lineas', ['vigencia_fin' => $hoy->format('d/m/Y'), 'admite_persona_juridica' => ''] + Fabrica::datosLinea());
        $this->assertAceptado($r, 'fin hoy y solo personas humanas');
        $id = $this->idDeLaRedireccion($r);
        $categorias = array_column($this->db->query(
            'SELECT cc.categoria_ingresos FROM dbo.condiciones_categorias cc JOIN dbo.versiones_condiciones v ON v.id = cc.version_condiciones_id WHERE v.linea_credito_id = ?',
            [$id],
        )->getResultArray(), 'categoria_ingresos');
        $this->assertSame(['RelacionDependencia'], $categorias);
        $linea = $this->linea($id);
        $this->assertNull($linea['antiguedad_minima_meses'], 'Sin personas jurídicas, la antigüedad no se guarda');
    }

    /** El gasto administrativo porcentual ignora el importe fijo y al revés (D-28). */
    public function testTipoDeGastoAdministrativo(): void
    {
        $this->comoRol('Gerente');
        $r = $this->enviar('admin/lineas', ['tipo_gasto_administrativo' => 'Porcentual', 'gasto_administrativo_pct' => '0,5', 'gasto_administrativo_cuota' => 'no es un importe'] + Fabrica::datosLinea());
        $this->assertAceptado($r);
        $l = $this->linea($this->idDeLaRedireccion($r));
        $this->assertValorGuardado('0.00', $l['gasto_administrativo_cuota']);
        $this->assertValorGuardado('0.005', $l['gasto_administrativo_pct']);

        $r = $this->enviar('admin/lineas', ['tipo_gasto_administrativo' => 'Fijo', 'gasto_administrativo_pct' => 'no es un porcentaje'] + Fabrica::datosLinea());
        $this->assertAceptado($r);
        $this->assertNull($this->linea($this->idDeLaRedireccion($r))['gasto_administrativo_pct']);
    }

    // ── IN-19 Modificar ──────────────────────────────────────────────────────────────────────────────────────────

    public function testModificarFechaDeVigenciaMotivoYControles(): void
    {
        $this->comoRol('Gerente');
        $l = $this->fabrica->linea();
        $hoy = Time::now(config('Sigccr')->zonaNegocio);
        $base = ['tna' => '70', 'motivo' => 'Ajuste de la tasa de mercado', 'fecha_vigencia' => $hoy->format('d/m/Y'), 'codigo' => 'OTRO-CODIGO'] + $l['datos'];
        $version = fn (): string => (string) $this->linea($l['id'])['version_fila'];
        $casos = [
            'fecha vacía' => ['fecha_vigencia', '', 'Ingrese la fecha de entrada en vigencia de la nueva versión (dd/mm/aaaa).'],
            'fecha imposible' => ['fecha_vigencia', '30/02/2030', 'Ingrese la fecha de entrada en vigencia de la nueva versión (dd/mm/aaaa).'],
            'fecha como lista' => ['fecha_vigencia', ['01/01/2030'], 'Ingrese la fecha de entrada en vigencia de la nueva versión (dd/mm/aaaa).'],
            'fecha de ayer' => ['fecha_vigencia', $hoy->subDays(1)->format('d/m/Y'), 'La nueva versión no puede regir desde una fecha anterior a hoy: no se admite retroactividad.'],
            'motivo corto' => ['motivo', 'Baja', 'Indique el motivo de la operación (al menos 5 caracteres).'],
            'motivo de 1001' => ['motivo', str_repeat('m', 1001), 'El motivo admite hasta 1000 caracteres.'],
        ];
        foreach ($casos as $caso => [$campo, $valor, $mensaje]) {
            $r = $this->enviar("admin/lineas/{$l['id']}/editar", self::conValor($base + ['version_fila' => $version()], $campo, $valor));
            $this->assertCampoRechazado($r, $campo, $mensaje, $caso);
        }
        $leida = $version();
        $this->assertAceptado($this->enviar("admin/lineas/{$l['id']}/editar", $base + ['version_fila' => $leida]), 'fecha de hoy');
        $linea = $this->linea($l['id']);
        $this->assertSame(2, (int) $linea['numero'], 'Se crea la versión 2');
        $this->assertValorGuardado('0.70', $linea['tna']);
        $this->assertSame($l['datos']['codigo'], $linea['codigo'], 'La identificación no cambia');

        $this->enviar("admin/lineas/{$l['id']}/editar", ['tna' => '71'] + $base + ['version_fila' => $leida]);
        $this->assertSame(ConflictoConcurrenciaException::MENSAJE, $_SESSION['error'] ?? null, 'IN-C06');

        $otra = $this->fabrica->linea();
        $this->assertCampoRechazado($this->enviar("admin/lineas/{$l['id']}/editar", ['nombre' => $otra['datos']['nombre']] + $base + ['version_fila' => $version()]), 'nombre', 'Ya existe otra línea con ese nombre.');
    }

    // ── IN-20 Suspender y reactivar ──────────────────────────────────────────────────────────────────────────────

    public function testSuspenderYReactivar(): void
    {
        $this->comoRol('Gerente');
        $l = $this->fabrica->linea();
        $version = fn (): string => (string) $this->linea($l['id'])['version_fila'];

        foreach (['', 'Baja', 'a    b', ['Motivo como lista']] as $motivo) {
            $this->assertCampoRechazado($this->enviar("admin/lineas/{$l['id']}/suspender", ['motivo' => $motivo, 'version_fila' => $version()]), 'motivo', 'Indique el motivo de la operación (al menos 5 caracteres).', var_export($motivo, true));
        }
        $this->enviar("admin/lineas/{$l['id']}/reactivar", ['motivo' => 'No corresponde', 'version_fila' => $version()]);
        $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'Reactivar una línea activa se rechaza');

        $this->assertAceptado($this->enviar("admin/lineas/{$l['id']}/suspender", ['motivo' => 'Revisión de la cartera', 'version_fila' => $version()]));
        $this->assertSame('Suspendida', $this->linea($l['id'])['estado']);
        $this->assertSame(1, $this->contar('lineas_credito', ['id' => $l['id']]), 'Baja lógica: la línea se conserva');
        $this->enviar("admin/lineas/{$l['id']}/suspender", ['motivo' => 'Otra vez', 'version_fila' => $version()]);
        $this->assertNotSame('', (string) ($_SESSION['error'] ?? ''), 'Suspender una línea suspendida se rechaza');

        $this->assertAceptado($this->enviar("admin/lineas/{$l['id']}/reactivar", ['motivo' => 'Fin de la revisión', 'version_fila' => $version()]));
        $this->assertSame('Activa', $this->linea($l['id'])['estado']);
    }

    // ── IN-21 Localizar líneas e IN-22 Consultar alertas ─────────────────────────────────────────────────────────

    public function testBusquedasConCadaClaseDeFiltro(): void
    {
        $this->comoRol('Gerente');
        $l = $this->fabrica->linea();
        $casos = [
            'admin/lineas' => [[], ['texto' => ['x']], ['texto' => ['x'], 'estado' => ['Activa'], 'categoria' => ['Empresa']], ['texto' => '%'], ['texto' => '_'], ['estado' => 'Inexistente'], ['categoria' => 'Inexistente'], ['texto' => '<script>alert(1)</script>']],
        ];
        $this->comoRol('Administrador');
        $casos['admin/alertas'] = [[], ['estado' => ['Pendiente']], ['estado' => 'Inexistente'], ['tipo' => 'Inexistente'], ['desde' => '31/02/2026'], ['hasta' => 'hoy'], ['desde' => ['x'], 'hasta' => ['y']], ['desde' => '01/01/2026', 'hasta' => '31/12/2026'], ['tipo' => '<script>alert(1)</script>']];
        foreach ($casos as $ruta => $filtros) {
            if ($ruta === 'admin/lineas') {
                $this->comoRol('Gerente');
            } else {
                $this->comoRol('Administrador');
            }
            foreach ($filtros as $f) {
                $r = $this->pedir($ruta, $f);
                $r->assertOK();
                $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $r->response()->getBody(), $ruta . ' ' . json_encode($f));
            }
        }
        $this->comoRol('Gerente');
        $this->assertStringNotContainsString($l['datos']['codigo'], (string) $this->pedir('admin/lineas', ['texto' => '%'])->response()->getBody());
    }

    /** F-52 e IN-C08: una alerta de otro rol, o inexistente, responde el mismo mensaje sin mostrar su contenido. */
    public function testAlertaDeOtroRolOInexistente(): void
    {
        $id = (new \App\Services\Comunes\AlertaService($this->db))->generar('Seguridad', 'Alerta de prueba para el Administrador', 'Revisar', 'Administrador');
        $this->comoRol('Cajero');
        foreach ([$id, 999999999] as $alerta) {
            $r = $this->pedir("admin/alertas/{$alerta}");
            $r->assertRedirectTo(site_url('admin/alertas'));
            $this->assertSame('La alerta no existe o no está dirigida a usted.', $_SESSION['error'] ?? null, (string) $alerta);
        }
        $this->comoRol('Administrador');
        $this->pedir("admin/alertas/{$id}")->assertOK();
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> la línea con su versión de condiciones más reciente */
    private function linea(int $id): array
    {
        return $this->db->query(
            'SELECT l.id, l.codigo, l.nombre, l.descripcion, l.estado, l.version_fila, v.* FROM dbo.lineas_credito l '
            . 'CROSS APPLY (SELECT TOP 1 numero, tna, gasto_otorgamiento_pct, gasto_administrativo_cuota, tipo_gasto_administrativo, gasto_administrativo_pct, '
            . 'monto_minimo, monto_maximo, cuotas_minimas, cuotas_maximas, edad_minima, edad_maxima, antiguedad_minima_meses, admite_persona_humana, '
            . 'admite_persona_juridica, vigencia_inicio, vigencia_fin FROM dbo.versiones_condiciones WHERE linea_credito_id = l.id ORDER BY numero DESC) v WHERE l.id = ?',
            [$id],
        )->getRowArray();
    }

    private function idDeLaRedireccion(TestResponse $r): int
    {
        $this->assertMatchesRegularExpression('#/admin/lineas/(\d+)$#', $r->response()->getHeaderLine('Location'));
        preg_match('#/admin/lineas/(\d+)$#', $r->response()->getHeaderLine('Location'), $m);

        return (int) $m[1];
    }
}
