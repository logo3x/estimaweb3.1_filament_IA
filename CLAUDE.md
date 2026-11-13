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

## Bases de datos locales (regla del proyecto)

Hay **dos** bases locales y no son intercambiables:

| Base | Para qué | Se puede escribir |
|---|---|---|
| `estimaweb31` | **REFERENCIA.** Copia de producción que se deja intacta para comparar el cálculo viejo con el nuevo. | **No.** Ni desde la app, ni al probar en el navegador. |
| `estimaweb31_test` | **DESECHABLE.** Donde trabajan la aplicación y las pruebas. | Sí. Se recrea con `php artisan pruebas:preparar`. |

El `.env` y `phpunit.xml` apuntan **los dos** a `estimaweb31_test`. Si apunta la
aplicación a la referencia, cualquier prueba en el navegador escribe ahí y
contamina los datos con los que se verifica el cálculo; ya ocurrió dos veces y
costó detectarlo, porque el dato parece legítimo.

`php artisan pruebas:preparar` recrea la desechable desde la referencia, de modo
que recuperar un estado limpio es inmediato.
