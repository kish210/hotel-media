<?php declare(strict_types=1);
namespace App\Models;
use App\Core\{Database, Auth};

class User
{
    private Database $db;
    private int $tenantId;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->tenantId = Auth::tenantId();
    }

    public function all(array $filters = []): array
    {
        $sql = "SELECT id,name,email,role,is_active,last_login_at,created_at FROM users WHERE tenant_id=? AND deleted_at IS NULL";
        $params = [$this->tenantId];
        if (!empty($filters['role']))   { $sql .= " AND role=?"; $params[] = $filters['role']; }
        if (!empty($filters['search'])) { $sql .= " AND (name LIKE ? OR email LIKE ?)"; $params[] = "%{$filters['search']}%"; $params[] = "%{$filters['search']}%"; }
        $sql .= " ORDER BY role,name";
        return $this->db->rows($sql, $params);
    }

    public function find(int $id): ?array
    {
        return $this->db->row("SELECT * FROM users WHERE id=? AND tenant_id=? AND deleted_at IS NULL", [$id, $this->tenantId]);
    }

    public function create(array $data): int|string
    {
        $data['tenant_id'] = $this->tenantId;
        $data['password']  = Auth::hashPassword($data['password']);
        return $this->db->insert('users', $data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['password']) && $data['password']) {
            $data['password'] = Auth::hashPassword($data['password']);
        } else {
            unset($data['password']);
        }
        return $this->db->update('users', $data, ['id' => $id, 'tenant_id' => $this->tenantId]) >= 0;
    }

    public function delete(int $id): bool
    {
        if ($id === Auth::id()) return false;
        return $this->db->update('users', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id, 'tenant_id' => $this->tenantId]) > 0;
    }
}
