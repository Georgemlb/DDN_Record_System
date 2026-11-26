# Deploying DDN Record System (\$0 / Month Free Setup)

Because free cloud containers use ephemeral filesystems, storing a local SQLite file inside the container will lose data on restarts or redeploys. The safest **\$0/month** cloud setup pairs **Render (Free Docker Web Service)** with **Neon (Free PostgreSQL)**.

---

## Option 1: Render + Neon PostgreSQL (Recommended Cloud Setup — \$0/mo)

### 1. Create a Free PostgreSQL Database on Neon
1. Sign up at https://neon.tech (no credit card required).
2. Create a new project (e.g. `ddn-record-system`, Singapore region for lowest latency in the Philippines).
3. Copy the **Connection String** (starts with `postgresql://...`).

### 2. Deploy the App on Render
1. Push your branch/repo to GitHub.
2. Sign up at https://render.com and click **New → Blueprint** (or **New → Web Service → Docker**).
3. Connect your GitHub repository — Render will automatically detect `render.yaml` and `Dockerfile`.
4. Set the two remaining environment variables in Render:
   - `DB_URL`: Paste your Neon PostgreSQL connection string.
   - `APP_URL`: Your Render URL (e.g. `https://ddn-record-system.onrender.com`).
5. Click **Deploy**. On boot, `docker/start.sh` automatically caches config/routes/views and runs `php artisan migrate --force`.

### 3. Seed Initial Admin Account (One-Time)
Open Render's **Shell** tab for the service and run:
```bash
php artisan db:seed --force
```
Then log in with your seeded admin account and immediately change the password via **User Menu → Change Password**.

---

## Option 2: Office PC + Cloudflare Tunnel (Zero Cold-Starts — \$0/mo)

If you prefer running on an existing branch PC with instant LAN speed and zero container sleep:
1. Run the app locally (`composer run build` + `php artisan serve --host=127.0.0.1 --port=8000` or Laragon/IIS/Nginx).
2. Install `cloudflared` and create a free Cloudflare Tunnel pointing to `http://127.0.0.1:8000`.
3. Set `FORCE_HTTPS=true` and `APP_URL=https://your-subdomain.trycloudflare.com` (or your custom domain) in `.env`.
4. Schedule daily backups with:
   ```bash
   php artisan ddn:backup
   ```
