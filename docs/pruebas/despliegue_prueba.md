# Despliegue de prueba en un servidor limpio (G05)

Requisito: RNF-04 (instalación reproducible en un servidor nuevo). Documento que se valida: `docs/manual_tecnico.md`.

**Objetivo.** Comprobar que una persona que solo tiene el repositorio y el manual técnico puede instalar, verificar,
operar, reiniciar y recuperar el sistema en un servidor que nunca tuvo el SIG-CCR. Lo que el manual no explique, o
explique mal, es un hallazgo aunque se resuelva en el momento.

**Reglas de ejecución**

1. Seguir el manual **al pie de la letra**, en orden, sin usar conocimiento previo del proyecto. Si un paso falla o
   no está claro, anotarlo y resolverlo como lo haría alguien externo.
2. Anotar por paso: resultado (**OK**, **Falla** o **Desvío**), el tiempo aproximado y las observaciones.
3. **No escribir contraseñas, claves ni el contenido de `.env` en este documento ni en las capturas.** Donde haga falta,
   poner «configurada».
4. Cada hallazgo se numera (H-01, H-02…) y se registra al final; el defecto o el ajuste del manual se corrige en G07.

## 1. Entorno

| Dato | Valor |
|---|---|
| Fecha de ejecución | |
| Ejecutó | Iván Thoux |
| Hipervisor | (VirtualBox / Hyper-V y versión) |
| Sistema operativo | Ubuntu Server 24.04 LTS, instalación mínima recién hecha |
| Recursos de la VM | 4 vCPU, 8 GB de memoria, disco de 60 GB (referencia de RNF-03) |
| Red | (NAT con reenvío de puertos / puente); dirección de la VM: |
| Docker Engine y Compose | (versiones de `docker version` y `docker compose version`) |
| Versión del SIG-CCR | rama `develop`, commit: (salida de `git rev-parse --short HEAD`) |
| Equipo desde el que se navega | (navegador y versión) |

Desvíos aceptados de antemano, porque la VM no es el servidor de la entidad:

- **Certificado:** se usa el autofirmado de `docker/nginx/generar-certificado.sh`; no hay dominio ni certificado de una
  entidad certificadora (manual 4.3).
- **Pasarela de pagos:** `sigccr.adaptadorPasarela = simulador`, porque no hay credenciales de una pasarela real.
- **Correo:** `sigccr.adaptadorComunicaciones = real` contra una cuenta SMTP de prueba propia; si no se dispone de una,
  se usa el perfil `correo` (Mailpit) y se anota como desvío.
- **Versión:** se instala `develop` y no una versión etiquetada, porque v1.0.0 se etiqueta después de esta prueba.

## 2. Preparación del servidor (manual 2 y 5.1, paso 1)

| # | Paso | Resultado | Tiempo | Observaciones |
|---|---|---|---|---|
| P-01 | Instalar Ubuntu Server 24.04 con usuario no administrador y OpenSSH | | | |
| P-02 | Actualizar el sistema (`sudo apt update && sudo apt upgrade -y`) y fijar la zona horaria (`sudo timedatectl set-timezone America/Argentina/Buenos_Aires`) | | | |
| P-03 | Instalar Docker Engine y el complemento Compose v2 desde el repositorio oficial de Docker (docs.docker.com/engine/install/ubuntu) | | | |
| P-04 | Agregar el usuario al grupo `docker`, cerrar sesión y verificar `docker run --rm hello-world` sin `sudo` | | | |
| P-05 | Firewall: `ufw allow OpenSSH`, `ufw allow 80,443/tcp`, `ufw enable`; verificar con `ufw status` | | | |
| P-06 | Comprobar la salida a Internet hacia `api.bcra.gob.ar` (`curl -sI https://api.bcra.gob.ar`) | | | |

## 3. Instalación (manual 3 y 5.1, pasos 2 a 5)

| # | Paso | Resultado | Tiempo | Observaciones |
|---|---|---|---|---|
| I-01 | Clonar el repositorio y pasar a `develop`; anotar el commit en la sección 1 | | | |
| I-02 | Crear `.env.docker` desde el ejemplo con `MSSQL_PID=Express` (manual 4.1) | | | |
| I-03 | Generar el certificado de desarrollo (desvío aceptado) | | | |
| I-04 | `docker compose build` | | | Anotar la duración |
| I-05 | `docker compose run --rm --no-deps app bash docker/bootstrap.sh` | | | |
| I-06 | Editar `.env` para producción: `CI_ENVIRONMENT = production`, `app.baseURL` con la dirección de la VM, adaptadores (manual 4.2), datos de la entidad, `sigccr.smtpCifrado` | | | Indicar qué claves del manual faltaban o no se entendían |
| I-07 | Permisos `600` en `.env` y `.env.docker` | | | |
| I-08 | `docker compose up -d` y esperar `db` *healthy* | | | |
| I-09 | `docker compose exec app bash docker/inicializar.sh` (incluye crear el Administrador inicial) | | | Copiar el resumen OK / FALLA de cada paso |
| I-10 | `docker compose exec app php spark geografia:importar` | | | |

## 4. Verificación (manual 5.3)

| # | Comprobación | Resultado | Observaciones |
|---|---|---|---|
| V-01 | `docker compose ps`: `web`, `app` y `scheduler` en ejecución y `db` *healthy* | | |
| V-02 | `https://<VM>/estado` responde 200 e indica `production` | | |
| V-03 | `http://<VM>` redirige a `https://` | | |
| V-04 | `sigccr:verificar-entorno` sin fallas | | |
| V-05 | `migrate:status -g migraciones` sin migraciones pendientes | | |
| V-06 | Ingreso al panel con el Administrador y alta de un usuario interno | | |
| V-07 | Alta de un cliente con consulta de nombre por CUIT/CUIL (padrón real) | | |
| V-08 | Una evaluación crediticia contra la Central de Deudores real | | |
| V-09 | Activación de la cuenta del portal e ingreso con ese cliente | | |
| V-10 | Un correo real de recuperación de acceso llega a destino | | |
| V-11 | `writable/logs/cron.log` muestra ejecuciones del último minuto | | |
| V-12 | `docker compose config` no publica el puerto 1433 | | |
| V-13 | Ninguna página muestra errores ni la barra de depuración (modo producción) | | |

## 5. Operación (manual 6 a 8)

| # | Paso | Resultado | Observaciones |
|---|---|---|---|
| O-01 | Generar una copia manual desde el panel (CU-61) y verificar que queda *Verificada* | | |
| O-02 | Descargar la copia (CU-62) al equipo desde el que se navega | | |
| O-03 | **Recuperación en otro equipo (manual 6.3):** en el equipo de desarrollo, con la misma `encryption.key`, ejecutar `copias:descifrar` sobre la copia descargada y comprobar que el zip trae `sigccr.bak` y `adjuntos/` | | |
| O-04 | Restaurar desde el panel (CU-63) una copia anterior: el sistema pasa a mantenimiento y vuelve; las operaciones posteriores se informan | | |
| O-05 | Portal en mantenimiento (`sigccr.portalEnMantenimiento = true` y recrear `app`): el portal muestra la página de mantenimiento y el panel sigue disponible; volver a `false` | | |
| O-06 | **Reinicio del servidor** (`sudo reboot`): los contenedores vuelven solos y `/estado` responde en menos de dos minutos | | |
| O-07 | Revisión diaria del manual 8 (`ps`, `/estado`, alertas, última copia) | | |

## 6. Actualización y vuelta atrás (manual 5.2), opcional si alcanza el tiempo

| # | Paso | Resultado | Observaciones |
|---|---|---|---|
| A-01 | Copia previa desde el panel y descarga | | |
| A-02 | `git checkout` de un commit posterior de `develop`, recreación de `web`, `app` y `scheduler` y `composer migrar` | | |
| A-03 | Verificaciones V-01, V-02 y V-04 | | |
| A-04 | Vuelta atrás al commit anterior y verificación | | |

## 7. Hallazgos

| ID | Paso | Tipo (defecto del sistema / manual incompleto / manual incorrecto) | Descripción | Corrección (G07) |
|---|---|---|---|---|
| | | | | |

## 8. Resultado

| Dato | Valor |
|---|---|
| Duración total de la instalación (P-01 a I-10) | |
| Pasos OK / con falla / con desvío | |
| Hallazgos | |
| Resultado | (Aprobado / Aprobado con observaciones / No aprobado) |

Criterio de aprobación: todas las comprobaciones de las secciones 4 y 5 en OK o con un desvío aceptado, y cada
hallazgo corregido en G07 o registrado con su justificación.
