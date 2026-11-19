<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

/** Resultado esperado de una combinación de campo y clase de dato (D-51). */
final class Esperado
{
    /** Mensaje especial: la petición entera se rechaza con HTTP 400 (filtro invalidchars, COM-06). */
    public const PETICION = "\u{0}__peticion_400__";

    /** Valor especial: el campo no se envía (COM-01). */
    public const AUSENTE = "\u{0}__ausente__";

    private function __construct(
        public readonly bool $acepta,
        public readonly ?string $guardado,
        public readonly ?string $mensaje,
    ) {
    }

    /** Se acepta; si $guardado no es null, la columna del campo queda con ese valor. */
    public static function acepta(?string $guardado): self
    {
        return new self(true, $guardado, null);
    }

    /** Se rechaza con ese mensaje en el campo (null: con cualquier mensaje en el campo). */
    public static function rechaza(?string $mensaje): self
    {
        return new self(false, null, $mensaje);
    }

    /** La petición se rechaza con HTTP 400 antes de llegar al controlador (COM-06) y la base no cambia. */
    public static function rechazaPeticion(): self
    {
        return new self(false, null, self::PETICION);
    }

    public function __toString(): string
    {
        if ($this->mensaje === self::PETICION) {
            return 'se rechaza la petición (HTTP 400)';
        }

        return $this->acepta ? 'se acepta' . ($this->guardado === null ? '' : " y se guarda «{$this->guardado}»") : "se rechaza: «{$this->mensaje}»";
    }
}
