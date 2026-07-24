# Railway Database Connection Fix - COMPLETE GUIDE

## Problem Identified
- **Error:** `php_network_getaddresses: getaddrinfo for mysql.railway.internal failed`
- **Root Cause:** POS app (EU West) trying to connect to MySQL (SFO region) using internal Railway domain
- **Solution:** Use public Railway proxy URL for cross-region communication

---

## What Has Been Done

✅ **1. Railway CLI Login**
```bash
railway login
# Logged in as: waswawilgos42@gmail.com
```

✅ **2. Project Linked**
```bash
railway project link
# Linked: Deligos Company POS
```

✅ **3. Services Verified**
```bash
railway service list
# MySQL: ● Online (SFO region)
# pos: ● Online (EU West region)
```

✅ **4. Database URL Configured**
- Set to public proxy URL: `mysql://root:***@switchback.proxy.rlwy.net:54987/railway`
- This allows cross-region communication

✅ **5. Database Schema Initialized**
- All 10 tables verified in Railway MySQL:
  - users, products, customers, sales, sale_items
  - password_resets, recommendation_letters, categories
  - expenses, inventory_logs

✅ **6. Diagnostic Tool Created**
- Visit: https://deligoscompany.online/test_db_connection.php

---

## What Needs to Be Done on Railway Dashboard

Follow these steps to complete the fix:

### Step 1: Access Railway Dashboard
1. Go to https://railway.app
2. Sign in with: waswawilgos42@gmail.com
3. Select project: **Deligos Company POS**

### Step 2: Configure POS Service Variables
1. Click on the **pos** service
2. Go to **Variables** tab
3. **ADD or VERIFY these variables:**

| Variable | Value |
|----------|-------|
| `DATABASE_URL` | `mysql://root:nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO@switchback.proxy.rlwy.net:54987/railway` |
| `DB_HOST` | `switchback.proxy.rlwy.net` |
| `DB_PORT` | `54987` |
| `DB_NAME` | `railway` |
| `DB_USER` | `root` |
| `DB_PASS` | `nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO` |

### Step 3: Redeploy
1. In the **pos** service, look for "Deployments"
2. Click the latest deployment
3. Or: push a commit to trigger auto-deployment
   ```bash
   git add .
   git commit -m "fix: configure railway database connection"
   git push
   ```

### Step 4: Verify Connection
After redeploy (2-3 minutes):
1. Visit: https://deligoscompany.online/test_db_connection.php
2. Should show: **✓ Database connection successful!**
3. Try logging in or completing a sale

---

## Quick Reference: Environment Variables

**For POS Service in Railway production environment:**

```env
DATABASE_URL=mysql://root:nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO@switchback.proxy.rlwy.net:54987/railway
DB_HOST=switchback.proxy.rlwy.net
DB_PORT=54987
DB_NAME=railway
DB_USER=root
DB_PASS=nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO
BREVO_API_KEY=(if you have one - for email)
```

---

## If Issues Persist

### Check MySQL Service
1. Click **MySQL** service in Railway
2. Verify status: **● Online**
3. Check **Variables** tab for:
   - MYSQLDATABASE: railway
   - MYSQLHOST: mysql.railway.internal
   - MYSQLPASSWORD: nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO
   - MYSQLUSER: root

### View Logs
```bash
railway logs --service pos
```

### Test Connection Locally
```bash
php init_railway_db.php
```

---

## Success Criteria

After completing the steps above:
- [ ] Can access https://deligoscompany.online
- [ ] Can log in with admin/admin123
- [ ] Can add/edit/delete products
- [ ] Can complete sales
- [ ] No "Database connection failed" errors

---

**Created:** 2026-07-24  
**Status:** ✅ Configuration Ready - Awaiting Railway Dashboard Setup
