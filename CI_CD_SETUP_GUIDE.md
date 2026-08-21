# Laravel CI/CD Pipeline Setup Guide

## Overview
This CI/CD pipeline automates testing, code quality checks, and deployment for your GYM_SAAS Laravel application using GitHub Actions.

## Pipeline Stages

### 1. **Lint & Test** (Runs on every push and PR)
- **PHP Linting**: Laravel Pint checks code style
- **Static Analysis**: PHPStan identifies type errors
- **Database Migrations**: Tests migrations work correctly
- **Unit Tests**: PHPUnit runs your test suite
- **Coverage Reports**: Uploaded to Codecov

### 2. **Build** (Runs after lint & test passes)
- Installs production dependencies
- Optimizes Laravel (config, routes, views)
- Creates deployable artifact

### 3. **Deploy** (Only on main branch, after build succeeds)
- Deploys to your cloud server via SSH
- Runs migrations on production
- Clears caches
- Restarts services

## Setup Instructions

### Step 1: Configure GitHub Secrets
Go to your GitHub repository → Settings → Secrets and variables → Actions

Add these secrets:
```
DEPLOY_KEY          → Your private SSH key (use ssh-keygen -t ed25519)
DEPLOY_HOST         → Your server IP/hostname (e.g., 192.168.1.100)
DEPLOY_USER         → SSH username (e.g., deploy)
DEPLOY_PATH         → Path on server (e.g., /var/www/html/fitcore)
```

### Step 2: Prepare Your Project

#### Create `.env.example` (if not exists)
Copy your `.env` file and rename to `.env.example`, removing sensitive values:
```bash
cp .env .env.example
# Edit .env.example to remove passwords, API keys, etc.
```

#### Install Quality Packages (Local)
```bash
composer require --dev phpstan/phpstan laravel/pint phpunit/phpunit
```

#### Create `phpstan.neon` config:
```neon
includes:
    - phpstan-baseline.neon

parameters:
    level: 5
    paths:
        - app
        - routes
    excludePaths:
        - tests
```

#### Create `pint.json` config:
```json
{
    "preset": "laravel",
    "rules": {
        "simplified_null_return": true
    }
}
```

### Step 3: Setup SSH Key for Deployment

On your local machine:
```bash
ssh-keygen -t ed25519 -f deploy_key -N ""
```

- Add public key to your server's `~/.ssh/authorized_keys`
- Add private key to GitHub Secrets as `DEPLOY_KEY`

### Step 4: Prepare Your Server

SSH into your production server and:
```bash
# Create deployment directory
mkdir -p /var/www/html/fitcore
cd /var/www/html/fitcore

# Setup PHP-FPM (if not already done)
sudo apt update
sudo apt install -y php-fpm php-mysql php-redis

# Configure nginx or Apache for your domain
```

## GitHub Actions Workflow Events

The pipeline runs on:
- **Push** to `main` or `develop` branches
- **Pull Requests** to `main` or `develop` branches

## Viewing Pipeline Results

1. Go to your repository
2. Click **Actions** tab
3. Select a workflow run to see details

## Common Issues & Solutions

### MySQL Connection Fails
- Ensure `DB_HOST: 127.0.0.1` in test environment
- Check `DB_DATABASE: fitcore_test` matches in `.env`

### SSH Deployment Fails
- Verify `DEPLOY_KEY` is in PEM format
- Check key permissions: `chmod 600 ~/.ssh/deploy_key`
- Test manually: `ssh -i deploy_key user@host`

### Code Style Check Fails
Run locally to fix:
```bash
./vendor/bin/pint app
```

### Tests Fail
Debug with:
```bash
php artisan test --debug
```

## Extending the Pipeline

### Add Slack Notifications
Add to workflow:
```yaml
- name: Slack Notification
  uses: slackapi/slack-github-action@v1
  if: failure()
  with:
    webhook-url: ${{ secrets.SLACK_WEBHOOK }}
```

### Add Email Notifications
Use GitHub's native notifications in Settings → Notifications

### Run Tests on Schedule (Nightly)
Add to `on:` section:
```yaml
schedule:
  - cron: '0 2 * * *'  # 2 AM daily
```

## Best Practices

1. **Keep `.env.example` updated** with new variables
2. **Write tests** for critical features
3. **Run locally** before pushing:
   ```bash
   ./vendor/bin/pint --test
   ./vendor/bin/phpstan analyse
   php artisan test
   ```
4. **Review deployment logs** after each release
5. **Version your database migrations** properly
6. **Use feature branches** with PR reviews

## Security Considerations

- Never commit sensitive keys or `.env` to git
- Rotate SSH keys periodically
- Use GitHub environment protection rules for main branch
- Implement approval requirements for production deployments
- Monitor deployment logs for errors

## Next Steps

1. Test the workflow on your `develop` branch first
2. Fix any issues in a test environment
3. Merge to `main` for production deployment
4. Monitor GitHub Actions logs for any issues
