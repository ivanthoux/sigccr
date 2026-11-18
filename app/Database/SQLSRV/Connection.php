<?php

declare(strict_types=1);

namespace App\Database\SQLSRV;

use CodeIgniter\Database\RawSql;
use Stringable;

/**
 * Controlador SQLSRV del SIG-CCR (D-49, H-09).
 *
 * CodeIgniter escribe los textos como literales '...' y SQL Server los interpreta en la página de códigos de la
 * intercalación de la base (Modern_Spanish_CI_AI, Latin-1): las letras con tilde y la eñe se conservan, pero un emoji,
 * una «ł», una «ș» o un ideograma se guardaban como «?» aunque la columna fuera NVARCHAR. Un texto con caracteres fuera
 * de ASCII se escribe como N'...', que SQL Server recibe en Unicode. Los textos ASCII (correos, códigos, documentos)
 * siguen como '...', para que las comparaciones con columnas VARCHAR usen sus índices sin conversión.
 *
 * Config\Database usa este controlador en lugar de SQLSRV en todos los grupos.
 */
class Connection extends \CodeIgniter\Database\SQLSRV\Connection
{
    /** @param array<string, mixed> $params */
    public function __construct(array $params)
    {
        parent::__construct($params);
        // La plataforma sigue siendo SQLSRV: la usan las funciones sqlsrv_*, Forge, Utils y el manejador de sesiones
        $this->DBDriver = 'SQLSRV';
    }

    /**
     * @param mixed $str
     * @return mixed
     */
    public function escape($str)
    {
        if ($str instanceof Stringable && !$str instanceof RawSql) {
            $str = (string) $str;
        }
        if (is_string($str) && preg_match('/[^\x00-\x7F]/', $str) === 1) {
            return "N'" . $this->escapeString($str) . "'";
        }

        return parent::escape($str);
    }
}
