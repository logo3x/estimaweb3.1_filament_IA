<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Genera el avatar del usuario sin salir a internet.
 *
 * Filament usa por defecto ui-avatars.com, lo que implica dos cosas: que cada
 * carga del panel envia el nombre del usuario a un tercero, y que hay que
 * abrir la politica de contenido para permitir imagenes de ese dominio.
 *
 * Aqui el avatar es un SVG generado en el momento y embebido en la propia
 * pagina, asi que la politica puede seguir aceptando solo 'self' y data:,
 * y los nombres del personal no viajan a ningun servicio externo.
 */
class AvatarLocal implements AvatarProvider
{
    /**
     * Colores de fondo, elegidos por su contraste con texto blanco.
     *
     * @var list<string>
     */
    private const COLORES = [
        '#0f766e', '#1d4ed8', '#7c3aed', '#be123c',
        '#b45309', '#15803d', '#0369a1', '#86198f',
    ];

    public function get(Model|\Illuminate\Contracts\Auth\Authenticatable $record): string
    {
        $nombre = (string) ($record->name ?? '');

        $svg = $this->dibujar($this->iniciales($nombre), $this->color($nombre));

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Hasta dos iniciales del nombre. */
    private function iniciales(string $nombre): string
    {
        $palabras = preg_split('/\s+/', trim($nombre)) ?: [];

        $iniciales = '';

        foreach (array_slice(array_filter($palabras), 0, 2) as $palabra) {
            $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return $iniciales !== '' ? $iniciales : '?';
    }

    /**
     * Color estable a partir del nombre.
     *
     * El mismo usuario conserva siempre su color, de modo que se le reconoce
     * de un vistazo entre varias filas.
     */
    private function color(string $nombre): string
    {
        return self::COLORES[crc32($nombre) % count(self::COLORES)];
    }

    private function dibujar(string $iniciales, string $color): string
    {
        $texto = htmlspecialchars($iniciales, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
                <rect width="100" height="100" fill="{$color}"/>
                <text x="50" y="50" fill="#FFFFFF" font-family="sans-serif" font-size="40"
                      font-weight="600" text-anchor="middle" dominant-baseline="central">{$texto}</text>
            </svg>
            SVG;
    }
}
