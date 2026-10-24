<?php

/**
 * Reemplazo mínimo de BCMath en PHP puro, SOLO para ejecutar las pruebas en un entorno
 * sin la extensión. En el contenedor de la aplicación se usa la extensión bcmath real.
 * Implementa bcadd, bcsub, bcmul, bcdiv, bccomp y bcpow (exponente entero >= 0)
 * con truncamiento a la escala indicada, igual que BCMath.
 */
if (function_exists('bcadd')) {
    return;
}

function _bc_parse(string $n): array
{
    $n = trim($n);
    $neg = false;
    if ($n !== '' && ($n[0] === '-' || $n[0] === '+')) {
        $neg = $n[0] === '-';
        $n = substr($n, 1);
    }
    if ($n === '' ) { $n = '0'; }
    if (!preg_match('/^\d*(\.\d*)?$/', $n)) {
        throw new ValueError("bcmath: número inválido '$n'");
    }
    $parts = explode('.', $n, 2);
    $int = ltrim($parts[0], '0');
    $frac = $parts[1] ?? '';
    return [$neg, $int === '' ? '0' : $int, $frac];
}

/** Convierte a entero escalado (string de dígitos) con $s decimales, truncando. */
function _bc_scaled(string $n, int $s): array
{
    [$neg, $int, $frac] = _bc_parse($n);
    $frac = substr(str_pad($frac, $s, '0'), 0, $s);
    $digits = ltrim($int . $frac, '0');
    if ($digits === '') { $digits = '0'; $neg = false; }
    return [$neg, $digits];
}

function _bc_cmp_abs(string $a, string $b): int
{
    $a = ltrim($a, '0') ?: '0';
    $b = ltrim($b, '0') ?: '0';
    if (strlen($a) !== strlen($b)) { return strlen($a) <=> strlen($b); }
    return strcmp($a, $b) <=> 0;
}

function _bc_add_abs(string $a, string $b): string
{
    $r = ''; $c = 0;
    $i = strlen($a) - 1; $j = strlen($b) - 1;
    while ($i >= 0 || $j >= 0 || $c) {
        $d = ($i >= 0 ? (int) $a[$i--] : 0) + ($j >= 0 ? (int) $b[$j--] : 0) + $c;
        $r = ($d % 10) . $r; $c = intdiv($d, 10);
    }
    return ltrim($r, '0') ?: '0';
}

/** a - b con a >= b. */
function _bc_sub_abs(string $a, string $b): string
{
    $r = ''; $br = 0;
    $i = strlen($a) - 1; $j = strlen($b) - 1;
    while ($i >= 0) {
        $d = (int) $a[$i--] - ($j >= 0 ? (int) $b[$j--] : 0) - $br;
        if ($d < 0) { $d += 10; $br = 1; } else { $br = 0; }
        $r = $d . $r;
    }
    return ltrim($r, '0') ?: '0';
}

function _bc_mul_abs(string $a, string $b): string
{
    $la = strlen($a); $lb = strlen($b);
    $res = array_fill(0, $la + $lb, 0);
    for ($i = $la - 1; $i >= 0; $i--) {
        $da = (int) $a[$i];
        if ($da === 0) { continue; }
        for ($j = $lb - 1; $j >= 0; $j--) {
            $res[$i + $j + 1] += $da * (int) $b[$j];
        }
    }
    for ($k = $la + $lb - 1; $k > 0; $k--) {
        $res[$k - 1] += intdiv($res[$k], 10);
        $res[$k] %= 10;
    }
    return ltrim(implode('', $res), '0') ?: '0';
}

/** División entera a / b (b > 0). */
function _bc_div_abs(string $a, string $b): string
{
    $q = ''; $rem = '0';
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) {
        $rem = ltrim($rem . $a[$i], '0') ?: '0';
        $d = 0;
        while (_bc_cmp_abs($rem, $b) >= 0) { $rem = _bc_sub_abs($rem, $b); $d++; }
        $q .= $d;
    }
    return ltrim($q, '0') ?: '0';
}

function _bc_signed_add(bool $na, string $a, bool $nb, string $b): array
{
    if ($na === $nb) { return [$na, _bc_add_abs($a, $b)]; }
    $c = _bc_cmp_abs($a, $b);
    if ($c === 0) { return [false, '0']; }
    return $c > 0 ? [$na, _bc_sub_abs($a, $b)] : [$nb, _bc_sub_abs($b, $a)];
}

function _bc_format(bool $neg, string $digits, int $s): string
{
    $digits = str_pad($digits, $s + 1, '0', STR_PAD_LEFT);
    $int = substr($digits, 0, strlen($digits) - $s);
    $frac = $s > 0 ? substr($digits, -$s) : '';
    $isZero = trim($int . $frac, '0') === '';
    $out = ($neg && !$isZero ? '-' : '') . (ltrim($int, '0') ?: '0');
    return $s > 0 ? $out . '.' . $frac : $out;
}

function bcadd(string $a, string $b, ?int $scale = 0): string
{
    $s = $scale ?? 0;
    $w = max($s, strlen(_bc_parse($a)[2]), strlen(_bc_parse($b)[2]));
    [$na, $da] = _bc_scaled($a, $w); [$nb, $db] = _bc_scaled($b, $w);
    [$n, $d] = _bc_signed_add($na, $da, $nb, $db);
    $d = $w > $s ? (substr($d, 0, max(0, strlen($d) - ($w - $s))) ?: '0') : $d;
    return _bc_format($n, $d, $s);
}

function bcsub(string $a, string $b, ?int $scale = 0): string
{
    [$nb, $ib, $fb] = _bc_parse($b);
    $b2 = ($nb ? '' : '-') . $ib . ($fb !== '' ? '.' . $fb : '');
    return bcadd($a, $b2, $scale);
}

function bcmul(string $a, string $b, ?int $scale = 0): string
{
    $s = $scale ?? 0;
    [$na, $ia, $fa] = _bc_parse($a); [$nb, $ib, $fb] = _bc_parse($b);
    $d = _bc_mul_abs(ltrim($ia . $fa, '0') ?: '0', ltrim($ib . $fb, '0') ?: '0');
    $w = strlen($fa) + strlen($fb);
    if ($w > $s) { $d = substr($d, 0, max(0, strlen($d) - ($w - $s))) ?: '0'; }
    else { $d .= str_repeat('0', $s - $w); }
    return _bc_format($na !== $nb, $d, $s);
}

function bcdiv(string $a, string $b, ?int $scale = 0): string
{
    $s = $scale ?? 0;
    [$na, $ia, $fa] = _bc_parse($a); [$nb, $ib, $fb] = _bc_parse($b);
    $w = max(strlen($fa), strlen($fb));
    [, $da] = _bc_scaled($a, $w); [, $db] = _bc_scaled($b, $w);
    if ($db === '0') { throw new DivisionByZeroError('Division by zero'); }
    $q = _bc_div_abs($da . str_repeat('0', $s), $db);
    return _bc_format($na !== $nb, $q, $s);
}

function bccomp(string $a, string $b, ?int $scale = 0): int
{
    $s = $scale ?? 0;
    $d = bcsub(bcadd($a, '0', $s), bcadd($b, '0', $s), $s);
    if (trim(str_replace(['-', '.'], '', $d), '0') === '') { return 0; }
    return $d[0] === '-' ? -1 : 1;
}

function bcpow(string $base, string $exp, ?int $scale = 0): string
{
    $s = $scale ?? 0;
    $e = (int) $exp;
    if ($e < 0) { throw new ValueError('polyfill: exponente negativo no soportado'); }
    $r = '1';
    $b = $base;
    while ($e > 0) {
        if ($e & 1) { $r = bcmul($r, $b, $s + 10); }
        $b = bcmul($b, $b, $s + 10);
        $e >>= 1;
    }
    return bcadd($r, '0', $s);
}
