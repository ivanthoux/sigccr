<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\ReglaNegocioException;
use App\Services\Usuarios\UsuarioService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Usuarios internos: CU-53 Localizar, CU-05 Registrar, CU-06 Modificar, CU-07 Restablecer credenciales,
 * CU-08 Desactivar, CU-09 Reactivar y CU-10 Asignar rol. Cada acción llama a una operación de UsuarioService.
 */
class Usuarios extends BaseController
{
    private UsuarioService $servicio;

    public function __construct()
    {
        $this->servicio = new UsuarioService();
    }

    /** CU-53 pasos 1 a 4. */
    public function index(): string
    {
        $criterios = $this->filtrosBusqueda(['texto', 'documento', 'correo', 'rol', 'estado']);

        return view('usuarios/index', [
            'titulo' => 'Usuarios',
            'criterios' => $criterios,
            'usuarios' => $this->servicio->buscar($criterios),
            'roles' => $this->servicio->roles(),
        ]);
    }

    /** CU-53 paso 6: detalle y operaciones disponibles según el estado. */
    public function ver(int $id): string|RedirectResponse
    {
        try {
            $usuario = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('usuarios.localizar')->with('error', $e->getMessage());
        }

        return view('usuarios/ver', ['titulo' => $usuario['nombre'] . ' ' . $usuario['apellido'], 'u' => $usuario]);
    }

    /** CU-05 pasos 1 y 2. */
    public function nuevo(): string
    {
        return view('usuarios/formulario', ['titulo' => 'Registrar usuario', 'u' => null, 'roles' => $this->servicio->roles()]);
    }

    /** CU-05 pasos 3 a 8. */
    public function registrar(): RedirectResponse
    {
        try {
            $id = $this->servicio->registrar((array) $this->request->getPost(['nombre', 'apellido', 'correo', 'tipo_documento', 'nro_documento', 'rol']));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/usuarios/{$id}"))
            ->with('exito', 'Usuario registrado en estado Pendiente de activación. Le enviamos un enlace por correo para que defina su contraseña (vence en 24 horas).');
    }

    /** CU-06 pasos 1 a 3. */
    public function editar(int $id): string|RedirectResponse
    {
        try {
            $usuario = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('usuarios.localizar')->with('error', $e->getMessage());
        }

        return view('usuarios/formulario', ['titulo' => 'Modificar datos', 'u' => $usuario, 'roles' => $this->servicio->roles()]);
    }

    /** CU-06 pasos 4 a 8. */
    public function actualizar(int $id): RedirectResponse
    {
        try {
            $verificacion = $this->servicio->modificar($id, (int) $this->request->getPost('version_fila'), (array) $this->request->getPost(['nombre', 'apellido', 'correo', 'tipo_documento', 'nro_documento']));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = 'Datos actualizados.' . ($verificacion ? ' Enviamos un enlace al correo nuevo para confirmarlo; hasta entonces sigue vigente el anterior.' : '');

        return redirect()->to(site_url("admin/usuarios/{$id}"))->with('exito', $mensaje);
    }

    /** CU-07, CU-08 y CU-09 paso de confirmación; CU-10 paso 3. */
    public function confirmar(int $id, string $operacion): string|RedirectResponse
    {
        try {
            $usuario = $this->servicio->obtener($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->route('usuarios.localizar')->with('error', $e->getMessage());
        }
        $vista = $operacion === 'rol' ? 'usuarios/rol' : 'usuarios/operacion';

        return view($vista, ['titulo' => $usuario['nombre'] . ' ' . $usuario['apellido'], 'u' => $usuario, 'operacion' => $operacion, 'roles' => $this->servicio->roles()]);
    }

    /** CU-07 pasos 4 a 7. */
    public function restablecer(int $id): RedirectResponse
    {
        try {
            $correo = $this->servicio->restablecerCredenciales($id);
        } catch (ReglaNegocioException $e) {
            return redirect()->to(site_url("admin/usuarios/{$id}"))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url("admin/usuarios/{$id}"))->with('exito', "Enviamos a {$correo} un enlace para que el usuario defina su contraseña nueva.");
    }

    /** CU-08 pasos 6 a 8. */
    public function desactivar(int $id): RedirectResponse
    {
        try {
            $this->servicio->desactivar($id, (int) $this->request->getPost('version_fila'), $this->campoPost('motivo'), (int) session('cuenta')['id']);
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }

        return redirect()->to(site_url("admin/usuarios/{$id}"))->with('exito', 'Usuario desactivado. Sus sesiones abiertas se cerraron.');
    }

    /** CU-09 pasos 6 a 8. */
    public function reactivar(int $id): RedirectResponse
    {
        try {
            $estado = $this->servicio->reactivar($id, (int) $this->request->getPost('version_fila'), $this->campoPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        $mensaje = $estado === 'Habilitada' ? 'Usuario reactivado: ya puede iniciar sesión.' : 'Usuario reactivado. Todavía no definió su contraseña: use "Restablecer credenciales" para enviarle el enlace.';

        return redirect()->to(site_url("admin/usuarios/{$id}"))->with('exito', $mensaje);
    }

    /** CU-10 pasos 4 a 9. */
    public function asignarRol(int $id): RedirectResponse
    {
        try {
            $cambio = $this->servicio->asignarRol($id, (int) $this->request->getPost('version_fila'), $this->campoPost('rol'), $this->campoPost('motivo'));
        } catch (ReglaNegocioException $e) {
            return $this->volverConError($e);
        }
        if (!$cambio) {
            return redirect()->to(site_url("admin/usuarios/{$id}"))->with('aviso', 'El usuario ya tiene ese rol: no hay cambios por aplicar.');
        }
        $propio = $id === (int) session('cuenta')['id'];

        return redirect()->to(site_url($propio ? 'admin/login' : "admin/usuarios/{$id}"))
            ->with('exito', $propio ? 'Cambió su propio rol: ingrese nuevamente para operar con los permisos nuevos.' : 'Rol actualizado. Si el usuario tenía una sesión abierta, deberá ingresar nuevamente.');
    }
}
