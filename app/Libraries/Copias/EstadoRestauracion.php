<?php

declare(strict_types=1);

namespace App\Libraries\Copias;

/**
 * Estado de la restauración (CU-63, D-45), guardado en writable/copias y no en la base, porque la restauración la
 * reemplaza. Mientras está Pendiente o EnCurso, el sistema está en modo mantenimiento (paso 8). Al terminar queda el
 * resultado en ultima_restauracion.json para el Administrador.
 */
final class EstadoRestauracion
{
    public function __construct(private readonly string $directorio)
    {
    }

    public static function predeterminado(): self
    {
        return new self(WRITEPATH . 'copias');
    }

    /** @return array<string, mixed>|null */
    public function actual(): ?array
    {
        return $this->leer('restauracion.json');
    }

    /** @return array<string, mixed>|null */
    public function ultima(): ?array
    {
        return $this->leer('ultima_restauracion.json');
    }

    public function activa(): bool
    {
        return PoliticaCopias::restauracionActiva($this->actual());
    }

    /** @param array<string, mixed> $estado */
    public function guardar(array $estado): void
    {
        $this->escribir('restauracion.json', $estado);
    }

    /**
     * Fin de la restauración: deja el resultado y sale del modo mantenimiento (paso 11).
     *
     * @param array<string, mixed> $resultado
     */
    public function finalizar(array $resultado): void
    {
        $this->escribir('ultima_restauracion.json', $resultado);
        @unlink($this->directorio . '/restauracion.json');
    }

    /** @return array<string, mixed>|null */
    private function leer(string $archivo): ?array
    {
        $ruta = $this->directorio . '/' . $archivo;
        if (!is_file($ruta)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($ruta), true);

        return is_array($d) ? $d : null;
    }

    /** @param array<string, mixed> $datos */
    private function escribir(string $archivo, array $datos): void
    {
        if (!is_dir($this->directorio)) {
            mkdir($this->directorio, 0770, true);
        }
        $ruta = $this->directorio . '/' . $archivo;
        $tmp = $ruta . '.tmp';
        file_put_contents($tmp, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($tmp, $ruta);
        // El contenedor de procesos escribe como root: el archivo tiene que seguir siendo del usuario de PHP-FPM
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($ruta, 'www-data');
            @chgrp($ruta, 'www-data');
            @chown($this->directorio, 'www-data');
        }
        @chmod($ruta, 0660);
    }
}
