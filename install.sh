#!/bin/bash
# ════════════════════════════════════════════════
# SignageCMS Installation Script
# ════════════════════════════════════════════════
set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}"
echo "  ███████╗██╗ ██████╗ ███╗   ██╗ █████╗  ██████╗ ███████╗"
echo "  ██╔════╝██║██╔════╝ ████╗  ██║██╔══██╗██╔════╝ ██╔════╝"
echo "  ███████╗██║██║  ███╗██╔██╗ ██║███████║██║  ███╗█████╗  "
echo "  ╚════██║██║██║   ██║██║╚██╗██║██╔══██║██║   ██║██╔══╝  "
echo "  ███████║██║╚██████╔╝██║ ╚████║██║  ██║╚██████╔╝███████╗"
echo "  ╚══════╝╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝"
echo -e "${NC}"
echo -e "${YELLOW}Digital Signage Management System — v1.0.0${NC}"
echo ""

# ─── Check requirements ───
echo -e "${GREEN}[1/7] Checking requirements...${NC}"
check_cmd() {
  command -v "$1" &>/dev/null || { echo -e "${RED}❌ $1 is required but not installed${NC}"; exit 1; }
}
check_cmd php; check_cmd composer; check_cmd mysql; check_cmd zip
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "  ✅ PHP $PHP_VER"
echo "  ✅ Composer $(composer --version --no-ansi | head -1 | awk '{print $3}')"

# ─── Configure .env ───
echo -e "${GREEN}[2/7] Setting up .env...${NC}"
if [ ! -f .env ]; then
  cp .env.example .env
  APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
  JWT_KEY=$(php -r "echo bin2hex(random_bytes(32));")
  sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env
  sed -i "s|JWT_SECRET=.*|JWT_SECRET=$JWT_KEY|" .env
  echo "  ✅ .env created with fresh keys"
else
  echo "  ✅ .env already exists"
fi

# ─── Database setup ───
echo -e "${GREEN}[3/7] Database setup...${NC}"
read -p "  DB Host [localhost]: " DB_HOST; DB_HOST=${DB_HOST:-localhost}
read -p "  DB Name [signage_cms]: " DB_NAME; DB_NAME=${DB_NAME:-signage_cms}
read -p "  DB User [root]: " DB_USER; DB_USER=${DB_USER:-root}
read -sp "  DB Password: " DB_PASS; echo ""

sed -i "s|DB_HOST=.*|DB_HOST=$DB_HOST|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/001_complete_schema.sql
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/seeds/seed.sql
echo "  ✅ Database migrated and seeded"

# ─── Composer ───
echo -e "${GREEN}[4/7] Installing PHP dependencies...${NC}"
composer install --no-dev --optimize-autoloader --quiet
echo "  ✅ Composer packages installed"

# ─── Permissions ───
echo -e "${GREEN}[5/7] Setting permissions...${NC}"
chmod -R 755 storage/ public/uploads/
chown -R www-data:www-data storage/ public/uploads/ 2>/dev/null || true
echo "  ✅ Permissions set"

# ─── App URL ───
echo -e "${GREEN}[6/7] Application configuration...${NC}"
read -p "  App URL [http://localhost]: " APP_URL; APP_URL=${APP_URL:-http://localhost}
sed -i "s|APP_URL=.*|APP_URL=$APP_URL|" .env
sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env

# ─── Done ───
echo -e "${GREEN}[7/7] Installation complete! 🎉${NC}"
echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         SignageCMS Ready to Launch!          ║${NC}"
echo -e "${BLUE}╠══════════════════════════════════════════════╣${NC}"
echo -e "${BLUE}║${NC} 🌐 Admin Panel: ${YELLOW}$APP_URL/admin/dashboard${NC}"
echo -e "${BLUE}║${NC} 📧 Email:       ${YELLOW}admin@signagecms.com${NC}"
echo -e "${BLUE}║${NC} 🔑 Password:    ${YELLOW}Admin@123456${NC}"
echo -e "${BLUE}║${NC}                                              ${BLUE}║${NC}"
echo -e "${BLUE}║${NC} 📺 Player URL:  ${YELLOW}$APP_URL/player/{SCREEN_CODE}${NC}"
echo -e "${BLUE}║${NC} 🔌 API Base:    ${YELLOW}$APP_URL/api/v1${NC}"
echo -e "${BLUE}║${NC} 📡 WebSocket:   ${YELLOW}ws://host:8080${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "Start WebSocket server: ${YELLOW}php artisan ws:start${NC}"
echo -e "Add cron monitor:       ${YELLOW}* * * * * php $(pwd)/artisan monitor:screens${NC}"
echo ""
