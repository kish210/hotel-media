# Hotel Media Player — Raspberry Pi

پلیر کیوسک رزبری‌پای: پورتال هتل را با مرورگر در حالت تمام‌صفحه باز
می‌کند، صفحه را بیدار نگه می‌دارد و اگر مرورگر بست یا سرور نیامد، خودش
دوباره بالا می‌آورد. فقط کتابخانه‌ی استاندارد پایتون ۳ لازم است.

## نصب

```bash
sudo bash install.sh --server http://192.168.1.100 --code SCR001
```

`install.sh` این کارها را می‌کند:

1. نصب `python3`، `x11-xserver-utils`، `unclutter` و مرورگر (`chromium`
   یا `chromium-browser`)
2. کپی `player.py` به `/opt/hotel-media-player/`
3. ساخت `/etc/hotel-media-player/config.json` از `config.example.json`
4. نصب و فعال‌سازی سرویس `hotel-media-player` (با کاربر گرافیکی واقعی)

برای ورود خودکار به محیط گرافیکی (لازم برای بالا آمدن پلیر بعد از بوت):

```bash
sudo raspi-config    # System Options → Boot / Auto Login → Desktop Autologin
```

## پیکربندی

`/etc/hotel-media-player/config.json`:

| کلید | پیش‌فرض | توضیح |
|---|---|---|
| `server_url` | — | آدرس سرور، مثل `http://192.168.1.100` |
| `screen_code` | — | کد صفحه از پنل مدیریت |
| `browser` | `auto` | `auto` / `chromium` / `chromium-browser` / `cog` |
| `user_agent` | `HotelMediaRPi/1.0 …` | برای ثبت دستگاه با پلتفرم `rpi` |
| `kiosk` | `true` | `--kiosk` مرورگر |
| `disable_blanking` | `true` | `xset` برای خاموش‌نشدن صفحه |
| `hide_cursor` | `true` | `unclutter` |
| `restart_seconds` | `10` | مکث بین تلاش‌ها |
| `reload_hour` | `4` | تازه‌سازی روزانه‌ی مرورگر (۰ = خاموش) |
| `server_wait` | `60` | انتظار برای بالا آمدن سرور |
| `extra_args` | `[]` | آرگومان‌های اضافه‌ی مرورگر |

بدون ویرایش دستی:

```bash
sudo python3 /opt/hotel-media-player/player.py --server http://192.168.1.100 --code SCR001
sudo systemctl restart hotel-media-player
```

## عیب‌یابی

```bash
python3 /opt/hotel-media-player/player.py --check      # تنظیمات + تست سرور
systemctl status hotel-media-player
journalctl -u hotel-media-player -f
```

- **صفحه سیاه**: کاربر سرویس باید همان کاربری باشد که X را اجرا می‌کند
  (`install.sh --user <name>`)، و `DISPLAY=:0` درست باشد.
- **مرورگر پیدا نشد**: `sudo apt install chromium` (یا `cog` روی
  Raspberry Pi OS Lite).
- **کد صفحه گم می‌شود**: پروفایل مرورگر در `/var/lib/hotel-media-player/profile`
  است؛ باید برای کاربر سرویس نوشتنی باشد.
