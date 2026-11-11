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

## Pruebas

Las pruebas corren contra **datos reales** de producción, no contra datos
inventados: así se verifica que los cálculos dan los mismos números que el
sistema actual sobre las mismas cotizaciones.

Hace falta un `.env.testing` (no va al repositorio, porque lleva credenciales).
Se crea copiando el `.env` y cambiando dos líneas:

```
APP_ENV=testing
DB_DATABASE=estimaweb31_test
```

Preparar la base de pruebas y ejecutar:

```bash
php artisan pruebas:preparar   # recrea el esquema y copia los datos reales
php artisan test --compact
```

### Golden masters: la referencia del cálculo

`tests/golden/` guarda, para cada una de las 48 cotizaciones reales, los totales
y el valor unitario de cada ítem **tal como los calcula el sistema actual**.

Es la respuesta verificable a "¿cómo sé que no se quedó nada por fuera?": el
dominio nuevo tendrá que reproducir esos mismos números, y lo que no cuadre
aparece nombrado por cotización e ítem.

Se regeneran **desde el sistema actual**, que es donde vive el cálculo de
referencia:

```bash
cd C:/wamp64/www/estimaweb3_filament
php artisan golden:generar --salida=../estimaweb3.1_filament_IA/tests/golden
```

Los snapshots se generan **ejecutando el cálculo**, no leyendo las columnas de
la base: muchas cotizaciones arrastran totales guardados por versiones
anteriores del código, y congelarlos congelaría errores ya corregidos.

Regenerar ante un fallo solo tiene sentido si el cambio de números es
deliberado y se revisó uno por uno.

### Las dos bases locales

| Base | Contenido | ¿Se puede borrar? |
|---|---|---|
| `estimaweb31` | Copia de los datos de producción, usada como referencia | **No.** Recuperarla exige recargar el respaldo |
| `estimaweb31_test` | Base de pruebas | Sí, se reconstruye sola |

`migrate:fresh` y demás comandos destructivos están **bloqueados** sobre la base
de referencia (ver `ProteccionBaseReferenciaServiceProvider`). Para trabajar
sobre la desechable: `--env=testing`.

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
