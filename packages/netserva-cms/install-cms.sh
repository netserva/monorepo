#!/bin/bash
#
# NetServa CMS Automated Installation Script
#
# Usage:
#   ./install-cms.sh                    # Interactive mode
#   ./install-cms.sh --non-interactive  # Automated mode
#

set -e

INTERACTIVE=true
if [[ "$1" == "--non-interactive" ]]; then
    INTERACTIVE=false
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║         NetServa CMS Installation Script                ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Step 1: Create Laravel project
if [ ! -f "composer.json" ]; then
    echo "→ Creating fresh Laravel 12 project..."
    composer create-project laravel/laravel . --no-interaction
    echo "✓ Laravel project created"
else
    echo "✓ Laravel project already exists"
fi

# Step 2: Require CMS package
echo ""
echo "→ Installing NetServa CMS package..."
composer require netserva/cms --no-interaction

# Step 3: Run install command
echo ""
if [ "$INTERACTIVE" = true ]; then
    echo "→ Running CMS installation (interactive)..."
    php artisan netserva-cms:install --seed
else
    echo "→ Running CMS installation (automated)..."
    php artisan netserva-cms:install --seed --force
fi

# Step 4: Install npm dependencies (if package.json exists)
if [ -f "package.json" ]; then
    echo ""
    echo "→ Installing npm dependencies..."
    npm install

    echo ""
    echo "→ Building frontend assets..."
    npm run build
    echo "✓ Frontend assets built"
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║             Installation Complete!                       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "🎉 NetServa CMS is ready!"
echo ""
echo "Start the development server:"
echo "  php artisan serve"
echo ""
echo "Then visit:"
echo "  • Frontend: http://localhost:8000"
echo "  • Admin:    http://localhost:8000/admin"
echo ""
if [ "$INTERACTIVE" = false ]; then
    echo "Default admin credentials:"
    echo "  • Email:    admin@netserva.com"
    echo "  • Password: password"
    echo ""
fi
