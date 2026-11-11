<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Acceso\AccesoService;
use App\Services\Acceso\AutorizacionService;
use App\Services\Acceso\RecuperacionService;
use App\Services\Usuarios\UsuarioService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Acceso al panel administrativo: CU-01 Iniciar Sesión, CU-02 Recuperar Acceso y CU-03 Cerrar Sesión.
 * Sin reglas de negocio: cada acción llama a una operación de servicio (RNF-05).
 */
class Acceso extends BaseController
{
    private const TIPO = 'UsuarioInterno';

    /** CU-01 pasos 1 y 2; A3: con una sesión activa se reutiliza sin registrar un nuevo acceso. */
    public function login(): string|RedirectResponse
    {
        if ((session('cuenta')['tipo'] ?? null) === self::TIPO) {
            return redirect()->route('panel.inicio');
        }

        return view('acceso/login', ['titulo' => 'Iniciar sesión']);
    }

    /** CU-01 pasos 3 a 6. */
    public function autenticar(): RedirectResponse
    {
        $correo = (string) $this->request->getPost('correo');
        $contrasena = (string) $this->request->getPost('contrasena');
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

        return redirect()->route('panel.inicio');
    }

    /** CU-03 pasos 1 a 3: confirmación, con advertencia si hay una jornada de caja abierta. */
    public function confirmarCierre(): string
    {
        $cuenta = session('cuenta');

        return view('acceso/cerrar', [
            'titulo' => 'Cerrar sesión',
            'jornadaAbierta' => (new AccesoService())->tieneJornadaAbierta((int) $cuenta['id']),
            'volver' => previous_url() !== current_url() ? previous_url() : site_url('admin'),
        ]);
    }

    /** CU-03 pasos 4 a 6. */
    public function cerrar(): RedirectResponse
    {
        $cuenta = session('cuenta');
        $servicio = new AccesoService();
        $servicio->registrarCierre((int) $cuenta['id'], (string) $cuenta['nombre'], $servicio->tieneJornadaAbierta((int) $cuenta['id']));
        finalizar_sesion();

        return redirect()->route('acceso.login')->with('exito', 'La sesión se cerró correctamente.');
    }

    /** CU-02 pasos 1 y 2. */
    public function recuperar(): string
    {
        return view('acceso/recuperar', ['titulo' => 'Recuperar acceso']);
    }

    /** CU-02 pasos 3 a 5: siempre el mismo mensaje (A1, A4, A5). */
    public function solicitarRecuperacion(): RedirectResponse
    {
        $correo = trim((string) $this->request->getPost('correo'));
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->withInput()->with('error', 'Ingrese un correo electrónico válido.');
        }
        (new RecuperacionService())->solicitar(self::TIPO, $correo, 'admin/restablecer');

        return redirect()->route('acceso.login')->with('exito', 'Si el correo corresponde a una cuenta habilitada, recibirá un enlace para '
            . 'definir una nueva contraseña. El enlace vence en ' . RecuperacionService::MINUTOS_VIGENCIA . ' minutos.');
    }

    /** CU-02 paso 6. */
    public function restablecer(string $codigo): string|RedirectResponse
    {
        if (!(new RecuperacionService())->esValida($codigo)) {
            return redirect()->route('acceso.recuperar')->with('error', RecuperacionService::MENSAJE_INVALIDA);
        }

        return view('acceso/restablecer', ['titulo' => 'Definir nueva contraseña', 'codigo' => $codigo]);
    }

    /** CU-02 pasos 7 y 8. */
    public function guardarContrasena(string $codigo): RedirectResponse
    {
        try {
            (new RecuperacionService())->restablecer($codigo, (string) $this->request->getPost('contrasena'), (string) $this->request->getPost('confirmacion'));
        } catch (ReglaNegocioException $e) {
            if ($e->getMessage() === RecuperacionService::MENSAJE_INVALIDA) {
                return redirect()->route('acceso.recuperar')->with('error', $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('acceso.login')->with('exito', 'La contraseña se actualizó. Ya puede iniciar sesión.');
    }

    /** CU-06 paso 7: confirmación del correo nuevo desde el enlace (sin sesión). */
    public function verificarCorreo(string $codigo): string
    {
        $valido = (new AutorizacionService())->buscarVigente($codigo, ['VerificacionCorreo'], false) !== null;

        return view('acceso/verificar_correo', ['titulo' => 'Confirmar correo', 'codigo' => $codigo, 'valido' => $valido]);
    }

    public function confirmarCorreo(string $codigo): RedirectResponse
    {
        try {
            (new UsuarioService())->verificarCorreo($codigo);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('acceso.login')->with('error', $e->getMessage());
        }

        return redirect()->route('acceso.login')->with('exito', 'Correo confirmado. Desde ahora inicia sesión con la dirección nueva.');
    }
}
