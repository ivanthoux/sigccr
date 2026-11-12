<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Comunicaciones\ComunicacionesInterface;
use App\Adapters\Comunicaciones\SimuladorComunicaciones;
use App\Adapters\Comunicaciones\SmtpComunicaciones;
use App\Adapters\InformacionCrediticia\BcraInformacionCrediticia;
use App\Adapters\InformacionCrediticia\InformacionCrediticiaInterface;
use App\Adapters\InformacionCrediticia\SimuladorInformacionCrediticia;
use App\Adapters\Padron\PadronInterface;
use App\Adapters\Padron\SimuladorPadron;
use App\Adapters\Pasarela\PasarelaInterface;
use App\Adapters\Pasarela\SimuladorPasarela;
use RuntimeException;

/**
 * Selecciona la implementación de cada integración según la configuración (sigccr.adaptador* en .env).
 * Las implementaciones reales se agregan en la iteración que construye cada integración (2.2.1).
 */
final class Fabrica
{
    public static function informacionCrediticia(): InformacionCrediticiaInterface
    {
        $c = config('Sigccr');

        return match ($c->adaptadorInformacionCrediticia) {
            'simulador' => new SimuladorInformacionCrediticia(),
            'real', 'bcra' => new BcraInformacionCrediticia(
                $c->informacionCrediticiaUrl !== '' ? $c->informacionCrediticiaUrl : BcraInformacionCrediticia::URL,
                $c->tiempoEsperaExterno,
                $c->reintentosInformacionCrediticia,
            ),
            default => throw new RuntimeException('Adaptador de información crediticia desconocido: use simulador o real.'),
        };
    }

    public static function pasarela(): PasarelaInterface
    {
        return match (config('Sigccr')->adaptadorPasarela) {
            'simulador' => new SimuladorPasarela(),
            default => throw new RuntimeException('Adaptador real de la pasarela pendiente (iteración 3).'),
        };
    }

    public static function comunicaciones(): ComunicacionesInterface
    {
        return match (config('Sigccr')->adaptadorComunicaciones) {
            'simulador' => new SimuladorComunicaciones(),
            'real' => new SmtpComunicaciones(),
            default => throw new RuntimeException('Adaptador de comunicaciones desconocido: use simulador o real.'),
        };
    }

    public static function padron(): PadronInterface
    {
        return match (config('Sigccr')->adaptadorPadron) {
            'simulador' => new SimuladorPadron(),
            default => throw new RuntimeException('Adaptador del padrón desconocido: use simulador (el servicio real requiere el convenio con ARCA).'),
        };
    }
}
