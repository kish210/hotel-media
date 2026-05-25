<?php declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Database, Request, Response};

class RateLimitMiddleware
{
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(int $maxAttempts = 60, int $windowSeconds = 60)
    {
        $this->maxAttempts  = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(Request $request, callable $next): void
    {
        $db  = Database::getInstance();
        $key = 'rl:' . $request->ip() . ':' . $request->path();

        // Cleanup expired
        $db->query("DELETE FROM rate_limits WHERE reset_at < NOW()");

        $record = $db->row("SELECT * FROM rate_limits WHERE `key`=?", [$key]);
        if ($record) {
            if ($record['attempts'] >= $this->maxAttempts) {
                header('Retry-After: ' . $this->windowSeconds);
                Response::error('تعداد درخواست‌ها بیش از حد مجاز', 429);
            }
            $db->query("UPDATE rate_limits SET attempts=attempts+1 WHERE `key`=?", [$key]);
        } else {
            $db->insert('rate_limits', [
                'key'      => $key,
                'attempts' => 1,
                'reset_at' => date('Y-m-d H:i:s', time() + $this->windowSeconds),
            ]);
        }

        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxAttempts - ($record['attempts'] ?? 1)));
        $next();
    }
}
