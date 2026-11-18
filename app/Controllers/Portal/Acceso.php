<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Validacion\Campos;
use App\Services\Acceso\AccesoService;
use App\Services\Acceso\RecuperacionService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Acceso del Cliente al portal de autogestión: CU-01 Iniciar Sesión, CU-02 Recuperar Acceso y CU-03 Cerrar Sesión.
 * Usa las mismas operaciones de servicio que el panel con el tipo de cuenta Cliente (RN-08): una cuenta del portal no
 * entra al panel ni a la inversa. Sin reglas de negocio en el controlador (RNF-05).
 */
class Acceso extends BaseController
{
    private const TIPO = 'Cliente';
    private const BASE = 'portal';

    /** CU-01 pasos 1 y 2; A3: con una sesión de cliente activa se reutiliza sin registrar un nuevo acceso. */
    public function login(): string|RedirectResponse
    {
        if ((session('cuenta')['tipo'] ?? null) === self::TIPO) {
            return redirect()->route('portal.inicio');
        }

        return view('acceso/login', ['titulo' => 'Iniciar sesión', 'base' => self::BASE]);
    }

    /** CU-01 pasos 3 a 6, A1 y A2 (con el mensaje genérico, RN-62), y RN-10. */
    public function autenticar(): RedirectResponse
    {
        $correo = $this->campoPost('correo');
        $contrasena = $this->campoPost('contrasena');
        if (trim($correo) === '' || $contrasena === '') {
            return redirect()->back()->withInput()->with('error', 'Ingrese el correo electrónico y la contraseña.');
        }
        try {
            $cuenta = (new AccesoService())->autenticar(self::TIPO, $correo, $contrasena);
        } catch (ReglaNegocioException $e) {
            return redirect()->back()->with('error', $e->getMessage())->with('correo', $correo);
        }
        // Nuevo identificador de sesión al autenticarse (RNF-11)
        session()->regenerate(true);
        session()->set('cuenta', $cuenta);

        return redirect()->route('portal.inicio');
    }

    /** CU-03 pasos 1 a 3. A2 (sesión ya vencida) la resuelve el filtro auth:portal antes de llegar aquí. */
    public function confirmarCierre(): string
    {
        return view('portal/cerrar', [
            'titulo' => 'Cerrar sesión',
            'volver' => previous_url() !== current_url() ? previous_url() : site_url('portal'),
        ]);
    }

    /** CU-03 pasos 4 a 6. */
    public function cerrar(): RedirectResponse
    {
        $cuenta = session('cuenta');
        (new AccesoService())->registrarCierre((int) $cuenta['id'], (string) $cuenta['nombre'], false);
        finalizar_sesion();

        return redirect()->route('portal.login')->with('exito', 'La sesión se cerró correctamente.');
    }

    /** CU-02 pasos 1 y 2. */
    public function recuperar(): string
    {
        return view('acceso/recuperar', ['titulo' => 'Recuperar acceso', 'base' => self::BASE]);
    }

    /** CU-02 pasos 3 a 5: siempre el mismo mensaje (A1, A4, A5). */
    public function solicitarRecuperacion(): RedirectResponse
    {
        $correo = trim($this->campoPost('correo'));
        if ($correo === '' || Campos::formatoCorreo(Campos::correo($correo)) !== null) {
            return redirect()->back()->withInput()->with('error', 'Ingrese un correo electrónico válido.');
        }
        (new RecuperacionService())->solicitar(self::TIPO, $correo, self::BASE . '/restablecer');

        return redirect()->route('portal.login')->with('exito', 'Si el correo corresponde a una cuenta habilitada, recibirá un enlace para '
            . 'definir una nueva contraseña. El enlace vence en ' . RecuperacionService::MINUTOS_VIGENCIA . ' minutos.');
    }

    /** CU-02 paso 6. */
    public function restablecer(string $codigo): string|RedirectResponse
    {
        if (!(new RecuperacionService())->esValida($codigo)) {
            return redirect()->route('portal.recuperar')->with('error', RecuperacionService::MENSAJE_INVALIDA);
        }

        return view('acceso/restablecer', ['titulo' => 'Definir nueva contraseña', 'codigo' => $codigo, 'base' => self::BASE]);
    }

    /** CU-02 pasos 7 y 8. */
    public function guardarContrasena(string $codigo): RedirectResponse
    {
        try {
            (new RecuperacionService())->restablecer($codigo, $this->campoPost('contrasena'), $this->campoPost('confirmacion'));
        } catch (ReglaNegocioException $e) {
            if ($e->getMessage() === RecuperacionService::MENSAJE_INVALIDA) {
                return redirect()->route('portal.recuperar')->with('error', $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.login')->with('exito', 'La contraseña se actualizó. Ya puede iniciar sesión.');
    }
}
