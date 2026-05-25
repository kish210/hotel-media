<?php declare(strict_types=1);
namespace App\Services;

/**
 * Push messages to the WebSocket server via HTTP API or direct socket
 */
class WebSocketService
{
    private string $host;
    private int    $port;

    public function __construct()
    {
        $this->host = env('WS_HOST', '127.0.0.1');
        $this->port = (int)env('WS_PORT', 8080);
    }

    public function broadcast(string $channel, array $data): bool
    {
        $payload = json_encode(array_merge($data, [
            'type'    => $data['type'] ?? 'broadcast',
            'channel' => $channel,
        ]));

        try {
            $sock = fsockopen($this->host, $this->port, $errno, $errstr, 2);
            if (!$sock) return false;

            // Send WebSocket frame (simplified text frame)
            $header = chr(0x81);
            $len    = strlen($payload);
            $header .= $len < 126 ? chr($len) : chr(126) . pack('n', $len);
            fwrite($sock, $header . $payload);
            fclose($sock);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function notifyScreenUpdate(string $screenCode, string $event, array $data = []): bool
    {
        return $this->broadcast("screen_$screenCode", array_merge(['type' => $event], $data));
    }

    public function notifyAdmin(int $tenantId, string $event, array $data = []): bool
    {
        return $this->broadcast("admin_$tenantId", array_merge(['type' => $event], $data));
    }

    public function emergency(int $tenantId, string $message): bool
    {
        return $this->notifyAdmin($tenantId, 'emergency', ['data' => $message]);
    }
}
