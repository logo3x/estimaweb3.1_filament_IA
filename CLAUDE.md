<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Qué es este proyecto

Reconstrucción de EstimaWeb, el sistema de cotizaciones de Confipetrol.
Laravel 13 + Filament 5. El sistema que está en producción vive en
`C:\wamp64\www\estimaweb3_filament` y es **referencia de solo lectura**: no se
commitea ni se pushea ahí.

Se despliega en **cPanel sin SSH**. Eso condiciona casi todo lo de abajo.

## Reglas que han costado caro

### El despliegue no tiene consola

No hay forma de ejecutar `composer install` ni `npm run build` en el servidor.
Por eso **`vendor/`, `public/build/` y los assets de Filament viajan dentro del
repositorio** (72 MB). Antes de publicar:

```sh
php artisan desplegar:preparar
```

Deja `vendor/` **sin librerías de desarrollo**, así que después las pruebas no
corren hasta hacer un `composer install` normal. El comando lo recuerda.

La **plantilla Word** (`storage/app/plantillas/oferta.docx`) NO viaja en el
repositorio: lleva el membrete de la empresa y se sube a mano. Sin ella ningún
Word ni PDF funciona.

### Las dos bases locales

| Base | Para qué | Se puede escribir |
|---|---|---|
| `estimaweb31` | **REFERENCIA.** Copia de producción intacta, para comparar el cálculo viejo con el nuevo. | **No.** Ni desde la app, ni al probar en el navegador. |
| `estimaweb31_test` | **DESECHABLE.** Donde trabajan la aplicación y las pruebas. | Sí. |

`.env` y `phpunit.xml` apuntan **los dos** a `estimaweb31_test`. Si la
aplicación apunta a la referencia, probar en el navegador la contamina; ya
ocurrió dos veces y costó detectarlo porque el dato parece legítimo.

`php artisan pruebas:preparar` recrea la desechable desde la referencia.
Úsalo también cuando fallen `BaseDePruebasTest`: suele ser acumulación de
usuarios o copias de cotización de pruebas anteriores, no un error real.

### Los datos reales son irregulares

Son 48 cotizaciones importadas de producción, con rarezas que hay que
respetar porque el sistema vivo las comparte:

- Las banderas booleanas se guardan como **texto**: `'SI'`/`'NO'`, y en algunas
  filas `'1'`/`'0'`. Un `Toggle` que reciba la cadena `'NO'` la toma por
  **verdadera** -en PHP toda cadena no vacía lo es- y al guardar la deja en
  `'SI'`. Eso invirtió el AIU de una cotización y le cambió el precio de 713 a
  607 millones. Convertir siempre al **cargar**, no con `formatStateUsing`.
- `cotizaciones.gerencia` guarda **números** (4, 6, 7, 8) que no están en el
  catálogo `gerencias`; hay una con gerencia `'2'`, que no existe. Un `Select`
  cerrado rechazaría cotizaciones reales al editarlas.
- Hay 3 cotizaciones **sin gerencia** y 9 **sin fila de AIU**.
- Los nombres de columna no siguen patrón: `id_datos_equipo` (singular),
  `id_datos_consumibles` (plural), y la tabla `precargado_heramientas` lleva
  **una sola erre**. Se reproducen tal cual.

### Los golden masters mandan

`tests/golden/` congela el cálculo de las 48 cotizaciones. Si una prueba de
ahí falla tras un cambio de interfaz, **casi siempre es que se alteró un dato
real sin querer** -probando en el navegador, por ejemplo-, no que el snapshot
esté mal. Comprobar antes de regenerarlo.

Detectaron el fallo de las banderas del AIU, que no se veía en pantalla.

## Cómo está organizado

- `app/Dominio/` — el cálculo y las reglas de negocio, sin Eloquent ni Filament.
  Es lo que los golden masters verifican.
- `app/Filament/` — el panel. No decide nada: pregunta al dominio.
- `app/Policies/` — permisos por recurso. `PolicyDeCatalogo` es la base de los
  ocho catálogos.

**Los roles SUMAN.** Seis personas tienen varios a la vez y una llega a cuatro,
así que basta con que uno lo permita. `User::role('Creador')->first()` devuelve
a alguien que TAMBIÉN es Administrador: para diagnosticar un rol puro hay que
construir el usuario al vuelo.

**Cada quien ve solo las cotizaciones de su gerencia**, salvo `Administrador` y
`super_admin`. Va en `CotizacionResource::getEloquentQuery()`, no en la tabla:
ese método lo usa todo el recurso, incluida la resolución de la ruta. Lo de
otra gerencia responde **404, no 403**: un mensaje de "no puede ver esta" ya
confirmaría que existe.

## Filament: lo que no funciona

- **Pestañas dentro de un `RelationGroup`**: los gestores comparten componente
  Livewire y todas acaban mostrando la tabla de la primera. La solución es
  **montar solo el componente de la pestaña activa** (ver
  `EditCotizacion::getRelationManagerTabComponent()`).
- **`modifyTabUsing` del grupo** se evalúa ANTES de que la página añada el
  contenido: lo que se declare ahí recibe un esquema vacío.
- **`getChildSchema()` / `getChildComponents()`** sobre una pestaña recién
  construida revientan: todavía no tienen contenedor.
- Una acción dentro de un esquema debe ser **`public function xAction()`** en la
  página, o Livewire no la encuentra.
- El `form()` de un Relation Manager solo se usa en sus modales, no en la
  página.
- `--primary-500` es un color completo `oklch(...)`, no tres componentes:
  envolverlo en `rgb(var(...))` lo deja transparente.

## Comandos propios

| Comando | Para qué |
|---|---|
| `php artisan pruebas:preparar` | Recrea la base desechable desde la referencia |
| `php artisan desplegar:preparar` | Deja el repositorio listo para publicar |
| `php artisan rendimiento:medir` | Tiempos y número de consultas por pantalla |
| `php artisan permisos:revisar` | Matriz rol × capacidad |
| `php artisan seguridad:verificar` | Configuración y extensiones de PHP |
| `php artisan correo:probar` | Diagnostica el correo sin SSH |
| `php artisan datos:cargar` | Importa un respaldo de producción |
| `php artisan usuario:prueba` | Crea la cuenta local del panel |

## Convenciones

- **Todo en castellano**: nombres de clase, métodos, comentarios, mensajes y
  commits. El código lo leen personas que trabajan en castellano.
- Los comentarios explican **por qué**, no qué. Si algo parece raro -una
  errata reproducida, una comparación de cadenas- hay que decir de dónde sale.
- Las pruebas describen el comportamiento, no el método:
  `test_guardar_la_cabecera_no_altera_el_aiu`, no `test_save`.
