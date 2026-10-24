/* Creación de la base y de los inicios de sesión del SIG-CCR.
   Lo ejecuta docker/inicializar.sh (se puede repetir sin efectos adversos), con el usuario sa, desde el contenedor app:
     sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -i docker/db/crear_base.sql \
            -v OWNER_PASSWORD="..." APP_PASSWORD="..."
   - sigccr_owner: propietario del esquema; lo usan las migraciones (php spark migrate -g migraciones).
   - sigccr_app: usuario de la aplicación, con el rol rol_sigccr_app que crean las migraciones (4.3.5). */
IF DB_ID('sigccr') IS NULL
    CREATE DATABASE sigccr COLLATE Modern_Spanish_CI_AI;
GO
ALTER DATABASE sigccr SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;
ALTER DATABASE sigccr SET ALLOW_SNAPSHOT_ISOLATION ON;
GO
-- Si los inicios de sesión ya existen se les actualiza la contraseña, para que siempre coincidan con el .env.
IF SUSER_ID('sigccr_owner') IS NULL
    CREATE LOGIN sigccr_owner WITH PASSWORD = '$(OWNER_PASSWORD)', CHECK_POLICY = ON, DEFAULT_DATABASE = sigccr;
ELSE
    ALTER LOGIN sigccr_owner WITH PASSWORD = '$(OWNER_PASSWORD)';
IF SUSER_ID('sigccr_app') IS NULL
    CREATE LOGIN sigccr_app WITH PASSWORD = '$(APP_PASSWORD)', CHECK_POLICY = ON, DEFAULT_DATABASE = sigccr;
ELSE
    ALTER LOGIN sigccr_app WITH PASSWORD = '$(APP_PASSWORD)';
GO
USE sigccr;
GO
IF USER_ID('sigccr_owner') IS NULL
BEGIN
    CREATE USER sigccr_owner FOR LOGIN sigccr_owner;
    ALTER ROLE db_owner ADD MEMBER sigccr_owner;
END
IF USER_ID('sigccr_app') IS NULL
    CREATE USER sigccr_app FOR LOGIN sigccr_app;
GO
PRINT 'Base sigccr y usuarios creados.';
GO
