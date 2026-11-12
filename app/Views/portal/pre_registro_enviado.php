<?php
/** CU-104 paso 5. @var CodeIgniter\View\View $this @var string $correo */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h1>Verifique su correo</h1>
<div class="alert alert-success" role="status">Recibimos sus datos.</div>
<p class="ayuda">Le enviamos un enlace a <strong><?= esc($correo) ?></strong>. Ábralo dentro de los próximos 30 minutos para verificar el correo. Si no llega, revise la carpeta de correo no deseado o vuelva a pre-registrarse con los mismos datos para recibir uno nuevo.</p>
<div class="text-center mt-3"><a href="<?= url_to('portal.login') ?>" class="small">Volver al portal</a></div>
<?php $this->endSection() ?>
