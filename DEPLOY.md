# Deployment Guide — TaskFlow Enterprise Task Manager
# Railway (Backend + Database) + Vercel (Frontend)

## Architecture

| Layer    | Platform       | Stack                         |
|----------|----------------|-------------------------------|
| Frontend | Vercel         | Vanilla JS (static site)      |
| Backend  | Railway        | PHP 8.2 + Apache (Docker)     |
| Database | Railway        | MySQL 8.0 (managed plugin)    |

Railway hosts both the PHP backend and the MySQL database.
Vercel hosts the static frontend.

---

## Prerequisites

- GitHub account (Railway and Vercel connect via GitHub)
- Railway account → https://railway.app (free $5 credit, no credit card needed)
- Vercel account → https://vercel.com (free)
- Your project pushed to a GitHub repository

---

## STEP 1 — Push your code to GitHub

If not already done:

```bash
git add .
git commit -m "Add Railway + Vercel deployment config"
git push origin main
```

Make sure `.env` is NOT committed (it is in `.gitignore` ✅).

---

## STEP 2 — Create a Railway project

1. Go to https://railway.app and sign in
2. Click **New Project**
3. Select **Deploy from GitHub repo**
4. Authorize Railway to access your GitHub account if prompted
5. Select your repository

Railway will show you the project dashboard.

---

## STEP 3 — Add a MySQL database on Railway

1. Inside your Railway project, click **+ New**
2. Select **Database** → **Add MySQL**
3. Railway spins up a MySQL 8 instance automatically
4. Click on the MySQL service → go to the **Connect** tab
5. Note these values (you'll need them in Step 5):
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`

> Railway also provides these as `${{MySQL.MYSQLHOST}}` reference variables
> that you can use directly — covered in Step 5.

---

## STEP 4 — Run the database schema

You need to run `docs/database-schema.sql` once to create all tables.

**Option A — Railway's built-in query editor (easiest):**
1. Click on your MySQL service in Railway
2. Go to the **Query** tab
3. Paste the contents of `docs/database-schema.sql`
4. Click **Run Query**

**Option B — MySQL Workbench / TablePlus / DBeaver:**
1. Use the connection details from Step 3 to connect
2. Open and run `docs/database-schema.sql`

---

## STEP 5 — Configure the backend service on Railway

1. In your Railway project, click on your **GitHub repo service** (the PHP backend)
2. Go to **Settings** → set **Root Directory** to `backend`
   - Railway will now build using `backend/Dockerfile`
3. Go to the **Variables** tab and add these environment variables:

| Variable     | Value                                                      |
|--------------|------------------------------------------------------------|
| `DB_HOST`    | `${{MySQL.MYSQLHOST}}`  (Railway reference variable)       |
| `DB_PORT`    | `${{MySQL.MYSQLPORT}}`                                     |
| `DB_NAME`    | `${{MySQL.MYSQLDATABASE}}`                                 |
| `DB_USER`    | `${{MySQL.MYSQLUSER}}`                                     |
| `DB_PASS`    | `${{MySQL.MYSQLPASSWORD}}`                                 |
| `DB_CHARSET` | `utf8mb4`                                                  |
| `JWT_SECRET` | Generate a random 64-char string (see tip below)           |
| `JWT_EXPIRY` | `3600`                                                     |
| `APP_ENV`    | `production`                                               |
| `APP_DEBUG`  | `false`                                                    |
| `APP_URL`    | Your Vercel URL — fill in after Step 8 (use `*` for now)  |

**Tip — generate a JWT_SECRET:**
Run this in your terminal and paste the output:
```bash
# PowerShell
-join ((65..90) + (97..122) + (48..57) | Get-Random -Count 64 | ForEach-Object {[char]$_})
```

4. Click **Deploy** — Railway builds the Docker image and starts the container

5. Once deployed, go to **Settings** → **Networking** → click **Generate Domain**
6. Copy your backend URL — it will look like:
   `https://taskflow-backend-production.up.railway.app`

---

## STEP 6 — Update the frontend API URL

Open `frontend/public/js/app.js` and find line ~60:

```js
const API_BASE = 'REPLACE_WITH_RAILWAY_BACKEND_URL/api';
```

Replace it with your actual Railway backend URL:

```js
const API_BASE = 'https://taskflow-backend-production.up.railway.app/api';
```

Save the file.

---

## STEP 7 — Deploy the frontend to Vercel

1. Go to https://vercel.com → **Add New Project**
2. Import your GitHub repository
3. Configure the project:
   - **Root Directory**: `frontend`
   - **Framework Preset**: Other
   - **Build Command**: *(leave empty)*
   - **Output Directory**: *(leave empty — Vercel uses vercel.json)*
4. Click **Deploy**
5. Once deployed, copy your Vercel URL, e.g.:
   `https://taskflow.vercel.app`

---

## STEP 8 — Set CORS origin on Railway

1. Go back to Railway → your backend service → **Variables**
2. Update `APP_URL` to your Vercel URL:
   ```
   APP_URL = https://taskflow.vercel.app
   ```
3. Railway auto-redeploys on variable changes

---

## STEP 9 — Verify everything works

1. Open your Vercel URL — TaskFlow login page should load
2. Register a new account
3. Create a project, add some tasks
4. Test the health endpoint directly:
   `https://taskflow-backend-production.up.railway.app/api/health`
   
   Should return: `{"success":true,"message":"OK","data":{"status":"ok","time":"..."}}`

---

## Local Development (unchanged)

```bash
# Ensure your local .env has correct DB credentials, then:
php -S localhost:8080 backend/server.php
```

The frontend at `http://localhost:8080` will use the local API automatically.

---

## File Structure Added for Deployment

```
backend/
  Dockerfile              ← PHP 8.2 + Apache + mod_rewrite
  docker-entrypoint.sh    ← Patches Apache to use Railway's $PORT
  railway.toml            ← Railway build/deploy config
frontend/
  vercel.json             ← Vercel routing config
  public/
    css/styles.css        ← Production copy (served by Vercel)
    js/app.js             ← Production copy with Railway API URL
```

---

## Troubleshooting

**CORS errors in browser:**
- Check `APP_URL` on Railway exactly matches your Vercel URL (no trailing slash)
- Redeploy backend after changing `APP_URL`

**502 / connection refused on backend:**
- Check Railway logs (click service → **Deployments** → **View Logs**)
- Verify all DB_ variables are set correctly
- Make sure the MySQL service is running

**Frontend loads but API calls fail:**
- Open browser DevTools → Network tab → check the API request URL
- Make sure `API_BASE` in `frontend/public/js/app.js` matches your Railway domain exactly

**Database tables not found:**
- Re-run `docs/database-schema.sql` via Railway's Query tab

**Railway build fails:**
- Check that **Root Directory** is set to `backend` in Railway service settings
- Railway should pick up `backend/Dockerfile` automatically
