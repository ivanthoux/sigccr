/* Base de las pruebas de integración (D-51). La ejecuta tests/feature/preparar_base.sh con el usuario sa, desde el
   contenedor app, al comienzo de cada ejecución de la suite:
     sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base_pruebas.sql

   - Borra y vuelve a crear sigccr_pruebas: cada ejecución parte de una base vacía. Solo toca esa base; el nombre está
     fijo en el script para que nunca pueda apuntar a la base de desarrollo (sigccr).
   - Usa los inicios de sesión que crea docker/db/crear_base.sql: sigccr_owner (propietario del esquema, para las
     migraciones y las semillas) y sigccr_app (la aplicación; el rol rol_sigccr_app lo crean las migraciones). */
IF DB_ID('sigccr_pruebas') IS NOT NULL
BEGIN
    ALTER DATABASE sigccr_pruebas SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE sigccr_pruebas;
END;
GO
CREATE DATABASE sigccr_pruebas COLLATE Modern_Spanish_CI_AI;
GO
ALTER DATABASE sigccr_pruebas SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;
ALTER DATABASE sigccr_pruebas SET ALLOW_SNAPSHOT_ISOLATION ON;
GO
IF SUSER_ID('sigccr_owner') IS NULL OR SUSER_ID('sigccr_app') IS NULL
    THROW 50000, 'Faltan los inicios de sesión sigccr_owner o sigccr_app: ejecutar antes docker/inicializar.sh.', 1;
GO
USE sigccr_pruebas;
GO
CREATE USER sigccr_owner FOR LOGIN sigccr_owner;
ALTER ROLE db_owner ADD MEMBER sigccr_owner;
CREATE USER sigccr_app FOR LOGIN sigccr_app;
GO
PRINT 'Base sigccr_pruebas creada.';
GO
