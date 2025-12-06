# Railway Deployment Guide

## Prerequisites
- Railway account (https://railway.app)
- GitHub repository connected to Railway
- MySQL database provisioned on Railway

## Environment Variables

Set these in Railway Dashboard → Variables:

### Application
```
APP_NAME=Crater
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.up.railway.app
APP_KEY=<generate with: php artisan key:generate --show>
```

### Database (from Railway MySQL service)
```
DB_CONNECTION=mysql
DB_HOST=${{MYSQLHOST}}
DB_PORT=${{MYSQLPORT}}
DB_DATABASE=${{MYSQLDATABASE}}
DB_USERNAME=${{MYSQLUSER}}
DB_PASSWORD=${{MYSQLPASSWORD}}
```

### Session & Security
```
SESSION_DRIVER=cookie
SESSION_LIFETIME=1440
SANCTUM_STATEFUL_DOMAINS=your-app.up.railway.app
SESSION_DOMAIN=your-app.up.railway.app
TRUSTED_PROXIES=*
```

### Mail (Optional - configure later)
```
MAIL_DRIVER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
```

## Deployment Steps

### 1. Prepare Repository
```bash
# Ensure all files are committed
git add .
git commit -m "Configure Railway deployment"
git push origin main
```

### 2. Create Railway Project
1. Go to https://railway.app
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Choose your repository

### 3. Add MySQL Database
1. In your project, click "+ New"
2. Select "Database" → "MySQL"
3. Railway will auto-provision and link it

### 4. Configure Environment Variables
1. Go to your service → "Variables"
2. Add all variables listed above
3. Use Railway's MySQL variables (they auto-populate)

### 5. Deploy
1. Railway will auto-deploy on push
2. Check "Deployments" tab for progress
3. View logs for any errors

### 6. Verify Deployment
1. Click "Settings" → "Domains" → "Generate Domain"
2. Visit your app URL
3. Complete installation at `/installation`

## Troubleshooting

### Database Connection Failed
- Check MySQL service is running
- Verify environment variables are correct
- Check logs: `railway logs`

### 502 Bad Gateway
- Check if app is listening on correct PORT
- Verify entrypoint script is executable
- Check logs for PHP errors

### Migrations Not Running
- Manually run: `railway run php artisan migrate --force`
- Check database credentials

## Post-Deployment

### Run Commands
```bash
# SSH into container
railway shell

# Run migrations
railway run php artisan migrate --force

# Seed database
railway run php artisan db:seed --force

# Clear cache
railway run php artisan cache:clear
```

### Monitor
- View logs: Railway Dashboard → Logs
- Check metrics: Railway Dashboard → Metrics
- Set up alerts: Railway Dashboard → Settings → Notifications

## Files Created for Railway

- `Dockerfile.railway` - Production Docker image
- `railway-entrypoint.sh` - Startup script
- `railway.json` - Railway configuration
- `RAILWAY_DEPLOYMENT.md` - This guide

## Important Notes

> [!WARNING]
> **File Uploads**: Railway has ephemeral storage. Configure cloud storage (S3, Cloudinary) for production file uploads.

> [!IMPORTANT]
> **Database Backups**: Set up regular backups in Railway Dashboard → Database → Backups

> [!TIP]
> **Custom Domain**: Add your domain in Railway Dashboard → Settings → Domains
