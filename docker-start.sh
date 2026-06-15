#!/bin/bash
# ═══════════════════════════════════════════════════════════
# SignageCMS Docker Quick Start
# ═══════════════════════════════════════════════════════════
set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'

echo -e "${BLUE}"
echo "  ████████╗ ██████╗ ██╗  ██╗"
echo "  ╚══██╔══╝██╔════╝ ╚██╗██╔╝"
echo "     ██║   ██║  ███╗ ╚███╔╝ "
echo "     ██║   ██║   ██║ ██╔██╗ "
echo "     ██║   ╚██████╔╝██╔╝ ██╗"
echo "     ╚═╝    ╚═════╝ ╚═╝  ╚═╝"
echo -e "${NC}"
echo -e "${YELLOW}SignageCMS v1.1.0 — Docker Quick Start${NC}"
echo ""

# ─── بررسی Docker ───
if ! command -v docker &>/dev/null; then
    echo -e "${RED}❌ Docker نصب نیست. از https://www.docker.com/products/docker-desktop دانلود کن${NC}"
    exit 1
fi
if ! command -v docker compose &>/dev/null && ! command -v docker-compose &>/dev/null; then
    echo -e "${RED}❌ Docker Compose نصب نیست${NC}"
    exit 1
fi

# ─── Docker Compose command ───
DC="docker compose"
command -v "docker compose" &>/dev/null || DC="docker-compose"

# ─── ساخت .env اگه نیست ───
if [ ! -f .env ]; then
    echo -e "${GREEN}[1/4] ساخت .env...${NC}"
    cp .env.example .env 2>/dev/null || echo "❌ .env.example نیست"
    APP_KEY="base64:$(openssl rand -base64 32 2>/dev/null || head -c 32 /dev/urandom | base64)"
    sed -i.bak "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env && rm -f .env.bak
    echo "  ✅ .env ساخته شد"
else
    echo -e "${GREEN}[1/4] .env از قبل وجود دارد${NC}"
fi

# ─── ساخت پوشه‌ها ───
echo -e "${GREEN}[2/4] ساخت پوشه‌های ضروری...${NC}"
mkdir -p storage/{sessions,logs,cache,temp}
mkdir -p public/uploads/{media,thumbnails}
chmod -R 755 storage public/uploads
echo "  ✅ پوشه‌ها آماده"

# ─── Build & Run ───
echo -e "${GREEN}[3/4] اجرای Docker (اولین بار کمی طول می‌کشه)...${NC}"
$DC up -d --build

# ─── انتظار برای MySQL ───
echo -e "${GREEN}[4/4] انتظار برای آماده شدن MySQL...${NC}"
MAX=60; COUNT=0
while ! $DC exec mysql mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD:-RootPass123!}" --silent &>/dev/null; do
    COUNT=$((COUNT+1))
    if [ $COUNT -ge $MAX ]; then
        echo -e "${RED}❌ MySQL آماده نشد. لاگ رو چک کن: $DC logs mysql${NC}"
        exit 1
    fi
    printf "."
    sleep 2
done

echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ SignageCMS با موفقیت اجرا شد!${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""
echo -e "🌐 پنل مدیریت:  ${YELLOW}http://localhost${NC}"
echo -e "🗄 phpMyAdmin:  ${YELLOW}http://localhost:8081${NC}"
echo -e "📡 WebSocket:   ${YELLOW}ws://localhost:8080${NC}"
echo ""
echo -e "📧 ایمیل:  ${YELLOW}admin@signagecms.com${NC}"
echo -e "🔑 رمز:    ${YELLOW}Admin@123456${NC}"
echo ""
echo -e "دستورات مفید:"
echo -e "  ${YELLOW}$DC logs -f php${NC}     — لاگ PHP"
echo -e "  ${YELLOW}$DC ps${NC}               — وضعیت سرویس‌ها"
echo -e "  ${YELLOW}$DC down${NC}             — خاموش کردن"
echo -e "  ${YELLOW}$DC restart php${NC}      — ریستارت PHP"
echo ""
