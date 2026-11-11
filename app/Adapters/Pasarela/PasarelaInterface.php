<?php

declare(strict_types=1);

namespace App\Adapters\Pasarela;

/**
 * Pasarela de pagos (proveedor de referencia: Mercado Pago). RNF-05, RNF-08, RN-30, RN-54.
 */
interface PasarelaInterface
{
    /**
     * Crea una orden de importe fijo con vencimiento al fin del día local.
     *
     * @return array{referencia_externa: string, url_pago: string}
     */
    public function crearOrden(string $numeroOrden, string $importe, string $descripcion, \DateTimeImmutable $vence): array;

    /**
     * Consulta el estado de los pagos de una orden (CU-90, CU-91).
     *
     * @return list<array{id_externo: string, estado: string, importe: string, moneda: string, fecha_efectiva: string}>
     */
    public function consultarPagos(string $referenciaExterna): array;

    /**
     * Valida la firma o el secreto compartido de una notificación recibida (RNF-08).
     *
     * @param array<string, string> $cabeceras
     */
    public function firmaValida(string $cuerpo, array $cabeceras): bool;

    /**
     * Solicita la devolución total o parcial de una transacción aprobada (CU-78, RN-73). El identificador de operación
     * es único: repetirlo no genera una segunda devolución.
     *
     * @return array{estado: string, id_devolucion: ?string, detalle: ?string}
     */
    public function devolver(string $idTransaccion, string $importe, string $idOperacion): array;

    /**
     * Estado de una devolución solicitada, para conciliar las de resultado incierto (CU-91). Null si la pasarela no
     * la registra.
     *
     * @return array{estado: string, id_devolucion: ?string, detalle: ?string}|null
     */
    public function consultarDevolucion(string $idOperacion): ?array;
}
