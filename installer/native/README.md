# SignageCMS — Native Windows Installer (no Docker)

سماع رایانه کیش | kishwifi.com

This folder builds a **single `.exe` installer** that a non-technical person can
run by double-clicking. It installs and starts SignageCMS completely — with **no
Docker, no WSL2, no manual database setup**. Everything the app needs (a portable
PHP, a portable MariaDB database, and the realtime server) is bundled inside the
installer and registered as auto-starting Windows services.

---

## For the end user (the person installing it)

1. Double-click **`SignageCMS-vX.Y.Z-setup.exe`**.
2. Click **Next → Next → Install** (accept the admin/UAC prompt).
3. Wait a couple of minutes while it sets things up.
4. The dashboard opens automatically at **http://localhost/admin**.

| | |
|---|---|
| **Login** | `admin@signagecms.com` |
| **Password** | `Admin@123456` *(change it after first login)* |

Start menu → **SignageCMS** has shortcuts to open the dashboard, start/stop the
services, check status, and uninstall. The server starts automatically every time
Windows boots — nothing to launch by hand.

> **Ports:** the web dashboard uses port **80** and the realtime server uses
> **8080**. If something else (IIS, Skype, another web server) is already using
> port 80, free it before installing, or rebuild the installer with a different
> `WebPort` (see the `.iss` file).

---

## For the developer (the person building the `.exe`)

### Requirements (build machine only)
- Windows x64
- [Inno Setup 6](https://jrsoftware.org/isdl.php) (provides `ISCC.exe`)
- Internet access (PHP / MariaDB / NSSM are downloaded once and cached)

### Build

```powershell
# from the repo root
.\scripts\build-native-installer.ps1 -Version 1.6.2
```

The script will:
1. Download portable **PHP**, **MariaDB**, and **NSSM** into `dist\downloads\` (cached).
2. Assemble `dist\native-payload\` = app files + `runtime\{php,mariadb,nssm,scripts}`.
3. Write a production `php.ini` with every extension the app uses.
4. Compile `SignageCMS-Native.iss` into:

```
installer\native\Output\SignageCMS-vX.Y.Z-setup.exe
```

Ship that one file. Nothing else is needed on the target PC.

To bundle newer runtime versions, pass the matching zip URLs:

```powershell
.\scripts\build-native-installer.ps1 `
    -PhpZipUrl     "https://windows.php.net/downloads/releases/php-8.3.x-nts-Win32-vs16-x64.zip" `
    -MariaDbZipUrl "https://archive.mariadb.org/mariadb-11.4.x/winx64-packages/mariadb-11.4.x-winx64.zip"
```
> PHP **must** be the x64 **NTS** (non-thread-safe) Windows build — that's the one
> the built-in web server uses.

---

## How it works under the hood

The installer copies everything to `C:\SignageCMS` and then runs
[`runtime/provision.ps1`](runtime/provision.ps1), which:

1. Generates `.env` with random secrets (DB password, `APP_KEY`, JWT).
2. Initializes a private MariaDB data directory in `C:\SignageCMS\data\mysql`.
3. Registers MariaDB as the **`SignageCMS-MySQL`** service and starts it.
4. Creates the `signage_cms` database + `signage_user`, then runs the app's own
   installer (`public/install.php`) to import the schema and seed the admin user.
5. Registers two more services via NSSM:
   - **`SignageCMS-Web`** — `php -S 0.0.0.0:80` with `public/server-router.php`
   - **`SignageCMS-WS`** — the pure-PHP WebSocket server
6. Opens the Windows Firewall for ports 80 and 8080.

Uninstalling runs [`runtime/uninstall.ps1`](runtime/uninstall.ps1) to remove the
three services and firewall rules before deleting the files.

### Files in this folder
| File | Purpose |
|------|---------|
| `SignageCMS-Native.iss` | Inno Setup script (defines the installer) |
| `runtime/provision.ps1` | Post-install setup (DB, services, firewall) |
| `runtime/uninstall.ps1` | Service/firewall teardown on uninstall |
| `runtime/Manage-SignageCMS.ps1` | start / stop / restart / status helper |
| `runtime/my.ini.template` | MariaDB config template |

> Binaries (PHP/MariaDB/NSSM) and `dist/` are **not** committed — they are
> downloaded at build time. Only the scripts above live in git.
