<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CotizacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required(),
                TextInput::make('codigo')
                    ->required(),
                TextInput::make('cliente')
                    ->required(),
                TextInput::make('contacto'),
                TextInput::make('cargo')
                    ->required(),
                TextInput::make('referencia')
                    ->required(),
                Textarea::make('alcance')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('observaciones')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('vigencia')
                    ->required(),
                TextInput::make('forma_pago')
                    ->required(),
                TextInput::make('otras_condiciones'),
                TextInput::make('gerencia'),
                TextInput::make('aprobacion1'),
                TextInput::make('aprobacion1_user_id')
                    ->numeric(),
                DateTimePicker::make('aprobacion1_fecha'),
                Textarea::make('aprobacion1_comentarios')
                    ->columnSpanFull(),
                TextInput::make('aprobacion2'),
                TextInput::make('aprobacion2_user_id')
                    ->numeric(),
                DateTimePicker::make('aprobacion2_fecha'),
                Textarea::make('aprobacion2_comentarios')
                    ->columnSpanFull(),
                TextInput::make('estado'),
                TextInput::make('dias_dura_servicio')
                    ->required()
                    ->numeric(),
                DatePicker::make('estimado_inicio')
                    ->required(),
                TextInput::make('id_user')
                    ->numeric(),
                TextInput::make('valor_total')
                    ->numeric(),
                TextInput::make('valor_total_con_aiu')
                    ->numeric(),
                TextInput::make('aiu_administracion')
                    ->numeric(),
                TextInput::make('aiu_imprevistos')
                    ->numeric(),
                TextInput::make('aiu_utilidad')
                    ->numeric(),
                TextInput::make('aiu_iva')
                    ->numeric(),
                TextInput::make('aiu_valortotal_con_iva')
                    ->numeric(),
            ]);
    }
}
