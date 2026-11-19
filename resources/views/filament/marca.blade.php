{{--
    La marca de EstimaWeb 3.1: el emblema y el nombre.

    Va en SVG y en texto, no como imagen, por dos razones. La primera, el
    modo oscuro: la imagen del logo lleva el nombre en azul oscuro, que sobre
    fondo negro no se lee; aqui el color lo pone el tema. La segunda, la
    animacion del acceso: cada pieza del emblema (los cuatro tramos del
    medidor, las tres barras y la curva) es un elemento propio que el tema
    puede dibujar por separado.

    El dibujo reproduce el logo aprobado: un medidor de tres cuartos de
    circulo, dos tramos azules y dos naranjas, y dentro tres barras que crecen
    hasta el resultado estimado, en naranja.
--}}
<span class="fi-marca" role="img" aria-label="EstimaWeb 3.1">
    <svg class="fi-marca-emblema" viewBox="0 0 120 100" aria-hidden="true" focusable="false">
        <g class="fi-marca-arco" fill="none" stroke-width="11" stroke-linecap="butt">
            <path class="fi-marca-tramo fi-marca-azul" pathLength="1" d="M26.30 85.70 A42 42 0 0 1 23.83 29.00" />
            <path class="fi-marca-tramo fi-marca-azul" pathLength="1" d="M25.79 26.82 A42 42 0 0 1 54.53 14.03" />
            <path class="fi-marca-tramo fi-marca-naranja" pathLength="1" d="M57.47 14.03 A42 42 0 0 1 85.70 26.30" />
            <path class="fi-marca-tramo fi-marca-naranja" pathLength="1" d="M87.70 28.44 A42 42 0 0 1 95.47 70.36" />
        </g>
        <g class="fi-marca-barras">
            <rect class="fi-marca-barra fi-marca-azul" x="33" y="66" width="12" height="22" rx="3.5" />
            <rect class="fi-marca-barra fi-marca-azul" x="49" y="56" width="12" height="30" rx="3.5" />
            <rect class="fi-marca-barra fi-marca-naranja" x="65" y="44" width="12" height="40" rx="3.5" />
            <circle class="fi-marca-punto fi-marca-naranja" cx="71" cy="35" r="5" />
        </g>
        <path class="fi-marca-curva fi-marca-azul" d="M3 98 Q48 77 117 77 Q56 81 3 98 Z" />
    </svg>

    <span class="fi-marca-nombre">
        <span class="fi-marca-estima">ESTIMA</span><span class="fi-marca-web">WEB</span>
    </span>

    <span class="fi-marca-version">3.1</span>
</span>
