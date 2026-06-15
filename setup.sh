#!/usr/bin/env bash
# ============================================================
#  SignageCMS — راه‌اندازی سریع Linux/Mac
#  v1.0
# ============================================================
set -e
BOLD="\e[1m"; GREEN="\e[32m"; YELLOW="\e[33m"; RED="\e[31m"; CYAN="\e[36m"; NC="\e[0m"

ok()   { echo -e "  ${GREEN}[OK]${NC}   $1"; }
info() { echo -e "  ${CYAN}[INFO]${NC} $1"; }
warn() { echo -e "  ${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "  ${RED}[ERROR]${NC} $1"; exit 1; }
ask()  { echo -en "  ${BOLD}$1${NC}: "; }

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         SignageCMS — راه‌اندازی سریع             ║${NC}"
echo -e "${BOLD}║              Linux/Mac Setup v1.0               ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# ── بررسی پیش‌نیازها ─────────────────────────────────────────
command -v docker &>/dev/null || err "Docker نصب نشده. https://docs.docker.com/get-docker/"
ok "Docker در دسترس است"
docker info &>/dev/null          || err "Docker daemon اجرا نمی‌کند. sudo systemctl start docker"
(docker compose version &>/dev/null || docker-compose version &>/dev/null) || err "Docker Compose پیدا نشد"
ok "Docker Compose در دسترس است"

# تعیین دستور compose
COMPOSE="docker compose"
docker compose version &>/dev/null || COMPOSE="docker-compose"

echo ""

# ── ساخت .env ────────────────────────────────────────────────
if [ ! -f ".env" ]; then
    [ -f ".env.example" ] || err "فایل .env.example پیدا نشد"
    cp .env.example .env
    ok "فایل .env از .env.example ساخته شد"
else
    ok "فایل .env موجود است"
fi

# ── تنظیمات ──────────────────────────────────────────────────
echo ""
echo "  ─────────────────────────────────────────────"
echo "  تنظیمات (Enter = مقدار پیش‌فرض)"
echo "  ─────────────────────────────────────────────"
echo ""

ask "پورت وب [80]"; read APP_PORT;   APP_PORT=${APP_PORT:-80}
ask "پورت WebSocket [8080]"; read WS_PORT; WS_PORT=${WS_PORT:-8080}
ask "آدرس سایت [http://localhost]"; read APP_URL; APP_URL=${APP_URL:-http://localhost}
ask "رمز دیتابیس [StrongPassword123!]"; read -s DB_PASS; echo; DB_PASS=${DB_PASS:-StrongPassword123!}
ask "رمز root MySQL [RootPass123!]"; read -s ROOT_PASS; echo; ROOT_PASS=${ROOT_PASS:-RootPass123!}

# ── تولید کلیدهای امنیتی ─────────────────────────────────────
info "تولید کلیدهای امنیتی ..."
APP_KEY="base64:$(openssl rand -base64 32)"
JWT_SECRET="$(openssl rand -hex 32)"

# ── بروزرسانی .env ───────────────────────────────────────────
update_env() {
    local key="$1" val="$2" file=".env"
    if grep -q "^${key}=" "$file" 2>/dev/null; then
        # macOS vs GNU sed
        sed -i.bak "s|^${key}=.*|${key}=${val}|" "$file" && rm -f "${file}.bak"
    else
        echo "${key}=${val}" >> "$file"
    fi
}

update_env "APP_URL"              "$APP_URL"
update_env "APP_PORT"             "$APP_PORT"
update_env "WS_PORT"              "$WS_PORT"
update_env "APP_KEY"              "$APP_KEY"
update_env "JWT_SECRET"           "$JWT_SECRET"
update_env "DB_PASSWORD"          "$DB_PASS"
update_env "MYSQL_ROOT_PASSWORD"  "$ROOT_PASS"

ok "فایل .env بروزرسانی شد"

# ── ساخت پوشه‌های ضروری ──────────────────────────────────────
info "ساخت پوشه‌های ضروری ..."
mkdir -p storage/{logs,cache,sessions,temp,cache/fids}
mkdir -p public/uploads/{media,thumbnails,apk,vod,vod/thumbs}
chmod -R 755 storage/ public/uploads/ 2>/dev/null || true
ok "پوشه‌ها آماده‌اند"

# ── Docker build ─────────────────────────────────────────────
echo ""
echo "  ─────────────────────────────────────────────"
info "ساخت و راه‌اندازی Docker containers ..."
echo "  (ممکنه چند دقیقه طول بکشه)"
echo "  ─────────────────────────────────────────────"
echo ""

$COMPOSE down --remove-orphans 2>/dev/null || true
$COMPOSE build --no-cache
$COMPOSE up -d
ok "Containers در حال اجرا هستند"

# ── انتظار برای MySQL ─────────────────────────────────────────
echo ""
info "انتظار برای آماده شدن MySQL ..."
TRIES=0
until docker exec signage_mysql mysqladmin ping -h localhost -u root -p"${ROOT_PASS}" --silent &>/dev/null; do
    TRIES=$((TRIES+1))
    [ $TRIES -gt 30 ] && { warn "MySQL آماده نشد — installer را بعداً اجرا کنید"; break; }
    info "تلاش $TRIES/30 ..."
    sleep 5
done
[ $TRIES -le 30 ] && ok "MySQL آماده است"

# ── اجرای installer ──────────────────────────────────────────
echo ""
echo "  ─────────────────────────────────────────────"
info "نصب دیتابیس و ساخت جداول ..."
echo "  ─────────────────────────────────────────────"
echo ""

docker exec signage_php php /var/www/html/public/install.php \
    && ok "نصب دیتابیس کامل شد" \
    || warn "installer مشکل داشت — آدرس http://localhost:${APP_PORT}/install.php را باز کنید"

# ── نتیجه ────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║            نصب کامل شد!                         ║${NC}"
echo -e "${GREEN}${BOLD}╠══════════════════════════════════════════════════╣${NC}"
echo -e "║  داشبورد:    ${CYAN}http://localhost:${APP_PORT}${NC}"
echo -e "║  phpMyAdmin: ${CYAN}http://localhost:8081${NC}"
echo -e "║  WebSocket:  ${CYAN}ws://localhost:${WS_PORT}${NC}"
echo -e "${GREEN}${BOLD}╠══════════════════════════════════════════════════╣${NC}"
echo -e "║  ایمیل:  ${YELLOW}admin@signagecms.com${NC}"
echo -e "║  رمز:    ${YELLOW}Admin@123456${NC}"
echo -e "${GREEN}${BOLD}╠══════════════════════════════════════════════════╣${NC}"
echo -e "║  ${RED}بعد از ورود رمز را تغییر دهید!${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# ── دستورات مدیریت ────────────────────────────────────────────
echo "  دستورات مفید:"
echo "    شروع:    docker compose up -d"
echo "    توقف:    docker compose down"
echo "    لاگ:     docker compose logs -f"
echo "    ری‌استارت: docker compose restart"
echo ""
