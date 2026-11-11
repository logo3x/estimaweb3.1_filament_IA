# EstimaWeb 3.1

Reconstrucción del sistema de cotizaciones **EstimaWeb**, sobre un stack actualizado
y con la lógica de negocio separada de la interfaz.

El sistema en producción (EstimaWeb 3) sigue operando sin cambios mientras dura el
desarrollo. Este proyecto trabaja siempre sobre **copias locales** de sus datos.

## Stack

| | Versión |
|---|---|
| PHP | 8.3+ |
| Laravel | 13 |
| Filament | 5 |
| Livewire | 4 |
| MySQL | 8 |

## Puesta en marcha local

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crear las bases locales y cargar datos reales:

```sql
CREATE DATABASE estimaweb31 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE estimaweb31_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan datos:cargar
```

## Cargar datos de producción

El desarrollo se hace contra datos reales para que el cambio sea transparente
para el usuario final.

1. Generar el respaldo desde el panel de EstimaWeb 3 en producción.
2. Descargarlo y dejarlo en `storage/app/respaldos/`.
3. Ejecutar:

```bash
php artisan datos:cargar
```

Toma el respaldo más reciente, comprueba que sea de EstimaWeb y recrea la base local.
Acepta `.sql` y `.zip`.

Los datos viajan **en un solo sentido**: producción → local. El comando se niega a
ejecutarse si la conexión no apunta a `127.0.0.1`, y los respaldos nunca entran al
repositorio (`.gitignore`).

## Despliegue

El hosting de destino es **cPanel sin acceso SSH**. El despliegue se hace con *Git
Version Control* del panel, y el mantenimiento (cachés, migraciones) desde un módulo
dentro de la propia aplicación, no por terminal.
