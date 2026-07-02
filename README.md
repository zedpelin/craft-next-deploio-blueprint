# CraftCMS 5 + Next.js 15 on Deploio

A minimal blueprint for running a headless CraftCMS 5 backend with a Next.js 15 frontend on [Deploio](https://deploio.com), Nine Internet Solutions AG's Heroku-style PaaS.

The backend exposes a GraphQL API. The frontend fetches content at request time via that API. Both apps are deployed separately on Deploio and connect over HTTPS.

## Repo structure

```
.                       # Craft CMS backend (PHP)
├── config/             # Craft + Yii app config (DB, cache, filesystems, GQL)
├── migrations/         # Content migrations (News section, GQL schema, bearer token)
├── frontend/           # Next.js 15 frontend
│   ├── app/            # App Router pages
│   └── lib/craft.ts    # Typed GraphQL helper
├── Procfile            # Deploio/Heroku: run migrations then start Apache
└── nginx.conf          # URL rewriting rules for the nginx buildpack
```

## Prerequisites

- [nctl](https://docs.nine.ch/docs/deploio/getting-started) installed and authenticated
- A Deploio project: `nctl project create my-project`

## Deploy the Craft backend

### 1. Create the app

```bash
nctl app create craft-backend --project my-project --git-url https://github.com/your-org/craft-next-deploio-blueprint
```

### 2. Provision a PostgreSQL database

```bash
nctl service create craft-db --type postgresql --project my-project
```

### 3. Connect the database to the app

```bash
nctl app service add craft-backend --service craft-db --name craft --project my-project
```

This injects `NINE_PG_CRAFT_*` env vars into the app at runtime. The `config/db.php` file reads them automatically.

### 4. Set required env vars

```bash
# Generate a unique app ID (any string, e.g. your app name)
nctl app env set craft-backend CRAFT_APP_ID=my-craft-app --project my-project

# Generate a security key
nctl app exec craft-backend -- php craft setup/security-key --project my-project
# Copy the printed key, then:
nctl app env set craft-backend CRAFT_SECURITY_KEY=<key> --project my-project
```

### 5. Push to trigger a build

```bash
git push
```

The `Procfile` runs `php craft migrate/all` on every start. On first deploy this:
- Installs Craft
- Creates the News section, body field, and GQL schema
- Generates a bearer token and **prints it to the deploy log**

### 6. Install Craft

After the first build completes:

```bash
nctl app exec craft-backend -- php craft install/craft --project my-project
```

Follow the prompts to create an admin account.

### 7. Retrieve the bearer token

```bash
nctl app logs craft-backend --project my-project | grep "BEARER TOKEN"
```

Copy the token — you need it for the frontend.

## Deploy the Next.js frontend

### 1. Create the frontend app

```bash
nctl app create craft-frontend \
  --project my-project \
  --git-url https://github.com/your-org/craft-next-deploio-blueprint \
  --build-subdir frontend
```

### 2. Set env vars

```bash
# Your Craft backend's Deploio hostname (no https://)
nctl app env set craft-frontend CRAFT_HOST=craft-backend.<id>.deploio.app --project my-project

nctl app env set craft-frontend CRAFT_GQL_URL=https://craft-backend.<id>.deploio.app/actions/graphql/api --project my-project

nctl app env set craft-frontend CRAFT_GQL_TOKEN=<token from step 7 above> --project my-project
```

### 3. Push to trigger a build

```bash
git push
```

The frontend app will be live at its Deploio URL. It renders the News entries from Craft.

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

The bearer token is printed during `php craft migrate/all` if it didn't exist yet. Copy it to `frontend/.env.local`.

### Next.js frontend

```bash
cd frontend
npm install
cp .env.local.example .env.local
# Edit .env.local: set CRAFT_HOST, CRAFT_GQL_URL, CRAFT_GQL_TOKEN
npm run dev
```

The frontend runs on `http://localhost:3000`.

## Optional: Object Storage for assets

To store Craft uploads in Nine Object Storage (S3-compatible) instead of the ephemeral local filesystem:

1. Create a bucket in the Nine console.
2. Set these env vars on the Craft app:
   ```
   BUCKET_ENDPOINT=https://<cluster>.s3.nine.ch
   BUCKET_REGION=nine-cz42
   BUCKET_NAME=<your-bucket>
   BUCKET_KEY=<access-key>
   BUCKET_SECRET=<secret-key>
   ```
3. In the Craft CP, create a Filesystem with handle `nine-s3` (type: Amazon S3). The credentials are injected from env vars via `config/filesystems.php` — leave the fields blank in the CP.
4. Create an Asset Volume that uses the `nine-s3` filesystem.

## Optional: Redis cache

To use Nine Key-Value Store (Redis-compatible) for Craft's cache:

```bash
nctl service create craft-cache --type kvs --project my-project
nctl app service add craft-backend --service craft-cache --name cache --project my-project
```

This injects `NINE_KVS_CACHE_*` env vars. The `config/app.php` file detects them and switches Craft's cache component to Redis automatically.
