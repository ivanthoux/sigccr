<?php

/**
 * Ejecutor mínimo compatible con las aserciones de PHPUnit usadas en tests/unit/Finanzas.
 * Permite verificar el motor financiero sin Composer (por ejemplo, en un entorno sin red).
 * En el proyecto se ejecuta con PHPUnit:  vendor/bin/phpunit --testsuite Finanzas
 *
 * Uso: php tests/_local/run.php
 */

namespace PHPUnit\Framework {
    class AssertionFailedError extends \Exception
    {
    }

    abstract class TestCase
    {
        private ?string $expected = null;
        public int $assertions = 0;

        public function expectException(string $class): void
        {
            $this->expected = $class;
        }

        public function getExpectedException(): ?string
        {
            return $this->expected;
        }

        public function resetExpectedException(): void
        {
            $this->expected = null;
        }

        protected function setUp(): void
        {
        }

        public function runSetUp(): void
        {
            $this->setUp();
        }

        private function fail(string $msg): never
        {
            throw new AssertionFailedError($msg);
        }

        public function assertSame(mixed $exp, mixed $act, string $msg = ''): void
        {
            $this->assertions++;
            if ($exp !== $act) {
                $this->fail(($msg ? "$msg: " : '') . 'esperado ' . var_export($exp, true) . ', obtenido ' . var_export($act, true));
            }
        }

        public function assertEquals(mixed $exp, mixed $act, string $msg = ''): void
        {
            $this->assertions++;
            if ($exp != $act) {
                $this->fail(($msg ? "$msg: " : '') . 'esperado ' . var_export($exp, true) . ', obtenido ' . var_export($act, true));
            }
        }

        public function assertTrue(mixed $c, string $msg = ''): void
        {
            $this->assertions++;
            if ($c !== true) {
                $this->fail($msg ?: 'se esperaba true');
            }
        }

        public function assertFalse(mixed $c, string $msg = ''): void
        {
            $this->assertions++;
            if ($c !== false) {
                $this->fail($msg ?: 'se esperaba false');
            }
        }

        public function assertCount(int $n, \Countable|array $a, string $msg = ''): void
        {
            $this->assertions++;
            if (count($a) !== $n) {
                $this->fail(($msg ? "$msg: " : '') . "se esperaban $n elementos, hay " . count($a));
            }
        }

        public function assertNull(mixed $v, string $msg = ''): void
        {
            $this->assertions++;
            if ($v !== null) {
                $this->fail($msg ?: 'se esperaba null');
            }
        }

        public function assertLessThanOrEqual(mixed $max, mixed $v, string $msg = ''): void
        {
            $this->assertions++;
            if (!($v <= $max)) {
                $this->fail(($msg ? "$msg: " : '') . var_export($v, true) . ' > ' . var_export($max, true));
            }
        }

        public function assertGreaterThan(mixed $min, mixed $v, string $msg = ''): void
        {
            $this->assertions++;
            if (!($v > $min)) {
                $this->fail(($msg ? "$msg: " : '') . var_export($v, true) . ' <= ' . var_export($min, true));
            }
        }
    }
}

namespace {
    require __DIR__ . '/bcmath_polyfill.php';
    spl_autoload_register(static function (string $class): void {
        $base = dirname(__DIR__, 2);
        $map = ['App\\' => $base . '/app/', 'Tests\\Unit\\' => $base . '/tests/unit/'];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });

    $files = glob(dirname(__DIR__) . '/unit/Finanzas/*Test.php');
    $total = $fallas = $aserciones = 0;
    $inicio = microtime(true);
    foreach ($files as $f) {
        require_once $f;
        $class = 'Tests\\Unit\\Finanzas\\' . basename($f, '.php');
        foreach (get_class_methods($class) as $m) {
            if (!str_starts_with($m, 'test')) {
                continue;
            }
            $total++;
            $t = new $class();
            try {
                $t->runSetUp();
                $t->$m();
                if ($t->getExpectedException() !== null) {
                    throw new PHPUnit\Framework\AssertionFailedError('se esperaba la excepción ' . $t->getExpectedException());
                }
                echo '.';
            } catch (Throwable $e) {
                $exp = $t->getExpectedException();
                if ($exp !== null && $e instanceof $exp) {
                    $t->assertions++;
                    echo '.';
                } else {
                    $fallas++;
                    echo "\nFALLA {$class}::{$m}\n  " . get_class($e) . ': ' . $e->getMessage() . "\n  " . $e->getFile() . ':' . $e->getLine() . "\n";
                }
            }
            $aserciones += $t->assertions;
        }
    }
    printf("\n\n%d pruebas, %d aserciones, %d fallas (%.1f s)\n", $total, $aserciones, $fallas, microtime(true) - $inicio);
    exit($fallas > 0 ? 1 : 0);
}
