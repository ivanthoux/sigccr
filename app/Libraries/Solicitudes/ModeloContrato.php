<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Validacion\Campos;

/**
 * Modelo configurable del contrato de préstamo (CU-106, D-56; RN-78, art. 36 de la Ley 24.240).
 *
 * El Gerente define las cláusulas: su orden, su forma (numerada, sin número o en recuadro), su título y su texto. El
 * texto admite variables entre llaves, que el contrato reemplaza con los datos de cada préstamo ({importe}, {cftea},
 * {plazo_revocacion}…), dos bloques que se ubican en un párrafo propio ({cuadro_cft} y {plan_cuotas}) y negrita
 * entre dobles asteriscos (**texto**). Cada cambio es una versión de parámetros del grupo «Contrato» con fecha de
 * vigencia (RN-15): un contrato conserva la versión vigente al generarse. Sin versiones registradas rige INICIAL, el
 * texto con que se construyó el contrato.
 *
 * Lo que la ley exige no se puede quitar: el costo financiero total destacado y el plan de pago (art. 36 de la Ley
 * 24.240) y el derecho de revocación con su plazo (art. 1110 del Código Civil y Comercial, irrenunciable).
 */
final class ModeloContrato
{
    public const ESTILOS = [
        'numerada' => 'Cláusula numerada',
        'sin_numero' => 'Párrafo sin número',
        'recuadro' => 'Recuadro destacado',
    ];

    public const MAX_CLAUSULAS = 40;
    public const MAX_TITULO = 100;
    public const MAX_TEXTO = 5000;

    /** @var array<string, string> variable => qué muestra */
    public const VARIABLES = [
        'entidad' => 'Razón social de la entidad',
        'entidad_datos' => 'CUIT y domicilio de la entidad, precedidos de coma, si están configurados',
        'cliente' => 'Nombre y apellido, o razón social, del cliente',
        'cliente_identificacion' => 'Documento y CUIL del cliente, o CUIT y representante de la persona jurídica',
        'cliente_domicilio' => 'Domicilio del cliente con su localidad y provincia',
        'cliente_correo' => 'Correo electrónico del cliente',
        'numero_contrato' => 'Número del contrato',
        'numero_solicitud' => 'Número de la solicitud',
        'linea' => 'Línea de crédito',
        'importe' => 'Importe prestado',
        'gasto_otorgamiento' => 'Gasto de otorgamiento',
        'iva_otorgamiento' => 'IVA del gasto de otorgamiento',
        'neto' => 'Importe neto a desembolsar',
        'cftea' => 'Costo financiero total efectivo anual con IVA',
        'tna' => 'Tasa nominal anual',
        'tea' => 'Tasa efectiva anual',
        'tasa_mensual' => 'Tasa efectiva mensual',
        'total_intereses' => 'Total de intereses',
        'total_a_pagar' => 'Total a pagar',
        'cuotas' => 'Cantidad de cuotas',
        'gasto_administrativo' => 'Gasto administrativo de cada cuota, en palabras («un gasto administrativo de $ …»)',
        'tolerancia' => 'Días de tolerancia sin punitorios',
        'punitorio_diario' => 'Tasa punitoria diaria',
        'punitorio_anual' => 'Tasa punitoria nominal anual',
        'plazo_revocacion' => 'Días hábiles del plazo de revocación',
    ];

    /** @var array<string, string> bloque => qué muestra (cada uno en un párrafo propio, una sola vez) */
    public const BLOQUES = [
        'cuadro_cft' => 'Recuadro del costo financiero total, destacado al doble del tamaño del texto',
        'plan_cuotas' => 'Tabla del plan de cuotas: capital, interés, gasto administrativo, IVA y total',
    ];

    /** @var list<array{estilo: string, titulo: string, texto: string}> el contrato con que se construyó el sistema (D-31) */
    public const INICIAL = [
        ['estilo' => 'sin_numero', 'titulo' => 'Partes', 'texto' => '{entidad}{entidad_datos} (en adelante, «la entidad»), y {cliente}, {cliente_identificacion}, con domicilio en {cliente_domicilio} y correo electrónico {cliente_correo} (en adelante, «el cliente»), celebran este contrato de préstamo de consumo.'],
        ['estilo' => 'numerada', 'titulo' => 'Importe', 'texto' => 'La entidad presta al cliente **{importe}**. Del importe se descuentan el gasto de otorgamiento de {gasto_otorgamiento} y su IVA de {iva_otorgamiento}; el importe neto a desembolsar es de **{neto}**, que se transfiere a una cuenta bancaria de titularidad del cliente identificada con su CBU (RN-25, RN-77).'],
        ['estilo' => 'numerada', 'titulo' => 'Costo financiero', 'texto' => "{cuadro_cft}\n\nTasa nominal anual (TNA) fija: {tna}. Tasa efectiva anual (TEA): {tea}. Tasa efectiva mensual: {tasa_mensual}. Total de intereses: {total_intereses}. Total a pagar: {total_a_pagar}."],
        ['estilo' => 'numerada', 'titulo' => 'Devolución', 'texto' => "El préstamo se devuelve en {cuotas} cuotas mensuales y consecutivas por el sistema de amortización francés, con cuota constante. La primera vence a los 30 días corridos del desembolso y las siguientes el mismo día de cada mes; si ese día no es hábil, el siguiente día hábil (RN-52). Cada cuota incluye {gasto_administrativo} y el IVA del interés y del gasto. Los vencimientos definitivos se informan en la constancia de desembolso.\n\n{plan_cuotas}"],
        ['estilo' => 'numerada', 'titulo' => 'Pagos', 'texto' => 'Las cuotas se pagan por transferencia o depósito bancario, con tarjeta de débito o de crédito en la sucursal, o por el medio de pago electrónico habilitado. La entidad no recibe pagos en efectivo (art. 1 de la Ley 25.345, RN-77). Cada pago se imputa a punitorios, intereses, gastos, IVA y capital, en ese orden (RN-29).'],
        ['estilo' => 'numerada', 'titulo' => 'Mora', 'texto' => 'La mora es automática al vencimiento. Si el pago se realiza dentro de los {tolerancia} días corridos posteriores al vencimiento no se cobran punitorios; superado ese plazo, se devenga un interés punitorio diario del {punitorio_diario} ({punitorio_anual} nominal anual) sobre el capital vencido, más su IVA, desde el primer día de atraso, que no supera el 50 % de la tasa compensatoria (art. 769 del Código Civil y Comercial, RN-34).'],
        ['estilo' => 'numerada', 'titulo' => 'Cancelación anticipada', 'texto' => 'El cliente puede cancelar el total del préstamo en cualquier momento sin comisión. Paga las cuotas vencidas con sus punitorios, el capital de las cuotas no vencidas, el interés de la cuota en curso proporcional a los días transcurridos y el IVA correspondiente; no paga los intereses ni los gastos de las cuotas no vencidas (art. 36 de la Ley 24.240, RN-67).'],
        ['estilo' => 'numerada', 'titulo' => 'Información', 'texto' => 'El cliente puede consultar su estado de cuenta, sus comprobantes y este contrato en el portal de autogestión. La entidad informa la situación del cliente a la Central de Deudores del BCRA (RN-84). Los datos personales se tratan conforme a la Ley 25.326 y a la política de tratamiento aceptada por el cliente.'],
        ['estilo' => 'numerada', 'titulo' => 'Jurisdicción', 'texto' => 'Para cualquier controversia son competentes los tribunales del domicilio real del cliente (art. 36 de la Ley 24.240).'],
        ['estilo' => 'recuadro', 'titulo' => '', 'texto' => '**DERECHO DE REVOCACIÓN. El cliente puede revocar este préstamo dentro de los {plazo_revocacion} días hábiles contados desde que recibe este contrato o desde el desembolso, lo que ocurra último, sin costo ni responsabilidad alguna. Para hacerlo, lo comunica a la entidad por cualquier medio y restituye por transferencia el capital recibido más el interés devengado por los días transcurridos, sin gastos ni comisiones. Este derecho no puede renunciarse (arts. 1110 a 1112 del Código Civil y Comercial, RN-79).**'],
        ['estilo' => 'sin_numero', 'titulo' => '', 'texto' => 'Se firman dos ejemplares de un mismo tenor; el cliente recibe uno en este acto.'],
    ];

    /**
     * Cláusulas del formulario (listas paralelas estilo[], titulo[], texto[]) normalizadas, sin las filas vacías.
     *
     * @param array<string, mixed> $datos
     * @return list<array{estilo: string, titulo: string, texto: string}>
     */
    public static function normalizar(array $datos): array
    {
        $lista = static fn (string $k): array => is_array($datos[$k] ?? null) ? array_values($datos[$k]) : [];
        [$estilos, $titulos, $textos] = [$lista('estilo'), $lista('titulo'), $lista('texto')];
        $clausulas = [];
        foreach ($textos as $i => $texto) {
            $t = is_string($texto) ? self::limpiar($texto) : '';
            $titulo = is_string($titulos[$i] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $titulos[$i])) : '';
            $estilo = is_string($estilos[$i] ?? null) ? trim($estilos[$i]) : '';
            if ($t === '' && $titulo === '') {
                continue;
            }
            $clausulas[] = ['estilo' => $estilo, 'titulo' => $titulo, 'texto' => $t];
        }

        return $clausulas;
    }

    /**
     * CU-106 paso 4 (A1 a A3): errores por cláusula («clausula_N_texto», con N desde 1) y del modelo completo
     * («clausulas»). Vacío si el modelo es válido.
     *
     * @param list<array{estilo: string, titulo: string, texto: string}> $clausulas
     * @return array<string, string>
     */
    /**
     * Las tres listas paralelas del formulario tienen un elemento por cláusula (H-32). Si falta un elemento en una,
     * las demás se correrían y el título o el texto de una cláusula quedaría en otra.
     *
     * @param array<string, mixed> $datos
     */
    public static function completas(array $datos): bool
    {
        $largos = array_map(static fn (string $k): int => is_array($datos[$k] ?? null) ? count($datos[$k]) : 0, ['estilo', 'titulo', 'texto']);

        return count(array_unique($largos)) === 1;
    }

    public static function validar(array $clausulas): array
    {
        $e = [];
        if ($clausulas === []) {
            return ['clausulas' => 'El contrato debe tener al menos una cláusula.'];
        }
        if (count($clausulas) > self::MAX_CLAUSULAS) {
            $e['clausulas'] = 'El contrato admite hasta ' . self::MAX_CLAUSULAS . ' cláusulas.';
        }
        $usos = array_fill_keys(array_keys(self::BLOQUES), 0);
        $revocacion = false;
        foreach ($clausulas as $i => $c) {
            $n = $i + 1;
            if (!isset(self::ESTILOS[$c['estilo']])) {
                $e["clausula_{$n}_estilo"] = 'Elija la forma de la cláusula.';
            }
            if ($c['estilo'] === 'numerada' && $c['titulo'] === '') {
                $e["clausula_{$n}_titulo"] = 'Una cláusula numerada lleva título.';
            } elseif (Campos::largo($c['titulo']) > self::MAX_TITULO) {
                // H-16: los largos se cuentan como en el resto del sistema (un emoji ocupa dos)
                $e["clausula_{$n}_titulo"] = 'El título admite hasta ' . self::MAX_TITULO . ' caracteres.';
            }
            if ($c['texto'] === '') {
                $e["clausula_{$n}_texto"] = 'Escriba el texto de la cláusula.';
                continue;
            }
            if (Campos::largo($c['texto']) > self::MAX_TEXTO) {
                $e["clausula_{$n}_texto"] = 'El texto admite hasta ' . number_format(self::MAX_TEXTO, 0, ',', '.') . ' caracteres.';
                continue;
            }
            preg_match_all('/\{([^{}\s]*)\}/u', $c['texto'], $m);
            $desconocidas = array_values(array_unique(array_diff($m[1], array_keys(self::VARIABLES), array_keys(self::BLOQUES))));
            if ($desconocidas !== []) {
                $e["clausula_{$n}_texto"] = 'Variable desconocida: {' . implode('}, {', $desconocidas) . '}. Use las de la lista.';
                continue;
            }
            if (preg_match('/[{}]/u', (string) preg_replace('/\{[^{}\s]*\}/u', '', $c['texto'])) === 1) {
                $e["clausula_{$n}_texto"] = 'Hay una llave sin cerrar: las variables se escriben entre { y }.';
                continue;
            }
            foreach (array_keys(self::BLOQUES) as $b) {
                $veces = substr_count($c['texto'], '{' . $b . '}');
                $usos[$b] += $veces;
                if ($veces > 0 && !self::bloqueSolo($c['texto'], $b)) {
                    $e["clausula_{$n}_texto"] = '{' . $b . '} va solo en un párrafo: deje una línea en blanco antes y después.';
                }
            }
            $revocacion = $revocacion || str_contains($c['texto'], '{plazo_revocacion}');
        }
        foreach ($usos as $b => $veces) {
            if ($veces !== 1) {
                $e['clausulas'] = ($veces === 0 ? 'Falta {' . $b . '}: ' : '{' . $b . '} va una sola vez: ')
                    . ($b === 'cuadro_cft' ? 'el costo financiero total debe informarse destacado (art. 36 de la Ley 24.240).' : 'el contrato debe incluir el plan de pago (art. 36 de la Ley 24.240).');
                break;
            }
        }
        if (!isset($e['clausulas']) && !$revocacion) {
            $e['clausulas'] = 'Falta la cláusula del derecho de revocación con {plazo_revocacion}: el derecho es irrenunciable (art. 1110 del Código Civil y Comercial, RN-79).';
        }

        return $e;
    }

    /**
     * Contrato listo para presentar: cada cláusula con su número (solo las numeradas, desde 1) y sus párrafos, que
     * son texto ya escapado con las variables reemplazadas, o uno de los bloques.
     *
     * @param list<array{estilo: string, titulo: string, texto: string}> $clausulas
     * @param array<string, string> $valores variable => valor (texto plano)
     * @return list<array{estilo: string, numero: ?int, titulo: string, parrafos: list<array{tipo: string, html?: string}>}>
     */
    public static function componer(array $clausulas, array $valores): array
    {
        $resultado = [];
        $numero = 0;
        foreach ($clausulas as $c) {
            $parrafos = [];
            foreach (preg_split('/\n\s*\n/u', $c['texto']) ?: [] as $p) {
                $p = trim($p);
                if ($p === '') {
                    continue;
                }
                if (preg_match('/^\{(' . implode('|', array_keys(self::BLOQUES)) . ')\}$/u', $p, $m) === 1) {
                    $parrafos[] = ['tipo' => $m[1]];
                    continue;
                }
                $parrafos[] = ['tipo' => 'texto', 'html' => self::html($p, $valores)];
            }
            $resultado[] = [
                'estilo' => $c['estilo'], 'numero' => $c['estilo'] === 'numerada' ? ++$numero : null,
                'titulo' => $c['titulo'], 'parrafos' => $parrafos,
            ];
        }

        return $resultado;
    }

    /** Texto de un párrafo: escapado, con **negrita**, saltos de línea simples y las variables reemplazadas. */
    private static function html(string $texto, array $valores): string
    {
        $h = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $h = (string) preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $h);
        $h = (string) preg_replace_callback('/\{([a-z_]+)\}/', static fn (array $m): string => htmlspecialchars((string) ($valores[$m[1]] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $h);

        return nl2br($h, false);
    }

    /** El bloque ocupa un párrafo propio (separado por líneas en blanco). */
    private static function bloqueSolo(string $texto, string $bloque): bool
    {
        foreach (preg_split('/\n\s*\n/u', $texto) ?: [] as $p) {
            if (str_contains($p, '{' . $bloque . '}') && trim($p) !== '{' . $bloque . '}') {
                return false;
            }
        }

        return true;
    }

    /** Saltos de línea uniformes, sin espacios al final de cada línea ni más de una línea en blanco seguida. */
    private static function limpiar(string $texto): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $texto);
        // Los espacios y tabulaciones repetidos se reducen a uno: el contrato no los muestra
        $t = (string) preg_replace('/[ \t]+/u', ' ', $t);
        $t = (string) preg_replace('/ ?\n ?/u', "\n", $t);
        $t = (string) preg_replace('/\n{3,}/u', "\n\n", $t);

        return trim($t);
    }
}
