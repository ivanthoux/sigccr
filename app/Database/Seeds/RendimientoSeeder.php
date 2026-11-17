<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Exceptions\DatosDePruebaException;
use CodeIgniter\Database\Seeder;

/**
 * Volumen de referencia de RNF-03 para las pruebas de rendimiento: 20.000 clientes, 30.000 créditos liquidados con
 * 300.000 cuotas (una parte en mora) y los usuarios de la prueba de carga (3 Analistas, 6 Cajeros con su caja y 2
 * Gerentes). Los datos se cargan con SQL masivo, sin pasar por los servicios: no representan operaciones reales y solo
 * se usan en la pila de pruebas de rendimiento (tests/rendimiento). Requiere los parámetros y una línea de crédito
 * vigentes, como los que deja la batería de punta a punta.
 *
 *   RENDIMIENTO_CLAVE=<contraseña> SIGCCR_DB_GRUPO=migraciones php spark db:seed RendimientoSeeder
 *
 * Se puede ejecutar una sola vez: si ya existen los clientes de rendimiento, no hace nada.
 */
class RendimientoSeeder extends Seeder
{
    public const CLIENTES = 20000;
    public const CREDITOS = 30000;
    public const CUOTAS_POR_CREDITO = 10;
    public const DOMINIO = 'rendimiento.local';

    private const APELLIDOS = [
        'González', 'Rodríguez', 'Gómez', 'Fernández', 'López', 'Díaz', 'Martínez', 'Pérez', 'García', 'Sánchez',
        'Romero', 'Sosa', 'Álvarez', 'Torres', 'Ruiz', 'Ramírez', 'Flores', 'Acosta', 'Benítez', 'Medina',
        'Suárez', 'Herrera', 'Aguirre', 'Pereyra', 'Gutiérrez', 'Giménez', 'Molina', 'Silva', 'Castro', 'Rojas',
        'Ortiz', 'Núñez', 'Luna', 'Juárez', 'Cabrera', 'Ríos', 'Ferreyra', 'Godoy', 'Morales', 'Domínguez',
        'Moreno', 'Peralta', 'Vega', 'Carrizo', 'Quiroga', 'Castillo', 'Ledesma', 'Muñoz', 'Ojeda', 'Ponce',
    ];
    private const NOMBRES_F = ['María', 'Ana', 'Laura', 'Sofía', 'Lucía', 'Valentina', 'Camila', 'Paula', 'Florencia', 'Agustina', 'Julieta', 'Carla', 'Natalia', 'Romina', 'Silvia', 'Graciela', 'Andrea', 'Victoria', 'Micaela', 'Rocío'];
    private const NOMBRES_M = ['Juan', 'Carlos', 'Jorge', 'Luis', 'Martín', 'Diego', 'Pablo', 'Matías', 'Lucas', 'Nicolás', 'Federico', 'Sebastián', 'Gustavo', 'Ricardo', 'Alejandro', 'Facundo', 'Gonzalo', 'Ezequiel', 'Mariano', 'Hernán'];

    public function run(): void
    {
        $clave = (string) getenv('RENDIMIENTO_CLAVE');
        if (strlen($clave) < 15) {
            throw new DatosDePruebaException('Defina RENDIMIENTO_CLAVE (al menos 15 caracteres) para los usuarios de la prueba de carga.');
        }
        if ((int) $this->db->query("SELECT COUNT(*) AS n FROM dbo.clientes WHERE correo LIKE '%@" . self::DOMINIO . "'")->getRow()->n > 0) {
            echo "Los datos de rendimiento ya están cargados.\n";

            return;
        }
        $t0 = microtime(true);
        $version = $this->db->query(
            "SELECT TOP 1 v.id, v.tna, v.gasto_otorgamiento_pct, v.gasto_administrativo_cuota FROM dbo.versiones_condiciones v "
            . "JOIN dbo.lineas_credito l ON l.id = v.linea_credito_id WHERE l.estado = 'Activa' AND v.vigente_desde <= SYSDATETIMEOFFSET() "
            . "ORDER BY CASE WHEN l.codigo = 'MOTOS' THEN 0 ELSE 1 END, v.vigente_desde DESC, v.id DESC",
        )->getRowArray();
        $morosidad = $this->db->query(
            "SELECT TOP 1 id FROM dbo.versiones_parametros WHERE tipo = 'Morosidad' AND vigente_desde <= SYSDATETIMEOFFSET() ORDER BY vigente_desde DESC, id DESC",
        )->getRowArray();
        if ($version === null || $morosidad === null) {
            throw new DatosDePruebaException('Faltan una línea de crédito activa o los parámetros de morosidad vigentes.');
        }

        $this->db->query('SET NOCOUNT ON');
        $usuarios = $this->usuarios($clave);
        $this->clientes();
        $this->creditos($version, (int) $morosidad['id'], $usuarios['analista'], $usuarios['cajero']);
        printf(
            "Volumen de rendimiento cargado en %.1f s: %d clientes, %d créditos, %d cuotas (%d vencidas).\n",
            microtime(true) - $t0,
            $this->contar("SELECT COUNT(*) AS n FROM dbo.clientes WHERE correo LIKE '%@" . self::DOMINIO . "'"),
            $this->contar("SELECT COUNT(*) AS n FROM dbo.creditos c JOIN dbo.clientes cl ON cl.id = c.cliente_id WHERE cl.correo LIKE '%@" . self::DOMINIO . "'"),
            $this->contar("SELECT COUNT(*) AS n FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.clientes cl ON cl.id = c.cliente_id WHERE cl.correo LIKE '%@" . self::DOMINIO . "'"),
            $this->contar("SELECT COUNT(*) AS n FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.clientes cl ON cl.id = c.cliente_id WHERE cl.correo LIKE '%@" . self::DOMINIO . "' AND q.estado = 'Vencida'"),
        );
    }

    /**
     * Usuarios de la prueba de carga: rend.analista1..3, rend.cajero1..6 (cada uno con su caja REND-01..06) y
     * rend.gerente1..2, todos habilitados con la misma contraseña.
     *
     * @return array{analista: int, cajero: int}
     */
    private function usuarios(string $clave): array
    {
        $hash = password_hash($clave, PASSWORD_ARGON2ID);
        $roles = array_column($this->db->query('SELECT id, codigo FROM dbo.roles')->getResultArray(), 'id', 'codigo');
        $ids = [];
        $dni = 30990000;
        foreach (['analista' => ['AnalistaCredito', 3], 'cajero' => ['Cajero', 6], 'gerente' => ['Gerente', 2]] as $clase => [$rol, $cantidad]) {
            for ($i = 1; $i <= $cantidad; $i++) {
                $correo = "rend.{$clase}{$i}@" . self::DOMINIO;
                $this->db->query(
                    "INSERT INTO dbo.cuentas_acceso (tipo_cuenta, correo, hash_contrasena, correo_verificado, estado) VALUES ('UsuarioInterno', ?, ?, 1, 'Habilitada')",
                    [$correo, $hash],
                );
                $id = (int) $this->db->insertID();
                $this->db->query(
                    "INSERT INTO dbo.usuarios_internos (id, rol_id, nombre, apellido, tipo_documento, nro_documento) VALUES (?, ?, ?, 'Rendimiento', 'DNI', ?)",
                    [$id, (int) $roles[$rol], ucfirst($clase) . " {$i}", (string) $dni++],
                );
                $ids[$clase][] = $id;
                if ($clase === 'cajero') {
                    $codigo = sprintf('REND-%02d', $i);
                    $this->db->query("INSERT INTO dbo.cajas (codigo, denominacion, ubicacion) VALUES (?, ?, 'Prueba de rendimiento')", [$codigo, "Caja de rendimiento {$i}"]);
                    $caja = (int) $this->db->insertID();
                    $this->db->query(
                        "INSERT INTO dbo.asignaciones_caja (caja_id, usuario_id, desde) VALUES (?, ?, CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date))",
                        [$caja, $id],
                    );
                }
            }
        }

        return ['analista' => $ids['analista'][0], 'cajero' => $ids['cajero'][0]];
    }

    /** 20.000 personas humanas con nombres combinados, DNI 40.000.001 en adelante y CUIL con dígito verificador válido. */
    private function clientes(): void
    {
        $filas = [];
        for ($n = 1; $n <= self::CLIENTES; $n++) {
            $mujer = $n % 2 === 0;
            $apellido = self::APELLIDOS[$n % count(self::APELLIDOS)];
            $nombres = $mujer ? self::NOMBRES_F : self::NOMBRES_M;
            $nombre = $nombres[intdiv($n, count(self::APELLIDOS)) % count($nombres)];
            $dni = (string) (40000000 + $n);
            $cuil = $this->cuil($mujer ? '27' : '20', $dni);
            $ingresos = 800000 + ($n % 25) * 100000;
            $filas[] = sprintf(
                "('PersonaHumana', '%s', N'%s, %s', 'rend%05d@%s', '3758 40-%04d', N'Calle %d %d, Apóstoles', %d, 'RelacionDependencia', 0, SYSDATETIMEOFFSET(), DATEADD(day, -%d, CAST(SYSDATETIMEOFFSET() AS date)))",
                $cuil,
                $apellido,
                $nombre,
                $n,
                self::DOMINIO,
                $n % 10000,
                ($n % 40) + 1,
                100 + $n % 3000,
                $ingresos,
                400 + $n % 300,
            );
            if (count($filas) === 1000) {
                $this->insertarClientes($filas);
                $filas = [];
            }
        }
        if ($filas !== []) {
            $this->insertarClientes($filas);
        }
        $this->db->query(
            "INSERT INTO dbo.personas_humanas (cliente_id, tipo_documento, nro_documento, nombre, apellido, fecha_nacimiento, estado_civil, ocupacion, nacionalidad, empleador) "
            . "SELECT id, 'DNI', SUBSTRING(cuit_cuil, 3, 8), LTRIM(SUBSTRING(denominacion, CHARINDEX(',', denominacion) + 1, 80)), LEFT(denominacion, CHARINDEX(',', denominacion) - 1), "
            . "DATEADD(day, -(9000 + id % 9000), CAST(SYSDATETIMEOFFSET() AS date)), 'Soltero', N'Empleado', N'Argentina', N'Empresa de prueba' "
            . "FROM dbo.clientes WHERE correo LIKE '%@" . self::DOMINIO . "'",
        );
    }

    /** @param list<string> $filas */
    private function insertarClientes(array $filas): void
    {
        $this->db->query(
            'INSERT INTO dbo.clientes (tipo_cliente, cuit_cuil, denominacion, correo, telefono, domicilio, ingresos_declarados, categoria_ingresos, persona_expuesta, fecha_declaracion_pep, fecha_alta) VALUES '
            . implode(', ', $filas),
        );
    }

    /**
     * 30.000 créditos de 10 cuotas: los primeros 10.000 clientes tienen dos. Cada crédito se liquidó hace 0 a 8 meses;
     * las cuotas vencidas están pagadas, salvo en uno de cada siete créditos, que tiene sus dos últimas cuotas vencidas
     * impagas (En mora). Cuotas por sistema francés con la TNA de la línea, gasto administrativo e IVA.
     *
     * @param array<string, mixed> $v
     */
    private function creditos(array $v, int $morosidad, int $analista, int $cajero): void
    {
        $d = self::DOMINIO;
        $sql = <<<SQL
            IF OBJECT_ID('tempdb..#m') IS NOT NULL DROP TABLE #m;
            WITH n AS (SELECT TOP ({$this->int(self::CREDITOS)}) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n FROM sys.all_objects a CROSS JOIN sys.all_objects b),
            c AS (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS k FROM dbo.clientes WHERE correo LIKE '%@{$d}')
            SELECT n.n, c.id AS cliente_id, CAST(200000 + (n.n % 16) * 50000 AS DECIMAL(19,2)) AS capital, n.n % 9 AS meses,
                   CASE WHEN n.n % 7 = 0 AND n.n % 9 >= 2 THEN 1 ELSE 0 END AS mora,
                   DATEADD(month, -(n.n % 9), DATEADD(day, -(n.n % 20), CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date))) AS liquidacion,
                   CAST(NULL AS INT) AS solicitud_id, CAST(NULL AS INT) AS credito_id
            INTO #m FROM n JOIN c ON c.k = ((n.n - 1) % {$this->int(self::CLIENTES)}) + 1;

            DECLARE @s TABLE (n INT, id INT);
            MERGE INTO dbo.solicitudes_credito AS t USING #m AS s ON 1 = 0
            WHEN NOT MATCHED THEN INSERT (cliente_id, version_condiciones_id, origen, fecha_registro, importe, cantidad_cuotas, estado, ultimo_movimiento, usuario_id)
                VALUES (s.cliente_id, {$this->int($v['id'])}, 'Independiente', DATEADD(day, -3, CAST(s.liquidacion AS DATETIMEOFFSET(3))), s.capital, {$this->int(self::CUOTAS_POR_CREDITO)}, 'Liquidada', CAST(s.liquidacion AS DATETIMEOFFSET(3)), {$this->int($analista)})
            OUTPUT s.n, inserted.id INTO @s;
            UPDATE m SET solicitud_id = s.id FROM #m m JOIN @s s ON s.n = m.n;

            DECLARE @c TABLE (n INT, id INT);
            MERGE INTO dbo.creditos AS t USING #m AS s ON 1 = 0
            WHEN NOT MATCHED THEN INSERT (cliente_id, version_condiciones_id, version_morosidad_id, origen, capital, tna, cantidad_cuotas, fecha_liquidacion, tolerancia_dias, estado)
                VALUES (s.cliente_id, {$this->int($v['id'])}, {$this->int($morosidad)}, 'Liquidacion', s.capital, {$this->dec($v['tna'])}, {$this->int(self::CUOTAS_POR_CREDITO)}, CAST(s.liquidacion AS DATETIMEOFFSET(3)), 3, CASE WHEN s.mora = 1 THEN 'EnMora' ELSE 'Activo' END)
            OUTPUT s.n, inserted.id INTO @c;
            UPDATE m SET credito_id = c.id FROM #m m JOIN @c c ON c.n = m.n;
            SQL;
        $this->db->query($sql);

        $gastoPct = $this->dec($v['gasto_otorgamiento_pct']);
        $this->db->query(
            "INSERT INTO dbo.liquidaciones (solicitud_id, credito_id, fecha, capital, gasto_otorgamiento, iva_gastos, importe_neto, medio_desembolso, cuenta_destino, comprobante, usuario_id) "
            . "SELECT solicitud_id, credito_id, CAST(liquidacion AS DATETIMEOFFSET(3)), capital, g.gasto, g.iva, capital - g.gasto - g.iva, 'Transferencia', '0720045800000045612303', CONCAT('REND-', n), {$this->int($cajero)} "
            . "FROM #m CROSS APPLY (SELECT ROUND(capital * {$gastoPct}, 2) AS gasto, ROUND(ROUND(capital * {$gastoPct}, 2) * 0.21, 2) AS iva) g",
        );

        // Cuotas por sistema francés: saldo después de la cuota k = C·((1+i)^n − (1+i)^k) / ((1+i)^n − 1)
        $i = (float) $v['tna'] / 12; // la TNA y los porcentajes se guardan como fracción (0,60 = 60 %)
        $gasto = $this->dec($v['gasto_administrativo_cuota']);
        $cuotas = $this->int(self::CUOTAS_POR_CREDITO);
        $this->db->query(
            "WITH k AS (SELECT TOP ({$cuotas}) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS k FROM sys.all_objects), "
            . "q AS (SELECT m.credito_id, m.mora, m.liquidacion, k.k, DATEADD(month, k.k, m.liquidacion) AS vencimiento, "
            . "ROUND(CAST(m.capital AS FLOAT) * (POWER(1 + {$i}, {$cuotas}) - POWER(1 + {$i}, k.k - 1)) / (POWER(1 + {$i}, {$cuotas}) - 1), 2) AS saldo_antes, "
            . "ROUND(CAST(m.capital AS FLOAT) * (POWER(1 + {$i}, {$cuotas}) - POWER(1 + {$i}, k.k)) / (POWER(1 + {$i}, {$cuotas}) - 1), 2) AS saldo_despues, "
            . "(SELECT MAX(k2.k) FROM k k2 WHERE DATEADD(month, k2.k, m.liquidacion) < CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date)) AS vencidas "
            . "FROM #m m CROSS JOIN k), "
            . "r AS (SELECT q.*, CAST(saldo_antes - saldo_despues AS DECIMAL(19,2)) AS capital, CAST(ROUND(saldo_antes * {$i}, 2) AS DECIMAL(19,2)) AS interes, "
            . "CASE WHEN vencimiento >= CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date) THEN 'Pendiente' "
            . "WHEN mora = 1 AND k > ISNULL(vencidas, 0) - 2 THEN 'Vencida' ELSE 'Pagada' END AS estado FROM q) "
            . "INSERT INTO dbo.cuotas (credito_id, numero, vencimiento, capital, interes, gastos, iva, capital_pendiente, interes_pendiente, gastos_pendiente, iva_pendiente, estado) "
            . "SELECT credito_id, k, vencimiento, capital, interes, {$gasto}, ROUND((interes + {$gasto}) * 0.21, 2), "
            . "CASE WHEN estado = 'Pagada' THEN 0 ELSE capital END, CASE WHEN estado = 'Pagada' THEN 0 ELSE interes END, "
            . "CASE WHEN estado = 'Pagada' THEN 0 ELSE {$gasto} END, CASE WHEN estado = 'Pagada' THEN 0 ELSE ROUND((interes + {$gasto}) * 0.21, 2) END, estado FROM r",
        );
        // Créditos sin cuotas vencidas impagas quedan Activos aunque se hayan elegido para la mora (liquidados hace poco)
        $this->db->query("UPDATE c SET estado = 'Activo' FROM dbo.creditos c JOIN #m m ON m.credito_id = c.id WHERE c.estado = 'EnMora' AND NOT EXISTS (SELECT 1 FROM dbo.cuotas q WHERE q.credito_id = c.id AND q.estado = 'Vencida')");
        $this->db->query('DROP TABLE #m');
    }

    private function cuil(string $prefijo, string $dni): string
    {
        foreach ([$prefijo, '23'] as $p) {
            $base = $p . str_pad($dni, 8, '0', STR_PAD_LEFT);
            $suma = 0;
            foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) {
                $suma += (int) $base[$i] * $peso;
            }
            $r = 11 - $suma % 11;
            if ($r === 10) {
                continue;
            }

            return $base . ($r === 11 ? 0 : $r);
        }

        throw new DatosDePruebaException("No se pudo calcular el CUIL del DNI {$dni}.");
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->getRow()->n;
    }

    private function int(mixed $v): string
    {
        return (string) (int) $v;
    }

    private function dec(mixed $v): string
    {
        return number_format((float) $v, 12, '.', '');
    }
}
