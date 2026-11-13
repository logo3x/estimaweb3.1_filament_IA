<?php

namespace App\Correo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Envia el correo por la API de Microsoft 365, no por SMTP.
 *
 * Se eligio asi por dos razones. La primera es que el alojamiento de destino
 * es cPanel compartido: Graph viaja por HTTPS, de modo que no depende de que
 * el hosting abra los puertos de SMTP. La segunda es la entrega: un correo
 * enviado desde el buzon corporativo no acaba en la bandeja de no deseado
 * como suele pasar con los de un servidor compartido.
 *
 * Usa credenciales de aplicacion (client credentials), que no se ven
 * afectadas por el segundo factor de la cuenta.
 *
 * El mensaje viaja como MIME completo, asi que el HTML, los adjuntos y las
 * cabeceras llegan intactos.
 */
final class TransporteMicrosoftGraph extends AbstractTransport
{
    private const TIEMPO_LIMITE = 30;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $remitente,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $mensaje): void
    {
        $this->exigirConfiguracion();

        $de = mb_strtolower($mensaje->getEnvelope()->getSender()->getAddress());

        /**
         * Graph solo deja enviar desde el buzon cuyas credenciales se usan.
         * Mejor avisar aqui que recibir un 403 sin explicacion.
         */
        if ($de !== mb_strtolower($this->remitente)) {
            throw new TransportException(sprintf(
                'Remitente no autorizado para Microsoft Graph: %s (solo se permite %s).',
                $de,
                $this->remitente,
            ));
        }

        $respuesta = Http::withToken($this->token())
            ->withBody(base64_encode($mensaje->toString()), 'text/plain')
            ->timeout(self::TIEMPO_LIMITE)
            ->post('https://graph.microsoft.com/v1.0/users/' . rawurlencode($this->remitente) . '/sendMail');

        /** Un token rechazado no sirve de nada en la cache. */
        if ($respuesta->status() === 401) {
            Cache::forget($this->claveDelToken());
        }

        if (! $respuesta->successful()) {
            throw new TransportException(sprintf(
                'Microsoft Graph rechazó el envío (HTTP %d): %s',
                $respuesta->status(),
                $respuesta->json('error.message') ?? $respuesta->body(),
            ));
        }
    }

    /**
     * El token de aplicacion, cacheado hasta poco antes de caducar.
     *
     * Sin cache se pediria uno nuevo en cada correo, y una aprobacion
     * dispara hasta dos.
     */
    private function token(): string
    {
        $cacheado = Cache::get($this->claveDelToken());

        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        $respuesta = Http::asForm()
            ->timeout(self::TIEMPO_LIMITE)
            ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if (! $respuesta->successful() || ! $respuesta->json('access_token')) {
            throw new TransportException(sprintf(
                'No se pudo obtener el token de Microsoft Graph (HTTP %d): %s',
                $respuesta->status(),
                $respuesta->json('error_description') ?? $respuesta->body(),
            ));
        }

        $token = (string) $respuesta->json('access_token');

        /** Un minuto de margen para no usarlo justo al caducar. */
        Cache::put(
            $this->claveDelToken(),
            $token,
            max(60, (int) $respuesta->json('expires_in', 3600) - 60),
        );

        return $token;
    }

    /**
     * Falta de configuracion: se avisa antes de llamar a Microsoft.
     *
     * Un .env a medias daria un error de autenticacion confuso en vez de
     * decir que falta una variable.
     */
    private function exigirConfiguracion(): void
    {
        $faltan = [];

        foreach ([
            'MAIL_GRAPH_TENANT_ID' => $this->tenantId,
            'MAIL_GRAPH_CLIENT_ID' => $this->clientId,
            'MAIL_GRAPH_CLIENT_SECRET' => $this->clientSecret,
            'MAIL_GRAPH_SENDER' => $this->remitente,
        ] as $variable => $valor) {
            if (trim($valor) === '') {
                $faltan[] = $variable;
            }
        }

        if ($faltan !== []) {
            throw new TransportException(
                'Falta configurar el correo. Defina en el .env: ' . implode(', ', $faltan) . '.'
            );
        }
    }

    private function claveDelToken(): string
    {
        return 'correo-graph-token:' . md5($this->tenantId . '|' . $this->clientId);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
