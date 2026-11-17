<?php
/**
 * Tema claro u oscuro (D-49), antes de cargar los estilos para que la página no aparezca un instante con el otro tema:
 * el elegido con el selector (guardado en este navegador) o, si no eligió ninguno, el del sistema operativo.
 */
?>
<script>(() => { let t = null; try { t = localStorage.getItem('sigccr-tema'); } catch (e) { /* almacenamiento no disponible */ } document.documentElement.dataset.bsTheme = t === 'dark' || t === 'light' ? t : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); })();</script>
