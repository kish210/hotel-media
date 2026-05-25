<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $params = [];

    // ── Path / URI ──────────────────────────────────────────

    public function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = '/' . ltrim($path ?? '/', '/');
        // trailing slash حذف می‌شه (به جز root /)
        return ($path !== '/') ? rtrim($path, '/') : '/';
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // Allow method override via POST _method field
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
            if (in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'])) {
                return strtoupper($override);
            }
        }
        return strtoupper($method);
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    // ── Input ───────────────────────────────────────────────

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function input(string $key = null, mixed $default = null): mixed
    {
        // For JSON requests (e.g. PUT/POST with Content-Type: application/json),
        // PHP does NOT populate $_POST — we must read from php://input instead.
        if ($this->isJson() && empty($_POST)) {
            $data = array_merge($_GET, $this->json());
        } else {
            $data = array_merge($_GET, $_POST);
        }

        if ($key === null) return $data;
        return $data[$key] ?? $default;
    }

    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $_POST;
        return $_POST[$key] ?? $default;
    }

    public function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $_GET;
        return $_GET[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public function filled(string $key): bool
    {
        $val = $this->input($key);
        return $val !== null && $val !== '';
    }

    /** Returns input without _token */
    public function safe(): array
    {
        $data = array_merge($_GET, $_POST);
        unset($data['_token'], $data['_method']);
        return $data;
    }

    // ── Headers ─────────────────────────────────────────────

    public function header(string $key, string $default = ''): string
    {
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$server] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // ── File ────────────────────────────────────────────────

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    // ── Type detection ──────────────────────────────────────

    public function isJson(): bool
    {
        $ct = $this->header('Content-Type');
        return str_contains($ct, 'application/json');
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    // ── Client info ─────────────────────────────────────────

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    // ── Route params (set by Router) ────────────────────────

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    // ── Validation ──────────────────────────────────────────

    public function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = $this->input($field);
            foreach (explode('|', $ruleStr) as $rule) {
                [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($ruleName) {
                    'required' => ($value === null || $value === '') ? "فیلد $field الزامی است" : null,
                    'email'    => ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) ? "ایمیل نامعتبر" : null,
                    'numeric'  => ($value !== '' && $value !== null && !is_numeric($value)) ? "باید عدد باشد" : null,
                    'min'      => (strlen((string)$value) < (int)$ruleParam) ? "حداقل $ruleParam کاراکتر" : null,
                    'max'      => (strlen((string)$value) > (int)$ruleParam) ? "حداکثر $ruleParam کاراکتر" : null,
                    'in'       => ($value && !in_array($value, explode(',', $ruleParam ?? ''))) ? "مقدار نامعتبر" : null,
                    'url'      => ($value && !filter_var($value, FILTER_VALIDATE_URL)) ? "آدرس URL نامعتبر" : null,
                    default    => null,
                };
                if ($error) { $errors[$field] = $error; break; }
            }
        }
        return $errors;
    }

    private ?array $_jsonCache = null;

    public function json(): array
    {
        if ($this->_jsonCache !== null) return $this->_jsonCache;
        $raw = file_get_contents('php://input');
        if (!$raw) return $this->_jsonCache = [];
        $data = json_decode($raw, true);
        return $this->_jsonCache = (is_array($data) ? $data : []);
    }
}