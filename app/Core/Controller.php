<?php
declare(strict_types=1);
namespace App\Core;

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function view(string $view, array $data = []): void
    {
        Response::view($view, array_merge($data, [
            'auth'  => Auth::user(),
        ]));
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash'][$type] = $message;
        }
    }

    protected function log(string $action, string $subjectType = null, int $subjectId = null, array $old = [], array $new = []): void
    {
        try {
            $user = Auth::user();
            $this->db->insert('activity_logs', [
                'tenant_id'    => $user['tenant_id'] ?? 1,
                'user_id'      => $user['id'] ?? null,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'old_values'   => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_values'   => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'   => request()->ip(),
                'user_agent'   => substr(request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // log نباید باعث crash صفحه بشه
            error_log('[LOG ERROR] ' . $e->getMessage());
        }
    }
}
