#!/bin/bash
# ╔══════════════════════════════════════════════════════════════════════════╗
# ║   SignageCMS In-Flight Bridge — Raspberry Pi Setup Script               ║
# ║   Tested on: Raspberry Pi OS Bullseye / Bookworm (32-bit & 64-bit)      ║
# ║                                                                          ║
# ║   Installs:                                                              ║
# ║     • gpsd (GPS daemon)                                                  ║
# ║     • rtl-sdr + dump1090-fa  (ADS-B receiver)                           ║
# ║     • SignageCMS Bridge service (Python 3, no extra pip packages)        ║
# ╚══════════════════════════════════════════════════════════════════════════╝

set -euo pipefail
BRIDGE_VER="1.0"
BRIDGE_DIR="/opt/inflight-bridge"
CONFIG_DIR="/etc/inflight-bridge"
SERVICE_FILE="/etc/systemd/system/inflight-bridge.service"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# ── Root check ─────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    echo "❌  این اسکریپت باید با sudo اجرا شود:"
    echo "    sudo bash setup.sh"
    exit 1
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  SignageCMS In-Flight Bridge v${BRIDGE_VER} — Setup              ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# ── 1. System update ───────────────────────────────────────────────────────
echo "[1/7] به‌روزرسانی سیستم..."
apt-get update -qq
apt-get upgrade -y -qq 2>/dev/null | tail -1

# ── 2. Base packages ───────────────────────────────────────────────────────
echo "[2/7] نصب پکیج‌های پایه..."
apt-get install -y -qq \
    gpsd gpsd-clients python3 \
    rtl-sdr librtlsdr-dev usbutils \
    wget curl git

# ── 3. RTL-SDR blacklist kernel driver ─────────────────────────────────────
echo "[3/7] پیکربندی RTL-SDR..."
cat > /etc/modprobe.d/blacklist-rtl.conf <<'EOF'
# Blacklist DVB drivers so RTL-SDR can be used for ADS-B
blacklist dvb_usb_rtl28xxu
blacklist rtl2832
blacklist rtl2830
EOF
# Load udev rules for non-root access
cat > /etc/udev/rules.d/20-rtlsdr.rules <<'EOF'
SUBSYSTEM=="usb", ATTRS{idVendor}=="0bda", ATTRS{idProduct}=="2832", GROUP="plugdev", MODE="0666"
SUBSYSTEM=="usb", ATTRS{idVendor}=="0bda", ATTRS{idProduct}=="2838", GROUP="plugdev", MODE="0666"
EOF
usermod -aG plugdev pi 2>/dev/null || true

# ── 4. dump1090-fa ─────────────────────────────────────────────────────────
echo "[4/7] نصب dump1090-fa (ADS-B decoder)..."
if ! command -v dump1090-fa &>/dev/null && [ ! -f /usr/local/bin/dump1090 ]; then
    # Try FlightAware package first
    ARCH=$(dpkg --print-architecture)
    DEB_URL=""
    case "$ARCH" in
        armhf)  DEB_URL="https://github.com/flightaware/dump1090/releases/download/v9.0/dump1090-fa_9.0_armhf.deb" ;;
        arm64)  DEB_URL="https://github.com/flightaware/dump1090/releases/download/v9.0/dump1090-fa_9.0_arm64.deb" ;;
        amd64)  DEB_URL="https://github.com/flightaware/dump1090/releases/download/v9.0/dump1090-fa_9.0_amd64.deb" ;;
    esac

    if [ -n "$DEB_URL" ]; then
        echo "   دانلود از GitHub..."
        wget -q -O /tmp/dump1090-fa.deb "$DEB_URL" && \
            dpkg -i /tmp/dump1090-fa.deb && rm /tmp/dump1090-fa.deb || \
            echo "   نصب deb ناموفق — build از source..."
    fi

    # Fallback: build from source
    if ! command -v dump1090-fa &>/dev/null; then
        echo "   Build از source (چند دقیقه طول می‌کشد)..."
        apt-get install -y -qq build-essential libusb-1.0-0-dev
        git clone --quiet --depth=1 https://github.com/flightaware/dump1090.git /tmp/dump1090-src
        cd /tmp/dump1090-src
        make -j4 -s
        cp dump1090 /usr/local/bin/dump1090
        mkdir -p /etc/dump1090-fa
        cd /
        rm -rf /tmp/dump1090-src
        echo "   dump1090 نصب شد: /usr/local/bin/dump1090"
    fi

    # Create systemd service for dump1090
    DUMP1090_BIN=$(command -v dump1090-fa 2>/dev/null || echo /usr/local/bin/dump1090)
    cat > /etc/systemd/system/dump1090.service <<EOF
[Unit]
Description=dump1090 ADS-B Receiver
After=network.target

[Service]
Type=simple
ExecStart=${DUMP1090_BIN} --net --net-http-port 8080 --quiet
Restart=on-failure
RestartSec=5
User=root

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable dump1090
    systemctl start dump1090 || echo "   ⚠ dump1090 هنوز شروع نشده (RTL-SDR لازم است)"
else
    echo "   dump1090 از قبل نصب است."
fi

# ── 5. GPS / gpsd ──────────────────────────────────────────────────────────
echo "[5/7] پیکربندی GPS..."
GPS_DEV=""
for dev in /dev/ttyUSB0 /dev/ttyUSB1 /dev/ttyACM0 /dev/ttyAMA0 /dev/ttyS0; do
    if [ -e "$dev" ]; then
        GPS_DEV="$dev"
        echo "   دستگاه GPS پیدا شد: $dev"
        break
    fi
done

if [ -n "$GPS_DEV" ]; then
    cat > /etc/default/gpsd <<EOF
DEVICES="$GPS_DEV"
GPSD_OPTIONS="-n -G"
START_DAEMON="true"
USBAUTO="true"
EOF
    systemctl enable gpsd
    systemctl restart gpsd
    echo "   gpsd شروع شد."
else
    echo "   ⚠ دستگاه GPS پیدا نشد. بعد از اتصال GPS اجرا کنید:"
    echo "      sudo dpkg-reconfigure gpsd"
fi

# ── 6. Install bridge script ───────────────────────────────────────────────
echo "[6/7] نصب SignageCMS Bridge..."
mkdir -p "$BRIDGE_DIR" "$CONFIG_DIR"

# Copy the bridge script
if [ -f "${SCRIPT_DIR}/inflight_bridge.py" ]; then
    cp "${SCRIPT_DIR}/inflight_bridge.py" "${BRIDGE_DIR}/inflight_bridge.py"
else
    echo "   ⚠ inflight_bridge.py پیدا نشد در ${SCRIPT_DIR}"
    echo "   فایل را از پنل مدیریت دانلود و در ${BRIDGE_DIR}/ قرار دهید"
fi
chmod +x "${BRIDGE_DIR}/inflight_bridge.py" 2>/dev/null || true

# Default config
if [ ! -f "${CONFIG_DIR}/config.json" ]; then
    cat > "${CONFIG_DIR}/config.json" <<'EOCFG'
{
  "port": 5055,
  "gpsd_host": "localhost",
  "gpsd_port": 2947,
  "dump1090_url": "http://localhost:8080",
  "dump1090_enabled": true,
  "dump1090_interval": 5,
  "signagecms_url": "",
  "flight_id": null,
  "api_token": "",
  "push_enabled": false,
  "push_interval": 10
}
EOCFG
    echo "   Config پیش‌فرض ایجاد شد: ${CONFIG_DIR}/config.json"
fi

# Systemd service
cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=SignageCMS In-Flight Bridge
Documentation=https://github.com/your-repo/signage-cms
After=network.target gpsd.service
Wants=gpsd.service

[Service]
Type=simple
ExecStart=/usr/bin/python3 ${BRIDGE_DIR}/inflight_bridge.py
Restart=on-failure
RestartSec=5
User=root
Environment=BRIDGE_CONFIG=${CONFIG_DIR}/config.json
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable inflight-bridge
systemctl start inflight-bridge
sleep 2

# ── 7. Final check ─────────────────────────────────────────────────────────
echo "[7/7] بررسی نهایی..."

IP=$(hostname -I 2>/dev/null | awk '{print $1}')
BRIDGE_OK=false
if curl -s --max-time 3 "http://localhost:5055/api/health" | grep -q '"ok":true'; then
    BRIDGE_OK=true
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                   نتیجه نصب                              ║"
echo "╠══════════════════════════════════════════════════════════╣"

if $BRIDGE_OK; then
    echo "║  ✅ Bridge: فعال روی پورت 5055                           ║"
else
    echo "║  ⚠  Bridge: هنوز آماده نیست (بررسی: systemctl status inflight-bridge)"
fi

if systemctl is-active --quiet gpsd; then
    echo "║  ✅ gpsd: فعال                                            ║"
else
    echo "║  ⚠  gpsd: غیرفعال (GPS وصل نیست؟)                       ║"
fi

if systemctl is-active --quiet dump1090 2>/dev/null; then
    echo "║  ✅ dump1090: فعال                                        ║"
else
    echo "║  ⚠  dump1090: غیرفعال (RTL-SDR لازم است)                ║"
fi

echo "╠══════════════════════════════════════════════════════════╣"
echo "║  آدرس این Raspberry Pi:                                   ║"
echo "║    http://${IP}:5055/api/status"
echo "║                                                           ║"
echo "║  در پنل SignageCMS:                                       ║"
echo "║    In-Flight → پرواز → Raspberry Pi → IP: ${IP}"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "دستورات مفید:"
echo "  وضعیت: sudo systemctl status inflight-bridge"
echo "  لاگ:   sudo journalctl -u inflight-bridge -f"
echo "  GPS:   cgps -s"
echo "  ADSB:  curl http://localhost:8080/data/aircraft.json | head"
