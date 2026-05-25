<?php
/**
 * SignageCMS WebSocket Server
 * Pure PHP streams — no extensions needed (socket, ratchet, etc.)
 */
declare(strict_types=1);

$host = '0.0.0.0';
$port = (int)(getenv('WS_PORT') ?: 8080);

function wsLog(string $m): void { echo '['.date('H:i:s').'] '.$m.PHP_EOL; }

function handshake($sock, string $data): bool {
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $data, $m)) return false;
    $accept = base64_encode(sha1(trim($m[1]).'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    fwrite($sock, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n");
    return true;
}

function decode(string $d): string {
    if (strlen($d) < 2) return '';
    $len = ord($d[1]) & 127; $off = 2;
    if ($len === 126) { $len = unpack('n', substr($d,2,2))[1]; $off = 4; }
    elseif ($len === 127) { $off = 10; }
    $masked = (ord($d[1]) >> 7) & 1;
    if ($masked) {
        $k = substr($d,$off,4); $off += 4;
        $out = '';
        for ($i=0;$i<$len;$i++) $out .= $d[$off+$i] ^ $k[$i%4];
        return $out;
    }
    return substr($d,$off,$len);
}

function encode(string $d): string {
    $l = strlen($d);
    if ($l < 126) return chr(0x81).chr($l).$d;
    if ($l < 65536) return chr(0x81).chr(126).pack('n',$l).$d;
    return chr(0x81).chr(127).pack('J',$l).$d;
}

function send($sock, array $data): void {
    @fwrite($sock, encode(json_encode($data)));
}

function removeFromChannel(int $id, string $ch, array &$channels): void {
    if (isset($channels[$ch])) {
        $channels[$ch] = array_values(array_filter($channels[$ch], fn($x) => $x !== $id));
        if (empty($channels[$ch])) unset($channels[$ch]);
    }
}

// ─── Start server ─────────────────────────────────────────────
$server = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$server) { echo "Failed: $errstr ($errno)\n"; exit(1); }
stream_set_blocking($server, false);
wsLog("WebSocket Server running on ws://$host:$port");

$clients   = [];  // id => ['sock','hs','ch','ts']
$channels  = [];  // channel => [id, ...]
$nextId    = 1;
$lastPing  = time();

while (true) {
    $read = [$server];
    foreach ($clients as $c) $read[] = $c['sock'];

    $w = $e = null;
    if (@stream_select($read, $w, $e, 1) === false) continue;

    // new connection
    if (in_array($server, $read)) {
        $new = @stream_socket_accept($server, 0);
        if ($new) {
            stream_set_blocking($new, false);
            $id = $nextId++;
            $clients[$id] = ['sock'=>$new,'hs'=>false,'ch'=>null,'ts'=>time()];
        }
        unset($read[array_search($server, $read)]);
    }

    // read clients
    foreach ($read as $sock) {
        $id = null;
        foreach ($clients as $cid => $c) { if ($c['sock'] === $sock) { $id = $cid; break; } }
        if ($id === null) continue;

        // بررسی validity قبل از read
        if (!is_resource($sock) || get_resource_type($sock) !== 'stream') {
            if (isset($clients[$id]['ch']) && $clients[$id]['ch'])
                removeFromChannel($id, $clients[$id]['ch'], $channels);
            unset($clients[$id]);
            continue;
        }
        $data = @fread($sock, 65536);
        if ($data === false || $data === '' || !is_resource($sock)) {
            if (isset($clients[$id]['ch']) && $clients[$id]['ch'])
                removeFromChannel($id, $clients[$id]['ch'], $channels);
            @fclose($sock);
            unset($clients[$id]);
            continue;
        }

        $c = &$clients[$id];

        if (!$c['hs']) {
            if (handshake($sock, $data)) {
                $c['hs'] = true;
                wsLog("Connected #$id");
            } else {
                fclose($sock); unset($clients[$id]);
            }
            continue;
        }

        $msg = decode($data);
        if (!$msg) continue;
        $p = json_decode($msg, true);
        if (!$p) continue;

        $c['ts'] = time();
        $type = $p['type'] ?? '';

        if ($type === 'subscribe') {
            $ch = preg_replace('/[^a-zA-Z0-9_\-]/','',$p['channel']??'');
            if ($ch) {
                if ($c['ch']) removeFromChannel($id, $c['ch'], $channels);
                $c['ch'] = $ch;
                $channels[$ch][] = $id;
                send($sock, ['type'=>'subscribed','channel'=>$ch]);
                wsLog("#$id → channel: $ch");
            }
        }

        elseif ($type === 'broadcast') {
            $ch  = preg_replace('/[^a-zA-Z0-9_\-]/','',$p['channel']??'');
            $out = json_encode(['type'=>'broadcast','data'=>$p['data']??null]);
            foreach ($channels[$ch] ?? [] as $tid) {
                if ($tid !== $id && isset($clients[$tid]))
                    @fwrite($clients[$tid]['sock'], encode($out));
            }
            wsLog("Broadcast→$ch from #$id");
        }

        elseif ($type === 'ping') {
            send($sock, ['type'=>'pong','ts'=>time()]);
        }
    }

    // ping + cleanup every 30s
    if (time() - $lastPing > 30) {
        $lastPing = time();
        $now = time();
        foreach ($clients as $id => $c) {
            if (!$c['hs']) continue;
            if ($now - $c['ts'] > 120) {
                wsLog("Timeout #$id");
                if ($c['ch']) removeFromChannel($id, $c['ch'], $channels);
                @fclose($c['sock']); unset($clients[$id]); continue;
            }
            send($c['sock'], ['type'=>'ping']);
        }
        wsLog("Clients: ".count($clients)." | Channels: ".count($channels));
    }
}
