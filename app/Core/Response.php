<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    // ── JSON helpers ──────────────────────────────────────────

    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'موفق', int $code = 200): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    public static function notFound(string $message = 'یافت نشد'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'احراز هویت لازم است'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'دسترسی مجاز نیست'): never
    {
        self::error($message, 403);
    }

    public static function validationError(array $errors): never
    {
        self::error('داده‌های ورودی نامعتبر است', 422, $errors);
    }

    // ── View ────────────────────────────────────────────────

    public static function view(string $view, array $data = []): void
    {
        $viewPath = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            if (APP_DEBUG) {
                http_response_code(500);
                echo "<h1>View not found: $view</h1>";
                echo "<p>Path: $viewPath</p>";
                exit;
            }
            throw new \RuntimeException("View not found: $view ($viewPath)");
        }

        // inject helpers
        $data['e']           = 'htmlspecialchars';
        $data['formatBytes'] = 'formatBytes';
        $data['timeAgo']     = 'timeAgo';

        extract($data, EXTR_SKIP);
        include $viewPath;
    }

    // ── Redirect ─────────────────────────────────────────────

    public static function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }

    public static function paginated(array $result, string $message = 'موفق', int $code = 200): void
    {
        self::json([
            'success'      => true,
            'message'      => $message,
            'data'         => $result['data']         ?? $result,
            'total'        => $result['total']        ?? count($result['data'] ?? $result),
            'per_page'     => $result['per_page']     ?? 20,
            'current_page' => $result['current_page'] ?? 1,
            'last_page'    => $result['last_page']    ?? 1,
            'from'         => $result['from']         ?? 1,
            'to'           => $result['to']           ?? 0,
        ], $code);
    }

}