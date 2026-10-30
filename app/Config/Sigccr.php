<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración propia del SIG-CCR. Cada propiedad puede sobrescribirse en .env con el prefijo "sigccr."
 * (por ejemplo, sigccr.adaptadorPasarela = real).
 */
class Sigccr extends BaseConfig
{
    /** Zona horaria de negocio (3.5.1). Los instantes se guardan en UTC. */
    public string $zonaNegocio = 'America/Argentina/Buenos_Aires';

    /** Modo de mantenimiento del portal de clientes (RNF-13, plan correctivo de R7). */
    public bool $portalEnMantenimiento = false;

    /** Sesiones (RNF-11), en segundos. */
    public int $inactividadPanel = 1800;
    public int $inactividadPortal = 900;
    public int $duracionMaximaSesion = 43200;

    /** Control de intentos (RN-62). */
    public int $intentosPorCuenta = 5;
    public int $ventanaIntentos = 900;
    public int $bloqueoTemporal = 900;
    public int $intentosPorIp = 100;

    /** Adaptadores de integración: 'simulador' o 'real' (RNF-05). */
    public string $adaptadorInformacionCrediticia = 'simulador';
    public string $adaptadorPasarela = 'simulador';
    public string $adaptadorComunicaciones = 'simulador';

    /** Integraciones (RNF-08, RNF-09). Las credenciales se cargan solo desde .env. */
    public int $tiempoEsperaExterno = 10;
    public int $reintentosInformacionCrediticia = 2;
    public string $pasarelaUrl = '';
    public string $pasarelaToken = '';
    public string $pasarelaSecretoNotificaciones = '';
    public string $informacionCrediticiaUrl = '';
    /** Catálogo de localidades censales de georef para geografia:importar (D-18). */
    public string $georefLocalidadesUrl = 'https://infra.datos.gob.ar/georef/localidades-censales.json';
    public string $smtpHost = '';
    public int $smtpPuerto = 587;
    /** tls (STARTTLS, puerto 587) o ssl (puerto 465) */
    public string $smtpCifrado = 'tls';
    public string $smtpUsuario = '';
    public string $smtpClave = '';
    public string $correoRemitente = 'no-responder@sigccr.local';
}
