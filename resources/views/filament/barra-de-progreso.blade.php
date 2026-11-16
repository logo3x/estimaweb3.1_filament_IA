{{--
    Marca el documento mientras se cambia de seccion.

    Livewire avisa de la navegacion con eventos (`livewire:navigate` y
    `livewire:navigated`), no con un atributo, de modo que el CSS no puede
    reaccionar por si solo. Estas pocas lineas traducen el evento a un
    atributo en <html>, y el tema se encarga del resto.

    Va aqui y no en un archivo de JavaScript aparte para que no dependa de
    la compilacion de Vite: son ocho lineas que deben estar siempre, incluso
    si alguien despliega sin recompilar.
--}}
<script>
    document.addEventListener('livewire:navigate', () => {
        document.documentElement.setAttribute('data-navegando', '');
    });

    {{-- Tambien al terminar por atras o adelante del navegador. --}}
    ['livewire:navigated', 'pageshow'].forEach((evento) => {
        document.addEventListener(evento, () => {
            document.documentElement.removeAttribute('data-navegando');
        });
    });
</script>
