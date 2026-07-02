<?php

use craft\config\DbConfig;
use craft\helpers\App;

// Deploio auto-injects DB credentials via ServiceConnection when a PostgreSQL
// service reference is declared on the app. The env var prefix depends on tier:
//   Economy (postgresdatabase) → NINE_PGDB_<NAME>_*  (db name == username)
//   Business (postgresql)      → NINE_PG_<NAME>_*
// The reference name "craft" (from --service craft=.../craft-db) becomes "CRAFT".
//
// Locally, return [] so Craft falls back to CRAFT_DB_* vars from .env.

if ($fqdn = App::env('NINE_PGDB_CRAFT_FQDN')) {
    return DbConfig::create()
        ->driver('pgsql')
        ->server($fqdn)
        ->port((int)(App::env('NINE_PGDB_CRAFT_PORT') ?: 5432))
        ->database(App::env('NINE_PGDB_CRAFT_USER') ?: '')
        ->user(App::env('NINE_PGDB_CRAFT_USER') ?: '')
        ->password(App::env('NINE_PGDB_CRAFT_PASSWORD') ?: '');
}

if ($fqdn = App::env('NINE_PG_CRAFT_FQDN')) {
    return DbConfig::create()
        ->driver('pgsql')
        ->server($fqdn)
        ->port((int)(App::env('NINE_PG_CRAFT_PORT') ?: 5432))
        ->database('postgres')
        ->user(App::env('NINE_PG_CRAFT_USER') ?: '')
        ->password(App::env('NINE_PG_CRAFT_PASSWORD') ?: '');
}

return [];
