#!/bin/bash

# GYM_SAAS Deployment Script
# Run this on your production server after GitHub Actions deploys

set -e

echo "🚀 Starting deployment process..."

# Get the latest version
LATEST_VERSION=$(ls -t *.tar.gz | head -1 | sed 's/app-//g' | sed 's/.tar.gz//g')
DEPLOY_PATH="/var/www/html/fitcore"

echo "📦 Deploying version: $LATEST_VERSION"

# Backup current version
if [ -d "$DEPLOY_PATH/app" ]; then
    echo "💾 Backing up current version..."
    tar -czf "$DEPLOY_PATH/backup-$(date +%Y%m%d-%H%M%S).tar.gz" \
        -C "$DEPLOY_PATH" app bootstrap config database routes --exclude=storage
fi

# Extract new version
echo "📂 Extracting application..."
tar -xzf "app-$LATEST_VERSION.tar.gz" -C "$DEPLOY_PATH"

# Run migrations
echo "🗄️  Running database migrations..."
cd "$DEPLOY_PATH"
php artisan migrate --force

# Clear caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Fix permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data "$DEPLOY_PATH"
sudo chmod -R 755 "$DEPLOY_PATH"
sudo chmod -R 775 "$DEPLOY_PATH/storage"

# Restart PHP-FPM
echo "♻️  Restarting PHP-FPM..."
sudo systemctl restart php-fpm

# Restart queue worker (if using)
echo "🔄 Restarting queue workers..."
sudo supervisorctl restart fitcore:* 2>/dev/null || echo "Queue workers not configured"

echo "✅ Deployment completed successfully!"
echo "📊 You can check status with: php artisan tinker"
