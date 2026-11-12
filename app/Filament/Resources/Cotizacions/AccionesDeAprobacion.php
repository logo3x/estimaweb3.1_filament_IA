<?php

namespace App\Filament\Resources\Cotizacions;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\FlujoDeAprobacion;
use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Models\Cotizacion;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Los botones del flujo de aprobacion.
 *
 * Viven aparte de la pagina para poder usarse en la vista de detalle y en
 * la de edicion sin repetirlos. Ninguno decide nada por su cuenta: cada
 * uno pregunta a las reglas si debe verse, y el dominio vuelve a
 * comprobarlo al ejecutarse.
 *
 * Esa doble comprobacion es deliberada. En el sistema actual el permiso lo
 * garantiza solo el visible() del boton, de modo que quien alcance la
 * accion por otra via no encuentra nada detras.
 */
final class AccionesDeAprobacion
{
    /**
     * @return array<Action>
     */
    public static function todas(): array
    {
        return [
            self::enviar(),
            self::aprobarTecnica(),
            self::rechazarTecnica(),
            self::aprobarFinanciera(),
            self::rechazarFinanciera(),
            self::reabrir(),
        ];
    }

    private static function reglas(): ReglasDeAprobacion
    {
        return new ReglasDeAprobacion();
    }

    /** El autor la manda al gerente. */
    private static function enviar(): Action
    {
        return Action::make('enviarAAprobacion')
            ->label('Enviar a aprobación')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Enviar a aprobación técnica')
            ->modalDescription('Pasará al gerente de la gerencia. Mientras la revisa, usted no podrá modificarla.')
            ->schema([self::comentarios('Comentarios para el gerente (opcional)', obligatorio: false)])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeEnviarAAprobacion($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->enviarAAprobacion($record, auth()->user(), $data['comentarios'] ?? null),
                'Enviada a aprobación técnica'
            ));
    }

    private static function aprobarTecnica(): Action
    {
        return Action::make('aprobarTecnica')
            ->label('Aprobar (técnica)')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Pasará a la administración para la aprobación financiera.')
            ->schema([self::comentarios('Comentarios (opcional)', obligatorio: false)])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeDecidirTecnica($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->aprobarTecnica($record, auth()->user(), $data['comentarios'] ?? null),
                'Aprobada técnicamente'
            ));
    }

    private static function rechazarTecnica(): Action
    {
        return Action::make('rechazarTecnica')
            ->label('Rechazar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Volverá al autor para que la corrija.')
            ->schema([self::comentarios('Motivo del rechazo')])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeDecidirTecnica($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->rechazarTecnica($record, auth()->user(), $data['comentarios'] ?? ''),
                'Devuelta al autor'
            ));
    }

    private static function aprobarFinanciera(): Action
    {
        return Action::make('aprobarFinanciera')
            ->label('Aprobar (financiera)')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Aprobación financiera')
            ->modalDescription('La cotización quedará cerrada y no admitirá más cambios.')
            ->schema([self::comentarios('Comentarios (opcional)', obligatorio: false)])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeDecidirFinanciera($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->aprobarFinanciera($record, auth()->user(), $data['comentarios'] ?? null),
                'Aprobada'
            ));
    }

    private static function rechazarFinanciera(): Action
    {
        return Action::make('rechazarFinanciera')
            ->label('Rechazar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Volverá al gerente de la gerencia.')
            ->schema([self::comentarios('Motivo del rechazo')])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeDecidirFinanciera($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->rechazarFinanciera($record, auth()->user(), $data['comentarios'] ?? ''),
                'Devuelta al gerente'
            ));
    }

    /** Deshace una aprobacion: queda reservado a la administracion. */
    private static function reabrir(): Action
    {
        return Action::make('reabrir')
            ->label('Reabrir')
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reabrir una cotización aprobada')
            ->modalDescription('Se borrarán ambas aprobaciones y volverá a borrador. Hágalo solo si el contenido debe cambiar.')
            ->schema([self::comentarios('Motivo de la reapertura')])
            ->visible(fn (Cotizacion $record): bool => self::reglas()->puedeReabrir($record, auth()->user()))
            ->action(fn (Cotizacion $record, array $data) => self::ejecutar(
                fn () => FlujoDeAprobacion::crear()->reabrir($record, auth()->user(), $data['comentarios'] ?? ''),
                'Reabierta'
            ));
    }

    private static function comentarios(string $etiqueta, bool $obligatorio = true): Textarea
    {
        return Textarea::make('comentarios')
            ->label($etiqueta)
            ->rows(3)
            ->maxLength(2000)
            ->required($obligatorio);
    }

    /**
     * Ejecuta la transicion y traduce el fallo a un aviso en pantalla.
     *
     * El dominio lanza DomainException cuando el usuario no tiene permiso;
     * sin esto el usuario veria una pantalla de error.
     */
    private static function ejecutar(callable $transicion, string $titulo): void
    {
        try {
            $cotizacion = $transicion();
        } catch (DomainException $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo completar')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($titulo)
            ->body('Estado: ' . $cotizacion->estadoAprobacion()->etiqueta())
            ->send();
    }

    /** El distintivo de estado, para listados e infolists. */
    public static function colorDelEstado(EstadoAprobacion $estado): string
    {
        return $estado->color();
    }
}
