<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una cotizacion: la unidad de trabajo del sistema.
 *
 * Agrupa los recursos (personal, herramientas, equipos, ...), los items que
 * ve el cliente y los parametros del AIU con que se calcula el precio final.
 *
 * El nombre de la clase es Cotizacion, en singular correcto; la tabla sigue
 * siendo `cotizaciones` por compatibilidad con los datos de produccion.
 */
class Cotizacion extends Model
{
    /** @use HasFactory<\Database\Factories\CotizacionFactory> */
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'nombre',
        'codigo',
        'cliente',
        'contacto',
        'cargo',
        'referencia',
        'alcance',
        'observaciones',
        'vigencia',
        'forma_pago',
        'otras_condiciones',
        'gerencia',
        'dias_dura_servicio',
        'estimado_inicio',
        'estado',
        'id_user',
        'aprobacion1',
        'aprobacion1_user_id',
        'aprobacion1_fecha',
        'aprobacion1_comentarios',
        'aprobacion2',
        'aprobacion2_user_id',
        'aprobacion2_fecha',
        'aprobacion2_comentarios',
        'valor_total',
        'valor_total_con_aiu',
        'aiu_administracion',
        'aiu_imprevistos',
        'aiu_utilidad',
        'aiu_iva',
        'aiu_valortotal_con_iva',
    ];

    /**
     * Los totales se leen como float porque alimentan calculos; MySQL
     * devolveria los decimal como cadena y obligaria a convertir en cada uso.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimado_inicio' => 'date',
            'aprobacion1_fecha' => 'datetime',
            'aprobacion2_fecha' => 'datetime',
            'dias_dura_servicio' => 'integer',
            'valor_total' => 'float',
            'valor_total_con_aiu' => 'float',
            'aiu_administracion' => 'float',
            'aiu_imprevistos' => 'float',
            'aiu_utilidad' => 'float',
            'aiu_iva' => 'float',
            'aiu_valortotal_con_iva' => 'float',
        ];
    }

    /** Quien creo la cotizacion. */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function aprobadorTecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobacion1_user_id');
    }

    public function aprobadorFinanciero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobacion2_user_id');
    }

    /** Parametros del AIU. Es uno solo por cotizacion. */
    public function aiu(): HasOne
    {
        return $this->hasOne(Aiu::class, 'id_cotizacion');
    }

    /** Las lineas que ve el cliente en la oferta. */
    public function items(): HasMany
    {
        return $this->hasMany(ItemCotizacion::class, 'id_cotizacion');
    }

    /** Reparto de cada recurso entre los items. Entrada del calculo. */
    public function revisiones(): HasMany
    {
        return $this->hasMany(Revision::class, 'id_cotizacion');
    }

    public function personals(): HasMany
    {
        return $this->hasMany(Personal::class, 'id_cotizacion');
    }

    public function herramientas(): HasMany
    {
        return $this->hasMany(Herramienta::class, 'id_cotizacion');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'id_cotizacion');
    }

    public function consumibles(): HasMany
    {
        return $this->hasMany(Consumible::class, 'id_cotizacion');
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'id_cotizacion');
    }

    public function oficinas(): HasMany
    {
        return $this->hasMany(Oficina::class, 'id_cotizacion');
    }

    public function aseos(): HasMany
    {
        return $this->hasMany(Aseo::class, 'id_cotizacion');
    }

    public function viaticos(): HasMany
    {
        return $this->hasMany(Viatico::class, 'id_cotizacion');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'id_cotizacion');
    }

    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class, 'id_cotizacion');
    }

    public function otros(): HasMany
    {
        return $this->hasMany(Otro::class, 'id_cotizacion');
    }

    public function impresiones(): HasMany
    {
        return $this->hasMany(ImpresionRegistro::class, 'cotizacion_id');
    }
}
