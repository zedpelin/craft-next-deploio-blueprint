# CraftCMS 5 + Next.js 15 on Deploio

A minimal blueprint for running a headless CraftCMS 5 backend with a Next.js 15 frontend on [Deploio](https://deploio.com), Nine Internet Solutions AG's Heroku-style PaaS.

The backend exposes a GraphQL API; the frontend fetches content at request time via that API. Both apps are deployed separately on Deploio and connect over HTTPS.

## Repo structure

```
.                        # Craft CMS backend (PHP)
├── config/
│   ├── app.php          # Yii app config (Redis cache component)
│   ├── db.php           # Database config (reads Deploio service-connection vars)
│   └── filesystems.php  # S3-compatible asset storage (reads BUCKET_* vars)
├── migrations/          # Content migrations (News section, GQL schema, bearer token)
├── web/index.php        # Craft web entry point
├── Procfile             # Deploio: run migrations then start Apache
└── frontend/            # Next.js 15 frontend
    ├── app/             # App Router pages (/, /[slug])
    ├── lib/craft.ts     # Typed GraphQL helper
    └── Procfile         # Deploio: start Next.js
```

## Prerequisites

- [nctl](https://docs.nine.ch/docs/deploio/getting-started) installed and authenticated
- A Deploio project (`nctl create project my-project`)

Set it as the active project:

```bash
nctl auth set-project my-project
```

## Part 1: Deploy the Craft backend

### 1. Create the app

```bash
nctl create app craft-backend \
  --git-url=https://github.com/YOUR_ORG/craft-next-deploio-blueprint \
  --git-revision=main \
  --buildpack-stack=heroku
```

### 2. Set required environment variables

```bash
nctl update app craft-backend \
  --sensitive-env="CRAFT_SECURITY_KEY=$(openssl rand -base64 32)" \
  --env="CRAFT_ENVIRONMENT=production" \
  --env="CRAFT_DEV_MODE=false" \
  --env="CRAFT_ALLOW_ADMIN_CHANGES=false" \
  --env="CRAFT_STORAGE_PATH=/tmp/craft-storage"
```

`CRAFT_SECURITY_KEY` is required — Craft refuses to start without it. `CRAFT_STORAGE_PATH` redirects Craft's runtime writes (logs, caches) to a writable path on Deploio.

### 3. Provision and connect the database

```bash
nctl create postgresdatabase craft-db --wait

nctl update app craft-backend --service craft=postgresdatabase/craft-db
```

The `--service` flag tells Deploio to inject the database credentials automatically as `NINE_PGDB_CRAFT_*` environment variables. The `config/db.php` in this blueprint reads those variables directly — no manual URL construction needed.

### 4. Trigger the first build

The app's build starts automatically once the git URL and env vars are set. Monitor it:

```bash
nctl get builds
```

Wait until a build with `STATUS=Succeeded` appears for `craft-backend`.

### 5. Install Craft (create admin account)

On first startup the Procfile runs `php craft migrate/all`, which creates the database schema, sets up the News section, and generates a GraphQL bearer token. Once the build has succeeded and the app is running:

```bash
nctl exec app craft-backend -- php craft install/craft
```

Follow the interactive prompts to set the site name, URL, admin email, username, and password. Use the app's Deploio hostname as the site URL — get it with:

```bash
nctl get app craft-backend
```

The URL is in the `HOSTS` column and looks like `craft-backend.{HASH}.deploio.app`.

### 6. Retrieve the GraphQL bearer token

The migration prints the token to the application log on first startup:

```bash
nctl logs app craft-backend --type=app | grep "BEARER TOKEN"
```

Copy the token — you'll need it when deploying the frontend.

> If the token line has already scrolled out of the log window, you can find it in the Craft control panel under **Settings → GraphQL → Tokens → frontend-token**.

---

## Optional: Redis cache

Adding a key-value store enables Craft's cache and session storage.

```bash
nctl create keyvaluestore craft-cache --wait

nctl update app craft-backend --service cache=keyvaluestore/craft-cache
```

The `config/app.php` in this blueprint detects the injected `NINE_KVS_CACHE_*` variables and switches Craft's cache component to Redis automatically.

## Optional: Object storage for assets

By default Craft stores uploaded assets on the local filesystem, which is ephemeral on Deploio. To persist assets across restarts, connect Nine Object Storage.

```bash
# Create a bucket in location nine-cz42 or nine-es34 (same location as your app)
nctl create bucket craft-assets --location=nine-cz42 --wait

# Create a bucket user to hold write credentials
nctl create bucketuser craft-assets-user --location=nine-cz42 --wait

# Grant the user write access to the bucket
nctl update bucket craft-assets --permissions="writer=craft-assets-user"

# Retrieve the credentials
ACCESS_KEY=$(nctl get bucketuser craft-assets-user --print-access-key)
SECRET_KEY=$(nctl get bucketuser craft-assets-user --print-secret-key)

# Inject them into the app
nctl update app craft-backend \
  --env="BUCKET_ENDPOINT=https://cz42.objects.nineapis.ch" \
  --env="BUCKET_NAME=craft-assets" \
  --env="BUCKET_REGION=us-east-1" \
  --sensitive-env="BUCKET_KEY=${ACCESS_KEY}" \
  --sensitive-env="BUCKET_SECRET=${SECRET_KEY}"
```

> Adjust the endpoint to match your chosen location: `cz42` → `https://cz42.objects.nineapis.ch`, `es34` → `https://es34.objects.nineapis.ch`.

With the env vars set, the `config/filesystems.php` in this blueprint configures an S3-compatible filesystem automatically. The last step is to wire it up inside Craft:

1. Log in to the Craft control panel at `https://craft-backend.{HASH}.deploio.app/admin`.
2. Go to **Settings → Filesystems → New filesystem**.
3. Set the handle to `nine-s3` and the type to **Amazon S3**. Leave all credential fields blank — they are read from environment variables.
4. Create an **Asset Volume** that uses the `nine-s3` filesystem.

---

## Part 2: Deploy the Next.js frontend

### 1. Get the backend URL

```bash
nctl get app craft-backend
```

Note the full hostname from the `HOSTS` column, e.g. `craft-backend.baa74b2.deploio.app`.

### 2. Create the frontend app

```bash
nctl create app craft-frontend \
  --git-url=https://github.com/YOUR_ORG/craft-next-deploio-blueprint \
  --git-revision=main \
  --git-sub-path=frontend \
  --buildpack-stack=heroku \
  --port=3000 \
  --env="CRAFT_GQL_URL=https://craft-backend.{HASH}.deploio.app/actions/graphql/api" \
  --sensitive-env="CRAFT_GQL_TOKEN={YOUR_BEARER_TOKEN}"
```

Replace `{HASH}` with your project hash and `{YOUR_BEARER_TOKEN}` with the token from Part 1.

> **Note the GraphQL endpoint path.** CraftCMS 5 exposes the GraphQL API at `/actions/graphql/api`, not `/api`.

---

## Verify the deployment

1. Open the Craft control panel at `https://craft-backend.{HASH}.deploio.app/admin` and create one or two entries in the **News** section.
2. Visit the frontend at `https://craft-frontend.{HASH}.deploio.app` — your entries should appear.
3. Click an entry to confirm the detail page loads.

---

## Troubleshooting

**App fails to start after first deploy**

Check if `CRAFT_SECURITY_KEY` is set:

```bash
nctl get app craft-backend -o yaml | grep CRAFT_SECURITY_KEY
```

**403 from the GraphQL API**

The GQL schema scope may be missing. Check whether the content migration ran:

```bash
nctl logs app craft-backend --type=app | grep -i "migration\|bearer\|error"
```

**No entries on the frontend**

Ensure the bearer token in `CRAFT_GQL_TOKEN` on the frontend app matches what is stored in Craft. You can verify the token in the Craft CP under **Settings → GraphQL → Tokens → frontend-token**.

**401 from the GraphQL API**

If basic auth is enabled on the Craft backend, the `Authorization` header for basic auth conflicts with the `Authorization: Bearer` header for GraphQL. Disable it:

```bash
nctl update app craft-backend --basic-auth=false
```

---

## Local development

### Craft backend

```bash
composer install
cp .env.example .env
# Edit .env: fill in CRAFT_APP_ID, CRAFT_SECURITY_KEY, and local DB credentials
php craft setup
php craft migrate/all
php craft serve
```

The bearer token is printed during `php craft migrate/all` when it runs for the first time. Copy it to `frontend/.env.local`.

### Next.js frontend

```bash
cd frontend
npm install
# Create frontend/.env.local with:
#   CRAFT_GQL_URL=http://localhost:8080/actions/graphql/api
#   CRAFT_GQL_TOKEN=<token from above>
npm run dev
```

The frontend runs on `http://localhost:3000`.
