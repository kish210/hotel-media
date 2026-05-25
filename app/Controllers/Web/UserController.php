<?php declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class UserController extends Controller
{
    public function index(Request $req): void
    {
        $users = $this->db->rows("SELECT * FROM users WHERE tenant_id=? AND deleted_at IS NULL ORDER BY role,name", [Auth::tenantId()]);
        $this->view('users.index', ['title' => 'مدیریت کاربران', 'users' => $users]);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['name'=>'required','email'=>'required|email','password'=>'required|min:8','role'=>'required']);
        if ($errors) { $this->flash('error', 'خطا در اطلاعات'); $this->redirect('/admin/users'); return; }
        if ($this->db->value("SELECT id FROM users WHERE email=? AND tenant_id=?", [$req->post('email'), Auth::tenantId()])) {
            $this->flash('error', 'این ایمیل قبلاً ثبت شده'); $this->redirect('/admin/users'); return;
        }
        $this->db->insert('users', [
            'tenant_id' => Auth::tenantId(),
            'name'      => $req->post('name'),
            'email'     => $req->post('email'),
            'password'  => Auth::hashPassword($req->post('password')),
            'role'      => $req->post('role', 'editor'),
            'is_active' => 1,
        ]);
        $this->flash('success', 'کاربر ایجاد شد');
        $this->redirect('/admin/users');
    }

    public function update(Request $req, array $params): void
    {
        $data = ['name' => $req->post('name'), 'role' => $req->post('role'), 'is_active' => (int)$req->post('is_active', 1)];
        if ($req->post('password')) $data['password'] = Auth::hashPassword($req->post('password'));
        $this->db->update('users', $data, ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'کاربر به‌روز شد');
        $this->redirect('/admin/users');
    }
}
