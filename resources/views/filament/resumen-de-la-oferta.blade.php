{{--
    El resumen de la oferta: lo que vera el cliente.

    Cada item con los recursos que lo componen y lo que aporta cada uno, mas
    los totales. Es la misma tabla que lleva el documento Word, calculada con
    el dominio, de modo que si aqui cuadra, el documento cuadra.

    Sirve para revisar antes de enviar: de donde sale cada precio y que
    recurso pesa mas.
--}}
<div class="fi-resumen">
    <table class="fi-resumen-tabla">
        <thead>
            <tr>
                <th class="fi-resumen-num">#</th>
                <th>Descripción</th>
                <th class="fi-resumen-centro">Unidad</th>
                <th class="fi-resumen-der">Cantidad</th>
                <th class="fi-resumen-der">Vr. unitario</th>
                <th class="fi-resumen-der">Vr. parcial</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($items as $posicion => $item)
                <tr class="fi-resumen-item">
                    <td class="fi-resumen-num">{{ $posicion + 1 }}</td>
                    <td>{{ $item['nombre'] }}</td>
                    <td class="fi-resumen-centro">{{ $item['unidad'] }}</td>
                    <td class="fi-resumen-der">{{ $item['cantidad'] }}</td>
                    <td class="fi-resumen-der">{{ $pesos($item['unitario']) }}</td>
                    <td class="fi-resumen-der">{{ $pesos($item['parcial']) }}</td>
                </tr>

                {{-- Los recursos que componen ese item. --}}
                @foreach ($item['lineas'] as $n => $linea)
                    <tr class="fi-resumen-linea">
                        <td class="fi-resumen-num">{{ $posicion + 1 }}.{{ $n + 1 }}</td>
                        <td>
                            <span class="fi-resumen-categoria">{{ $linea->categoria }}</span>
                            {{ $linea->descripcion }}
                        </td>
                        <td class="fi-resumen-centro">{{ $linea->unidad }}</td>
                        <td class="fi-resumen-der">{{ rtrim(rtrim(number_format($linea->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="fi-resumen-der">{{ $pesos($linea->valorUnitario) }}</td>
                        <td class="fi-resumen-der">{{ $pesos($linea->aporte) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="6" class="fi-resumen-vacio">
                        Esta cotización todavía no tiene ítems.
                    </td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            @foreach ($totales as $etiqueta => $valor)
                <tr @class(['fi-resumen-total' => $loop->last])>
                    <td colspan="5" class="fi-resumen-der">{{ $etiqueta }}</td>
                    <td class="fi-resumen-der">{{ $pesos($valor) }}</td>
                </tr>
            @endforeach
        </tfoot>
    </table>
</div>
