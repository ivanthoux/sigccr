<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Política de tratamiento de datos personales (Ley 25.326, arts. 4, 5 y 6; Res. AAIP 14/2018; RNF-14, D-64).
 *
 * El texto es un modelo: los datos de la entidad salen de la configuración (Config\Sigccr) y la entidad debe hacerlo
 * revisar por su asesor legal antes de ponerlo en producción. Esta clase no usa la base ni el framework, por lo que se
 * prueba sin entorno.
 */
final class PoliticaDatos
{
    /** Plazos de la Ley 25.326 (arts. 14 y 16) que la política informa y CU-108 controla. */
    public const DIAS_ACCESO = 10;
    public const DIAS_RECTIFICACION = 5;

    /** Pre-registros sin completar: se eliminan pasado este plazo (art. 4, inc. 7; D-64). */
    public const DIAS_CONSERVACION_PRE_REGISTRO = 90;

    /** Leyenda exigida en los formularios de recolección de datos (Res. AAIP 14/2018). */
    public const LEYENDA_AAIP = 'El titular de los datos personales tiene la facultad de ejercer el derecho de acceso a los mismos en forma gratuita '
        . 'a intervalos no inferiores a seis meses, salvo que se acredite un interés legítimo al efecto conforme lo establecido en el artículo 14, '
        . 'inciso 3 de la Ley N.º 25.326. La Agencia de Acceso a la Información Pública, en su carácter de Órgano de Control de la Ley N.º 25.326, '
        . 'tiene la atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento '
        . 'de las normas vigentes en materia de protección de datos personales.';

    /**
     * @param array{razonSocial: string, cuit: string, domicilio: string, correo: string} $entidad
     * @return list<array{titulo: string, parrafos: list<string>}>
     */
    public static function secciones(array $entidad): array
    {
        $contacto = $entidad['correo'] !== '' ? " o por correo electrónico a {$entidad['correo']}" : '';
        $domicilio = $entidad['domicilio'] !== '' ? $entidad['domicilio'] : 'el domicilio de la sucursal';
        $cuit = $entidad['cuit'] !== '' ? ", CUIT {$entidad['cuit']}" : '';

        return [
            ['titulo' => 'Responsable del tratamiento', 'parrafos' => [
                "{$entidad['razonSocial']}{$cuit}, con domicilio en {$domicilio}, es la responsable de la base de datos de clientes y solicitantes de crédito.",
            ]],
            ['titulo' => 'Qué datos se recogen', 'parrafos' => [
                'Datos de identificación (nombre y apellido o razón social, documento, CUIT o CUIL, fecha de nacimiento, estado civil), de contacto (correo electrónico, teléfono, domicilio), '
                . 'laborales y económicos (ocupación, empleador, categoría e ingresos declarados), la declaración de condición de persona expuesta políticamente y la documentación del legajo.',
                'Además, los datos de las operaciones (solicitudes, créditos, cuotas, pagos y comunicaciones) y los registros de acceso al sistema. '
                . 'No se recogen datos sensibles (origen racial o étnico, opiniones políticas, convicciones religiosas o morales, afiliación sindical, salud o vida sexual).',
            ]],
            ['titulo' => 'Para qué se usan', 'parrafos' => [
                'Para evaluar y otorgar créditos, administrar la cartera, cobrar las cuotas y cumplir las obligaciones legales de la entidad, entre ellas la prevención del lavado de activos y el régimen informativo '
                . 'del Banco Central de la República Argentina (BCRA). Los datos no se usan para otra finalidad incompatible con esas.',
                'Los datos de contacto se usan también para enviar comunicaciones del servicio (códigos, avisos de vencimiento, comprobantes). '
                . 'Las ofertas y novedades comerciales solo se envían a quien lo aceptó, y puede revocarlo en cualquier momento desde el portal, en Mi perfil → Preferencias de comunicación.',
            ]],
            ['titulo' => 'Carácter de las respuestas', 'parrafos' => [
                'Los datos pedidos en los formularios son obligatorios para registrarse como cliente y para solicitar un crédito; los marcados como opcionales (por ejemplo, el teléfono) no lo son. '
                . 'Si no se los proporciona, o se los proporciona de manera inexacta, la entidad no puede registrar al cliente ni evaluar la solicitud.',
            ]],
            ['titulo' => 'A quién se comunican', 'parrafos' => [
                'La entidad consulta la Central de Deudores del BCRA para evaluar la solicitud e informa a ese organismo la situación de sus deudores, como la normativa exige. '
                . 'Los pagos electrónicos se procesan con una pasarela de pagos y las comunicaciones se envían con un proveedor de correo electrónico, que tratan los datos solo por cuenta de la entidad y para esos fines. '
                . 'Fuera de esos casos y de los requerimientos de autoridades competentes, los datos no se ceden a terceros.',
            ]],
            ['titulo' => 'Cuánto tiempo se conservan', 'parrafos' => [
                'Los datos de los clientes se conservan mientras dure la relación con la entidad y, después, durante los plazos que fijan las normas contables, de prevención del lavado de activos y de información crediticia. '
                . 'Los pre-registros del portal que no se completan con el alta en la sucursal se eliminan a los ' . self::DIAS_CONSERVACION_PRE_REGISTRO . ' días.',
            ]],
            ['titulo' => 'Cómo se protegen', 'parrafos' => [
                'El acceso al sistema exige cuenta y contraseña, se limita por rol y queda registrado; las comunicaciones viajan cifradas y las copias de seguridad se guardan cifradas. '
                . 'El personal de la entidad está obligado a guardar secreto sobre los datos a los que accede.',
            ]],
            ['titulo' => 'Sus derechos', 'parrafos' => [
                'Puede pedir acceso a sus datos, su rectificación, actualización o supresión, en el portal (Mi perfil → Ejercer mis derechos), en la sucursal'
                . "{$contacto}. El acceso es gratuito y la entidad responde en un plazo de " . self::DIAS_ACCESO . ' días corridos; la rectificación, la actualización o la supresión se hacen en un plazo de '
                . self::DIAS_RECTIFICACION . ' días hábiles. La supresión no procede cuando los datos deben conservarse por una obligación legal o por una relación contractual vigente.',
            ]],
            ['titulo' => 'Decisiones automatizadas', 'parrafos' => [
                'La evaluación crediticia usa un procesamiento automático de los antecedentes del solicitante. Si la solicitud resulta rechazada por ese procesamiento, puede pedir que una persona de la entidad la revise (artículo 20 de la Ley 25.326), '
                . 'desde los mismos canales que para el resto de sus derechos.',
            ]],
            ['titulo' => 'Reclamos', 'parrafos' => [self::LEYENDA_AAIP]],
        ];
    }
}
