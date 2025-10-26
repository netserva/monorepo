#!/bin/bash
# Install obs-ctl dependencies
# Run this after cloning the repo or on a new system

set -e

cd "$(dirname "$0")"

echo "Installing obs-ctl Node.js dependencies..."

# Check if node and npm are installed
if ! command -v node &> /dev/null; then
    echo "Error: Node.js is not installed"
    echo "Install with: sudo pacman -S nodejs npm"
    exit 1
fi

# Install dependencies
npm install

echo ""
echo "✅ obs-ctl dependencies installed"
echo ""
echo "Next steps:"
echo "1. Set OBS_PASSWORD environment variable:"
echo "   export OBS_PASSWORD=\"your-password\""
echo "2. Test connection:"
echo "   obs-ctl version"
echo ""
