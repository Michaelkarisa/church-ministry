#!/usr/bin/env bash
set -e

# ============================================================
# Church Ministry Platform — Kenya Setup Script
# ============================================================

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info()    { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
heading() { echo -e "\n${YELLOW}=== $1 ===${NC}"; }

# ---------------------------------------------------------------
heading "Prerequisites Check"
# ---------------------------------------------------------------

command -v php  >/dev/null 2>&1  || error "PHP 8.2+ is required."
command -v composer >/dev/null 2>&1 || error "Composer is required."
command -v mysql >/dev/null 2>&1 || warn "MySQL client not found — ensure your DB is running."

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
info "PHP $PHP_VERSION detected"

# ---------------------------------------------------------------
heading "Laravel Project Setup"
# ---------------------------------------------------------------

PROJECT_DIR="${1:-church-ministry-api}"

if [ -d "$PROJECT_DIR" ] && [ "$(ls -A $PROJECT_DIR)" ]; then
    warn "Directory '$PROJECT_DIR' already exists and is not empty."
    read -rp "Overwrite? [y/N] " confirm
    [[ "$confirm" =~ ^[Yy]$ ]] || exit 0
fi

info "Creating Laravel 11 project in './$PROJECT_DIR'..."
composer create-project laravel/laravel "$PROJECT_DIR" "^11.0" --prefer-dist --quiet

info "Copying Church Ministry Platform files..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Copy all custom files
cp -r "$SCRIPT_DIR/app/"*             "$PROJECT_DIR/app/"
cp -r "$SCRIPT_DIR/database/"*        "$PROJECT_DIR/database/"
cp -r "$SCRIPT_DIR/routes/"*          "$PROJECT_DIR/routes/"
cp -r "$SCRIPT_DIR/config/"*          "$PROJECT_DIR/config/"
cp    "$SCRIPT_DIR/bootstrap/app.php" "$PROJECT_DIR/bootstrap/app.php"
cp    "$SCRIPT_DIR/.env.example"      "$PROJECT_DIR/.env.example"

cd "$PROJECT_DIR"

# ---------------------------------------------------------------
heading "Install Dependencies"
# ---------------------------------------------------------------
info "Installing Laravel Sanctum..."
composer require laravel/sanctum --quiet

# ---------------------------------------------------------------
heading "Environment Configuration"
# ---------------------------------------------------------------

if [ ! -f ".env" ]; then
    cp .env.example .env
    info "Created .env from .env.example"
fi

info "Generating application key..."
php artisan key:generate --ansi

# Database configuration
echo ""
warn "Configure your database connection now."
read -rp "  DB_DATABASE [church_ministry]: " DB_NAME
read -rp "  DB_USERNAME [root]: "            DB_USER
read -s -rp "  DB_PASSWORD: "               DB_PASS
echo ""

DB_NAME="${DB_NAME:-church_ministry}"
DB_USER="${DB_USER:-root}"

sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env

info "Database config written to .env"

# ---------------------------------------------------------------
heading "Database Migration & Seeding"
# ---------------------------------------------------------------

# Create DB if it doesn't exist
mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null \
    && info "Database '$DB_NAME' ready." \
    || warn "Could not auto-create database — ensure it exists before migrating."

info "Running migrations..."
php artisan migrate --force

info "Seeding roles, permissions, ministry, and default users..."
php artisan db:seed --force

# ---------------------------------------------------------------
heading "Final Steps"
# ---------------------------------------------------------------

info "Publishing Sanctum configuration..."
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --quiet

info "Clearing all caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   Church Ministry Platform is ready!      ${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "  Start the server:   php artisan serve"
echo "  API base URL:       http://localhost:8000/api"
echo ""
echo "  Default Credentials:"
echo "  ┌─────────────────┬───────────────────────┬──────────────────┐"
echo "  │ Role            │ Email                 │ Password         │"
echo "  ├─────────────────┼───────────────────────┼──────────────────┤"
echo "  │ Ministry Admin  │ admin@ministry.ke     │ Admin@Kenya2024  │"
echo "  │ Zone Admin      │ zone@ministry.ke      │ Zone@Kenya2024   │"
echo "  │ Church Admin    │ church@ministry.ke    │ Church@Kenya2024 │"
echo "  └─────────────────┴───────────────────────┴──────────────────┘"
echo ""
