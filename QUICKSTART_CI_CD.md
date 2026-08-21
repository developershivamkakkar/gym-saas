# CI/CD Pipeline Quick Start

## 5-Minute Setup

### 1. Install Required Composer Packages
```bash
composer require --dev phpstan/phpstan laravel/pint phpunit/phpunit
```

### 2. Create `.env.example` (if not exists)
```bash
cp .env .env.example
# Edit .env.example and remove sensitive data
```

### 3. Setup GitHub Secrets
Visit: **GitHub Repo → Settings → Secrets and variables → Actions**

Add these 4 secrets:
| Secret | Example Value |
|--------|---|
| `DEPLOY_KEY` | Your SSH private key (generate with `ssh-keygen -t ed25519`) |
| `DEPLOY_HOST` | `123.45.67.89` (your server IP) |
| `DEPLOY_USER` | `deploy` (SSH username) |
| `DEPLOY_PATH` | `/var/www/html/fitcore` (path on server) |

### 4. Setup SSH on Your Server
```bash
# On your local machine:
ssh-keygen -t ed25519 -f deploy_key -N ""

# Add public key to server:
cat deploy_key.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# Copy private key to GitHub Secrets as DEPLOY_KEY
```

### 5. Push to GitHub
```bash
git add .github/workflows/ci-cd.yml pint.json phpstan.neon CI_CD_SETUP_GUIDE.md
git commit -m "Add CI/CD pipeline"
git push
```

## ✅ Verify It Works

1. Go to **GitHub Repo → Actions**
2. Watch the workflow run
3. Check if tests pass ✓

## 📋 What Happens Automatically

| Branch | Action |
|--------|--------|
| **develop** | Runs tests & code checks only |
| **main** | Runs tests → builds → **deploys to production** |

## 🔧 Local Development

Run quality checks locally before pushing:

```bash
# Check code style
./vendor/bin/pint app

# Run static analysis
./vendor/bin/phpstan analyse

# Run tests
php artisan test
```

## 📚 Full Documentation

See [CI_CD_SETUP_GUIDE.md](CI_CD_SETUP_GUIDE.md) for detailed setup and troubleshooting.

## 🆘 Need Help?

Common issues:
- **Deploy fails** → Check SSH key in GitHub Secrets is correct
- **Tests fail** → Run `php artisan test` locally to debug
- **Code style fails** → Run `./vendor/bin/pint app` to auto-fix

---

**You're all set! 🎉 Your CI/CD pipeline is ready to use.**
