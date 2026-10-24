<?php
/** CU-01 A4: límite de intentos por dirección de origen (RN-62). @var CodeIgniter\View\View $this */
$this->extend('layouts/acceso');
$this->section('contenido');
?>
<h2 class="h5">Demasiados intentos</h2>
<p class="mb-0">Se superó la cantidad de intentos permitidos. Vuelva a intentarlo en unos minutos.</p>
<?php $this->endSection() ?>
