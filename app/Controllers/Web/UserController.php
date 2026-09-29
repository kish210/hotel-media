<?php declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth, Branch};

/**
 * مدیریت کاربران پنل.
 *
 * پیش از این هیچ بررسی نقشی نداشت: هر کاربر واردشده، حتی «بازدیدکننده»،
 * می‌توانست با یک POST نقش خودش را super_admin کند. و ردیف کامل کاربر
 * (هش رمز، کلید ۲FA، remember_token) برای دکمه‌ی ویرایش در HTML صفحه
 * چاپ می‌شد.
 */
class UserController extends Controller
{
    private const ROLES = ['super_admin', 'admin', 'manager', 'editor', 'viewer'];

    public function index(Request $req): void
    {
        if (!$this->guard()) return;
        $users = $this->db->rows(
            "SELECT u.id, u.name, u.email, u.role, u.location_id, u.is_active, u.last_login_at, l.name AS location_name
               FROM users u LEFT JOIN locations l ON l.id = u.location_id
              WHERE u.tenant_id=? AND u.deleted_at IS NULL" . (Branch::locked() !== null ? ' AND u.location_id = ' . Branch::locked() : '') . "
              ORDER BY u.role, u.name",
            [Auth::tenantId()]
        );
        $locations = Branch::locked() !== null ? [] :
            $this->db->rows('SELECT id, name FROM locations WHERE tenant_id=? AND is_active=1 ORDER BY name', [Auth::tenantId()]);
        $this->view('users.index', [
            'title'     => 'مدیریت کاربران',
            'users'     => $users,
            'locations' => $locations,
            'roles'     => $this->assignable(),
        ]);
    }

    public function store(Request $req): void
    {
        if (!$this->guard()) return;
        $errors = $req->validate(['name'=>'required','email'=>'required|email','password'=>'required|min:8','role'=>'required']);
        if ($errors) { $this->flash('error', 'خطا در اطلاعات'); $this->redirect('/admin/users'); return; }
        $role = (string)$req->post('role', 'editor');
        if (!in_array($role, $this->assignable(), true)) { $this->flash('error', 'این نقش را نمی‌توانید بدهید'); $this->redirect('/admin/users'); return; }
        if ($this->db->value("SELECT id FROM users WHERE email=? AND tenant_id=?", [$req->post('email'), Auth::tenantId()])) {
            $this->flash('error', 'این ایمیل قبلاً ثبت شده'); $this->redirect('/admin/users'); return;
        }
        $id = (int)$this->db->insert('users', [
            'tenant_id'   => Auth::tenantId(),
            'name'        => $req->post('name'),
            'email'       => $req->post('email'),
            'password'    => Auth::hashPassword($req->post('password')),
            'role'        => $role,
            'location_id' => $this->location($req),
            'is_active'   => 1,
        ]);
        $this->log('user.create', 'user', $id, [], ['role' => $role]);
        $this->flash('success', 'کاربر ایجاد شد');
        $this->redirect('/admin/users');
    }

    public function update(Request $req, array $params): void
    {
        if (!$this->guard()) return;
        $id     = (int)$params['id'];
        $target = $this->db->row('SELECT id, role, location_id FROM users WHERE id=? AND tenant_id=? AND deleted_at IS NULL', [$id, Auth::tenantId()]);
        if (!$target || !Branch::allows($target['location_id'])) { $this->flash('error', 'کاربر یافت نشد'); $this->redirect('/admin/users'); return; }

        /* ادمین نمی‌تواند مدیر ارشد را ویرایش کند یا کسی را مدیر ارشد کند */
        $role = (string)$req->post('role', $target['role']);
        if (!in_array($target['role'], $this->assignable(), true) || !in_array($role, $this->assignable(), true)) {
            $this->flash('error', 'اجازه‌ی این تغییر را ندارید'); $this->redirect('/admin/users'); return;
        }
        /* نقش و شعبه‌ی خود را عوض نکنید: قفل شدن بیرون از پنل با یک کلیک */
        $self = $id === Auth::id();

        $data = ['name' => $req->post('name')];
        if (!$self) {
            $data['role']        = $role;
            $data['location_id'] = $this->location($req);
            $data['is_active']   = (int)$req->post('is_active', 1) ? 1 : 0;
        }
        if ($req->post('password')) $data['password'] = Auth::hashPassword($req->post('password'));
        $this->db->update('users', $data, ['id' => $id, 'tenant_id' => Auth::tenantId()]);
        $this->log('user.update', 'user', $id, ['role' => $target['role']], ['role' => $data['role'] ?? $target['role']]);
        $this->flash('success', $self ? 'حساب شما به‌روز شد (نقش و شعبه‌ی خودتان را کاربر دیگری باید عوض کند)' : 'کاربر به‌روز شد');
        $this->redirect('/admin/users');
    }

    // ─────────────────────────────────────────────────────────────

    private function guard(): bool
    {
        if (in_array(Auth::role(), ['super_admin', 'admin'], true)) return true;
        http_response_code(403);
        $this->flash('error', 'مدیریت کاربران فقط برای مدیر سامانه است');
        $this->redirect('/admin');
        return false;
    }

    /** @return list<string> */
    private function assignable(): array
    {
        return Auth::role() === 'super_admin' ? self::ROLES : array_values(array_diff(self::ROLES, ['super_admin']));
    }

    private function location(Request $req): ?int
    {
        /* ادمین یک شعبه فقط برای شعبه‌ی خودش کاربر می‌سازد */
        $l = Branch::forNew($req->post('location_id', 0));
        if ($l === null) return null;
        return $this->db->exists('locations', ['id' => $l, 'tenant_id' => Auth::tenantId()]) ? $l : null;
    }
}
