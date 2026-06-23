# Deployment Guide — TaskFlow
# Vercel (Frontend) + Render (Backend) + Firebase Firestore (Database)

## Architecture

| Layer    | Platform         | Stack                              |
|----------|------------------|------------------------------------|
| Frontend | Vercel           | Vanilla JS static site             |
| Backend  | Render           | PHP 8.2 + Apache (Docker)          |
| Database | Firebase         | Firestore (NoSQL, serverless)      |

No MySQL. No Railway. Data lives in Firebase Firestore.
The PHP backend talks to Firestore via the REST API using a Service Account key.

---

## STEP 1 — Create a Firebase Project

1. Go to https://console.firebase.google.com
2. Click **Add project** → name it (e.g. `taskflow`) → Continue
3. Disable Google Analytics (not needed) → **Create project**
4. Once created, click **Continue**

---

## STEP 2 — Enable Firestore

1. In the left sidebar → **Build** → **Firestore Database**
2. Click **Create database**
3. Select **Start in production mode** → Next
4. Choose a region (e.g. `us-east1` or closest to you) → **Enable**

Firestore is now ready. No schema needed — it's schema-less.

---

## STEP 3 — Set Firestore Security Rules

In the Firestore console → **Rules** tab, replace the default rules with:

```
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Backend-only access via Service Account — deny all direct client access
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

Click **Publish**. All access goes through your PHP backend only.

---

## STEP 4 — Create Required Firestore Indexes

The app uses composite queries that require indexes.
In Firestore console → **Indexes** tab → **Composite** → create these:

| Collection     | Fields (in order)                          | Query scope |
|----------------|--------------------------------------------|-------------|
| `projects`     | `user_id` ASC, `is_archived` ASC, `created_at` DESC | Collection |
| `tasks`        | `project_id` ASC, `is_deleted` ASC, `position_index` ASC, `created_at` ASC | Collection |
| `tasks`        | `project_id` ASC, `is_deleted` ASC, `status` ASC, `position_index` ASC | Collection |
| `tasks`        | `project_id` ASC, `is_deleted` ASC, `priority` ASC, `position_index` ASC | Collection |
| `activity_logs`| `project_id` ASC, `created_at` DESC        | Collection |
| `activity_logs`| `user_id` ASC, `created_at` DESC           | Collection |

> Tip: Firestore also auto-suggests missing indexes from error messages in
> the Firebase console logs when a query runs without one.

---

## STEP 5 — Get the Service Account Key

1. In Firebase console → click the ⚙️ gear icon → **Project settings**
2. Go to the **Service accounts** tab
3. Click **Generate new private key** → **Generate key**
4. A JSON file downloads — open it and note these 3 values:
   - `project_id`
   - `client_email`
   - `private_key` (the entire RSA key including `-----BEGIN...-----END...`)

Keep this file secure — **never commit it to git**.

---

## STEP 6 — Deploy the Backend to Render

1. Go to https://render.com → sign up / log in
2. Click **New** → **Web Service**
3. Connect your GitHub account → select your repo **sai-nithin24/Task**
4. Configure:
   - **Name**: `taskflow-backend`
   - **Root Directory**: `backend`
   - **Environment**: Docker (auto-detected from Dockerfile)
   - **Plan**: Free
5. Click **Create Web Service** — it starts building (takes 3-5 min first time)

### Add Environment Variables on Render

Go to your service → **Environment** tab → add these variables:

| Key                    | Value                                             |
|------------------------|---------------------------------------------------|
| `FIREBASE_PROJECT_ID`  | your Firebase project ID (e.g. `taskflow-abc12`) |
| `FIREBASE_CLIENT_EMAIL`| the `client_email` from the JSON key file         |
| `FIREBASE_PRIVATE_KEY` | the full `private_key` value — paste the entire thing including `-----BEGIN RSA PRIVATE KEY-----` and `-----END RSA PRIVATE KEY-----`. Render will store it safely. |
| `JWT_SECRET`           | any random 64-char string (see tip below)         |
| `JWT_EXPIRY`           | `3600`                                            |
| `APP_ENV`              | `production`                                      |
| `APP_DEBUG`            | `false`                                           |
| `APP_URL`              | `*` for now — update after Step 8                 |

**Tip — generate a JWT_SECRET in PowerShell:**
```powershell
-join ((65..90) + (97..122) + (48..57) | Get-Random -Count 64 | ForEach-Object {[char]$_})
```

6. Click **Save Changes** → Render auto-redeploys
7. Once deployed, go to the service URL (shown at the top of the dashboard)
8. Test: visit `https://your-service.onrender.com/api/health`
   → should return: `{"success":true,"message":"OK","data":{"status":"ok","time":"..."}}`
9. Copy your Render URL — you'll need it in the next step

---

## STEP 7 — Update the Frontend API URL

Open `frontend/public/js/app.js` and find line ~62:

```js
const API_BASE = 'REPLACE_WITH_RENDER_BACKEND_URL/api';
```

Replace with your actual Render URL:

```js
const API_BASE = 'https://taskflow-backend.onrender.com/api';
```

Save, commit and push:

```bash
git add frontend/public/js/app.js
git commit -m "Set Render backend URL"
git push
```

---

## STEP 8 — Deploy the Frontend to Vercel

1. Go to https://vercel.com → **Add New Project**
2. Import your GitHub repo **sai-nithin24/Task**
3. Configure:
   - **Root Directory**: `frontend`
   - **Framework Preset**: Other
   - **Build Command**: *(leave empty)*
   - **Output Directory**: *(leave empty)*
4. Click **Deploy**
5. Once deployed, copy your Vercel URL (e.g. `https://task-abc123.vercel.app`)

---

## STEP 9 — Fix CORS on Render

1. Go to Render → your backend service → **Environment**
2. Update `APP_URL` to your Vercel URL (no trailing slash):
   ```
   APP_URL = https://task-abc123.vercel.app
   ```
3. Click **Save Changes** → Render redeploys automatically

---

## STEP 10 — Test the Full App

1. Open your Vercel URL
2. Click **Create Account** → register
3. Create a project → add tasks → drag tasks between columns
4. Check the Activity log

---

## Local Development

Create a `.env` file in the project root (already in `.gitignore`):

```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@your-project-id.iam.gserviceaccount.com
FIREBASE_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----\n"
JWT_SECRET=any_local_dev_secret_at_least_32_chars
JWT_EXPIRY=3600
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
```

Then run:
```bash
php -S localhost:8080 backend/server.php
```

---

## Troubleshooting

**`Firebase credentials are not configured` error:**
- Check all 3 Firebase env vars are set on Render
- Make sure `FIREBASE_PRIVATE_KEY` is pasted in full — including the header/footer lines

**`Failed to load Firebase private key` error:**
- The private key must have real newlines. Render stores multi-line secrets correctly when pasted raw. If issues persist, try replacing literal `\n` in the key with actual line breaks when pasting.

**CORS errors in browser:**
- Verify `APP_URL` on Render exactly matches your Vercel domain
- No trailing slash in `APP_URL`

**Firestore `FAILED_PRECONDITION` / index error:**
- Create the missing composite index shown in the error URL (Firestore includes a direct link to create it)

**Render free tier note:**
- Services sleep after 15 min of inactivity — first request after sleep takes ~30 seconds to wake up. Expected behavior on the free plan.
