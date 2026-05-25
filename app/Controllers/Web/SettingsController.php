<?php declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class SettingsController extends Controller
{
    public function index(Request $req): void
    {
        $tenant = $this->db->row("SELECT * FROM tenants WHERE id=?", [Auth::tenantId()]);
        $locations = $this->db->rows("SELECT * FROM locations WHERE tenant_id=? ORDER BY name", [Auth::tenantId()]);
        $apiKeys = [];
        try {
            $apiKeys = $this->db->rows("SELECT * FROM api_keys WHERE tenant_id=? AND is_active=1 ORDER BY created_at DESC", [Auth::tenantId()]);
        } catch (\Throwable) {}
        $this->view('settings.index', compact('tenant','locations','apiKeys') + ['title' => 'تنظیمات سیستم']);
    }

    public function update(Request $req): void
    {
        $section = $req->post('section', 'general');
        if ($section === 'tenant') {
            $this->db->update('tenants', ['name' => $req->post('name')], ['id' => Auth::tenantId()]);
            $this->flash('success', 'تنظیمات ذخیره شد');
        } elseif ($section === 'location') {
            $this->db->insert('locations', ['tenant_id'=>Auth::tenantId(),'name'=>$req->post('name'),'address'=>$req->post('address'),'city'=>$req->post('city'),'country'=>$req->post('country','ایران'),'timezone'=>$req->post('timezone','Asia/Tehran')]);
            $this->flash('success','شعبه اضافه شد');
        } elseif ($section === 'apikey') {
            $key = 'sgn_' . bin2hex(random_bytes(24));
            $this->db->insert('api_keys', ['tenant_id'=>Auth::tenantId(),'user_id'=>Auth::id(),'name'=>$req->post('name','API Key'),'key_hash'=>hash('sha256',$key),'key_prefix'=>substr($key,0,8),'is_active'=>1]);
            $this->flash('success','کلید API: ' . $key . ' — این کلید را ذخیره کنید');
        }
        $this->redirect('/admin/settings');
    }
}
