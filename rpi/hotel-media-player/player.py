#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Hotel Media — Raspberry Pi Player
=================================
پلیر کیوسک برای رزبری‌پای (Raspberry Pi OS). همان پورتال هتل را در
حالت تمام‌صفحه با مرورگر می‌بازد، صفحه را بیدار نگه می‌دارد و اگر
مرورگر بست یا سرور نیامد، خودش دوباره بالا می‌آورد.

فقط کتابخانه‌ی استاندارد پایتون لازم است — هیچ pip نصب نمی‌شود.

دستورها:
    player.py                    # اجرا (از systemd)
    player.py --check            # نمایش تنظیمات و تست دسترسی سرور
    player.py --setup            # پرسش تعاملی آدرس سرور و کد صفحه
    player.py --server URL --code SCR001   # تنظیم بدون پرسش
    player.py --once             # یک بار اجرا، بدون حلقه‌ی بازیابی

پیکربندی: /etc/hotel-media-player/config.json
"""

import argparse
import json
import os
import shutil
import signal
import subprocess
import sys
import tempfile
import time
from datetime import datetime, timedelta
from urllib.error import URLError, HTTPError
from urllib.request import Request, urlopen

CONFIG_PATH = os.environ.get("HMP_CONFIG", "/etc/hotel-media-player/config.json")
STATE_DIR   = os.environ.get("HMP_STATE", "/var/lib/hotel-media-player")
LOG_PREFIX  = "[hotel-media-player]"

DEFAULTS = {
    # اتصال
    "server_url":        "",
    "screen_code":       "",
    # مرورگر
    "browser":           "auto",       # auto | chromium | chromium-browser | cog
    "user_agent":        "HotelMediaRPi/1.0 (Raspberry Pi; Linux)",
    "extra_args":        [],
    "kiosk":             True,
    # صفحه
    "disable_blanking":  True,
    "hide_cursor":       True,
    # بازیابی
    "restart_seconds":   10,           # مکث بین تلاش‌ها
    "reload_hour":       4,            # هر شب ساعت ۴ مرورگر تازه شود (۰ = خاموش)
    "server_wait":       60,           # چند ثانیه منتظر بالا آمدن سرور بمانیم
}

# خروجی UTF-8 حتی وقتی LANG=C است — وگرنه پیام‌های فارسی در
# journald/کنسول با UnicodeEncodeError می‌افتند.
try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except (AttributeError, ValueError):
    pass

BROWSER_CANDIDATES = [
    "chromium-browser",
    "chromium",
    "google-chrome",
    "cog",
]


def log(msg):
    print("%s %s" % (LOG_PREFIX, msg), flush=True)


# ── پیکربندی ────────────────────────────────────────────────────────────────

def load_config(path=CONFIG_PATH):
    cfg = dict(DEFAULTS)
    try:
        with open(path, "r", encoding="utf-8") as fh:
            data = json.load(fh)
        if isinstance(data, dict):
            cfg.update({k: v for k, v in data.items() if v is not None})
    except FileNotFoundError:
        pass
    except (ValueError, OSError) as exc:
        log("پیکربندی خوانده نشد (%s) — پیش‌فرض‌ها استفاده می‌شوند" % exc)
    return cfg


def save_config(cfg, path=CONFIG_PATH):
    """نوشتن اتمیک تا قطع برق وسط نوشتن فایل را خراب نکند."""
    folder = os.path.dirname(os.path.abspath(path))
    try:
        os.makedirs(folder, exist_ok=True)
    except OSError as exc:
        log("پوشه‌ی پیکربندی ساخته نشد: %s" % exc)
        raise

    fd, tmp = tempfile.mkstemp(dir=folder, prefix=".config-")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            json.dump(cfg, fh, ensure_ascii=False, indent=2)
            fh.write("\n")
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def player_url(cfg):
    base = (cfg.get("server_url") or "").rstrip("/")
    if not base:
        return ""
    code = (cfg.get("screen_code") or "").strip().upper()
    return base + "/player/" + code if code else base + "/player/"


# ── مرورگر ──────────────────────────────────────────────────────────────────

def find_browser(cfg):
    wanted = (cfg.get("browser") or "auto").strip()
    if wanted and wanted != "auto":
        found = shutil.which(wanted)
        if found:
            return found
        log("مرورگر «%s» پیدا نشد — جست‌وجوی خودکار" % wanted)
    for name in BROWSER_CANDIDATES:
        found = shutil.which(name)
        if found:
            return found
    return ""


def profile_dir():
    """پروفایل مرورگر باید نوشتنی باشد؛ وگرنه localStorage و کوکی
       کد دستگاه بعد از هر ریبوت پاک می‌شود."""
    for cand in (os.path.join(STATE_DIR, "profile"),
                 os.path.join(os.path.expanduser("~"), ".hotel-media-player")):
        try:
            os.makedirs(cand, exist_ok=True)
            if os.access(cand, os.W_OK):
                return cand
        except OSError:
            continue
    return tempfile.mkdtemp(prefix="hmp-profile-")


def build_command(browser, cfg, url):
    name = os.path.basename(browser)

    if name.startswith("cog"):
        # cog (WebKit) روی Raspberry Pi OS Lite — ساده‌ترین کیوسک
        return [browser, url]

    args = [
        browser,
        "--user-data-dir=" + profile_dir(),
        "--user-agent=" + str(cfg.get("user_agent") or DEFAULTS["user_agent"]),
        "--noerrdialogs",
        "--disable-infobars",
        "--disable-session-crashed-bubble",
        "--disable-translate",
        "--disable-features=Translate,TranslateUI,MediaRouter",
        "--autoplay-policy=no-user-gesture-required",
        "--password-store=basic",
        "--no-first-run",
        "--no-default-browser-check",
        "--check-for-update-interval=31536000",
        "--overscroll-history-navigation=0",
    ]
    if cfg.get("kiosk", True):
        args.append("--kiosk")
    else:
        args.append("--start-fullscreen")

    extra = cfg.get("extra_args") or []
    if isinstance(extra, list):
        args.extend(str(a) for a in extra)

    args.append(url)
    return args


# ── صفحه ────────────────────────────────────────────────────────────────────

def apply_display_options(cfg):
    """خاموشی صفحه و اسکرین‌سیور خاموش شوند و نشانگر ماوس پنهان شود.
       همه بی‌صدا شکست می‌خورند: بدون X هم پلیر باید کار کند."""
    if cfg.get("disable_blanking", True):
        for cmd in (["xset", "s", "off"], ["xset", "-dpms"], ["xset", "s", "noblank"]):
            try:
                subprocess.run(cmd, stdout=subprocess.DEVNULL,
                               stderr=subprocess.DEVNULL, timeout=5)
            except (OSError, subprocess.SubprocessError):
                pass

    if cfg.get("hide_cursor", True) and shutil.which("unclutter"):
        try:
            running = subprocess.run(["pgrep", "-x", "unclutter"],
                                     stdout=subprocess.DEVNULL,
                                     stderr=subprocess.DEVNULL).returncode == 0
            if not running:
                subprocess.Popen(["unclutter", "-idle", "0.5", "-root"],
                                 stdout=subprocess.DEVNULL,
                                 stderr=subprocess.DEVNULL)
        except (OSError, subprocess.SubprocessError):
            pass


# ── سرور ────────────────────────────────────────────────────────────────────

def server_reachable(url, timeout=5):
    try:
        req = Request(url, headers={"User-Agent": DEFAULTS["user_agent"]})
        with urlopen(req, timeout=timeout) as res:
            return res.status < 500
    except HTTPError as exc:
        return exc.code < 500
    except (URLError, OSError, ValueError):
        return False


def wait_for_server(url, seconds):
    deadline = time.time() + max(0, seconds)
    while time.time() < deadline:
        if server_reachable(url):
            return True
        time.sleep(3)
    return False


# ── حلقه‌ی اصلی ─────────────────────────────────────────────────────────────

def next_reload_at(hour):
    """زمان بعدی تازه‌سازی روزانه (ساعت محلی)."""
    now = datetime.now()
    target = now.replace(hour=hour, minute=0, second=0, microsecond=0)
    if target <= now:
        target = target + timedelta(days=1)
    return target


def run_once(cfg, browser):
    url = player_url(cfg)
    cmd = build_command(browser, cfg, url)
    log("اجرای مرورگر: %s" % " ".join(cmd))
    proc = subprocess.Popen(cmd)
    return proc


def supervise(cfg):
    stop = {"flag": False}

    def _stop(signum, _frame):
        stop["flag"] = True
        log("سیگنال %s — خروج" % signum)

    signal.signal(signal.SIGTERM, _stop)
    signal.signal(signal.SIGINT, _stop)

    if not cfg.get("server_url"):
        log("آدرس سرور تنظیم نشده — «player.py --setup» را اجرا کنید")
        return 2

    apply_display_options(cfg)

    browser = find_browser(cfg)
    if not browser:
        log("هیچ مرورگری نصب نیست (chromium یا cog) — نصب: "
            "sudo apt install chromium-browser")
        return 3
    log("مرورگر: %s" % browser)

    url = player_url(cfg)
    log("پورتال: %s" % url)

    retry = max(3, int(cfg.get("restart_seconds") or 10))
    reload_hour = int(cfg.get("reload_hour") or 0)
    reload_at = next_reload_at(reload_hour) if reload_hour else None

    while not stop["flag"]:
        if not wait_for_server(url, int(cfg.get("server_wait") or 60)):
            log("سرور پاسخ نمی‌دهد — %s ثانیه بعد دوباره" % retry)
            time.sleep(retry)
            continue

        proc = run_once(cfg, browser)
        started = time.time()

        while proc.poll() is None and not stop["flag"]:
            time.sleep(2)
            if reload_at and datetime.now() >= reload_at:
                log("تازه‌سازی روزانه — مرورگر دوباره باز می‌شود")
                terminate(proc)
                reload_at = next_reload_at(reload_hour)
                break

        if stop["flag"]:
            terminate(proc)
            break

        code = proc.poll()
        lived = time.time() - started
        log("مرورگر بسته شد (کد %s، %d ثانیه) — دوباره در %s ثانیه"
            % (code, int(lived), retry))
        time.sleep(retry)

    return 0


def terminate(proc):
    if proc is None or proc.poll() is not None:
        return
    try:
        proc.terminate()
        try:
            proc.wait(timeout=10)
        except subprocess.TimeoutExpired:
            proc.kill()
    except OSError:
        pass


# ── CLI ─────────────────────────────────────────────────────────────────────

def cmd_check(cfg):
    url = player_url(cfg)
    browser = find_browser(cfg)
    print("پیکربندی: %s" % CONFIG_PATH)
    print(json.dumps(cfg, ensure_ascii=False, indent=2))
    print("مرورگر:   %s" % (browser or "پیدا نشد"))
    print("پورتال:   %s" % (url or "تنظیم نشده"))
    if url:
        ok = server_reachable(url)
        print("سرور:     %s" % ("در دسترس ✓" if ok else "پاسخ نمی‌دهد ✗"))
    return 0 if url else 1


def cmd_setup(cfg, server, code):
    if server:
        cfg["server_url"] = server.rstrip("/")
    elif sys.stdin.isatty():
        answer = input("آدرس سرور [%s]: " % (cfg.get("server_url") or "http://192.168.1.100")).strip()
        if answer:
            cfg["server_url"] = answer.rstrip("/")

    if code:
        cfg["screen_code"] = code.strip().upper()
    elif sys.stdin.isatty():
        answer = input("کد صفحه [%s]: " % (cfg.get("screen_code") or "SCR001")).strip()
        if answer:
            cfg["screen_code"] = answer.strip().upper()

    if not cfg.get("server_url"):
        log("آدرس سرور لازم است")
        return 1

    save_config(cfg)
    log("ذخیره شد در %s" % CONFIG_PATH)
    log("برای اعمال: sudo systemctl restart hotel-media-player")
    return 0


def main(argv=None):
    parser = argparse.ArgumentParser(description="Hotel Media Raspberry Pi player")
    parser.add_argument("--config", default=CONFIG_PATH, help="مسیر فایل پیکربندی")
    parser.add_argument("--check", action="store_true", help="نمایش تنظیمات و تست سرور")
    parser.add_argument("--setup", action="store_true", help="تنظیم تعاملی")
    parser.add_argument("--server", help="آدرس سرور (بدون پرسش)")
    parser.add_argument("--code", help="کد صفحه (بدون پرسش)")
    parser.add_argument("--once", action="store_true", help="یک بار اجرا، بدون حلقه")
    args = parser.parse_args(argv)

    cfg = load_config(args.config)

    if args.setup or args.server or args.code:
        return cmd_setup(cfg, args.server, args.code)
    if args.check:
        return cmd_check(cfg)
    if args.once:
        browser = find_browser(cfg)
        url = player_url(cfg)
        if not browser or not url:
            log("مرورگر یا آدرس سرور تنظیم نشده")
            return 1
        apply_display_options(cfg)
        return run_once(cfg, browser).wait()

    return supervise(cfg)


if __name__ == "__main__":
    sys.exit(main())
