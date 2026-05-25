<?php
declare(strict_types=1);

namespace App\Modules\Core;

use App\Core\Database;

/**
 * BaseModule — Abstract base class for all content modules
 * Every module must extend this class and implement required methods.
 */
abstract class BaseModule
{
    protected Database $db;
    protected int $tenantId;
    protected array $config = [];

    // ── Identity ─────────────────────────────────────────────
    abstract public function id(): string;          // unique slug: 'fids', 'hotel', 'menu'
    abstract public function name(): string;        // Display name: 'سامانه اطلاع‌رسانی پرواز'
    abstract public function nameEn(): string;      // English name
    abstract public function description(): string; // Short description
    abstract public function version(): string;     // '1.0.0'
    abstract public function icon(): string;        // FontAwesome icon class: 'fas fa-plane'
    abstract public function color(): string;       // Hex color: '#0ea5e9'
    abstract public function category(): string;    // 'transport' | 'hospitality' | 'retail' | 'info'

    // ── Capabilities ─────────────────────────────────────────
    public function hasAdminPanel(): bool    { return true; }
    public function hasPlayerWidget(): bool  { return true; }
    public function hasApi(): bool           { return true; }
    public function hasScheduler(): bool     { return false; }
    public function requiresExternalApi(): bool { return false; }

    // ── Zone types this module provides ──────────────────────
    abstract public function zoneTypes(): array;
    /*
     * Return array of zone type definitions:
     * [
     *   'id'          => 'fids_departures',
     *   'label'       => 'پروازهای عزیمت',
     *   'icon'        => 'fas fa-plane-departure',
     *   'description' => 'نمایش لیست پروازهای خروجی',
     *   'defaultSize' => ['w' => 1920, 'h' => 600],
     *   'settings'    => [...configurable fields],
     * ]
     */

    // ── DB migrations ─────────────────────────────────────────
    abstract public function migrations(): array; // list of SQL strings to run on install

    // ── Lifecycle ─────────────────────────────────────────────
    public function install(): bool
    {
        try {
            foreach ($this->migrations() as $sql) {
                $this->db->query($sql);
            }
            $this->db->query(
                "INSERT IGNORE INTO modules (id, name, version, is_active, tenant_id, installed_at) VALUES (?,?,?,1,?,NOW())",
                [$this->id(), $this->name(), $this->version(), $this->tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Module install failed [{$this->id()}]: " . $e->getMessage());
            return false;
        }
    }

    public function uninstall(): bool
    {
        $this->db->query("UPDATE modules SET is_active=0 WHERE id=? AND tenant_id=?", [$this->id(), $this->tenantId]);
        return true;
    }

    public function isInstalled(): bool
    {
        return (bool)$this->db->value("SELECT id FROM modules WHERE id=? AND tenant_id=? AND is_active=1", [$this->id(), $this->tenantId]);
    }

    // ── Settings ──────────────────────────────────────────────
    public function getSettings(): array
    {
        $row = $this->db->row("SELECT settings FROM modules WHERE id=? AND tenant_id=?", [$this->id(), $this->tenantId]);
        return $row ? (json_decode($row['settings'] ?? '{}', true) ?? []) : [];
    }

    public function saveSettings(array $settings): void
    {
        $this->db->query("UPDATE modules SET settings=? WHERE id=? AND tenant_id=?",
            [json_encode($settings), $this->id(), $this->tenantId]);
    }

    // ── Player render ─────────────────────────────────────────
    /** Returns HTML/JS for the player widget for a given zone type */
    abstract public function renderPlayerWidget(string $zoneType, array $settings = []): string;

    /** Returns API data for the player to consume */
    public function getPlayerData(string $zoneType, array $settings = []): array { return []; }

    // ── Admin summary card data ───────────────────────────────
    public function getDashboardStats(): array { return []; }

    // ── Constructor ───────────────────────────────────────────
    public function __construct(int $tenantId = 1)
    {
        $this->db       = Database::getInstance();
        $this->tenantId = $tenantId;
        $this->config   = $this->getSettings();
    }

    public function setSetting(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}