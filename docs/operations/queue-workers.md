# Production Queue Workers

## Purpose

Rainfall and emissions CSV imports are asynchronous. The web request receives the file and stores it on disk, then Redis queues the import. A worker performs file hashing, CSV validation, duplicate-file checks, staging, and fact upserts.

Do not use `QUEUE_CONNECTION=sync` in production. It executes CSV processing inside the web request and can exhaust request memory/CPU or time out.

## Environment Variables

Set these in the hosting environment-variable UI for the production application. Use the Redis hostname, port, password, and TLS settings supplied by the host; do not copy local values when production Redis is remote.

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=your-redis-host
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=imports
REDIS_QUEUE_RETRY_AFTER=960
```

`CACHE_STORE=redis` also enables the public emissions explorer's version-scoped response cache. It does not start a worker and does not make uploads run in the web request; worker processing remains controlled by `QUEUE_CONNECTION` and the Supervisor process below.

After saving the environment variables, run:

```bash
php artisan optimize:clear
```

## Hosting UI Worker Fields

Create one always-on Supervisor or Background Worker process in the hosting UI.

| UI field | Value |
| --- | --- |
| Name | `climatehub-imports` |
| Working directory | Absolute path to the Laravel application root |
| Command | Absolute PHP path followed by `artisan queue:work redis --queue=imports --tries=3 --timeout=900 --sleep=3 --no-interaction` |
| Processes | `1` |
| Auto restart | Enabled |
| Start on deploy/boot | Enabled |
| Run as user | The application-file owner, never `root` |
| Standard output | `storage/logs/queue-imports.log` |
| Standard error | `storage/logs/queue-imports.log` |
| Stop timeout | `900` seconds |

Example command when the PHP binary is `/usr/bin/php` and the application is `/var/www/climatehub`:

```bash
/usr/bin/php /var/www/climatehub/artisan queue:work redis --queue=imports --tries=3 --timeout=900 --sleep=3 --no-interaction
```

Use the absolute PHP and application paths shown by the hosting UI. Keep one worker initially to cap background resource usage. Add a second worker only after observing sustained queued imports and confirming available CPU, memory, and database capacity.

The Redis retry window is 960 seconds, longer than the 900-second worker timeout. Keep that ordering if either value changes.

## Deployment Procedure

1. Deploy code and install dependencies as required by the host.
2. Save the Redis environment variables above.
3. Run `php artisan optimize:clear`.
4. Start or restart the `climatehub-imports` worker in the hosting UI.
5. Run `php artisan queue:restart` after every code deployment so existing workers reload the new application code.
6. Stage a small CSV in the ClimateHub CP, verify it becomes `Awaiting approval`, approve it, then confirm it becomes `Applied` or `Rejected duplicate` with its recorded reason.

## Operational Checks

```bash
php artisan tinker --execute='var_export(["queue" => config("queue.default"), "queue_name" => config("queue.connections.redis.queue")]); echo PHP_EOL;'
php artisan queue:failed
tail -f storage/logs/queue-imports.log
```

Expected queue settings are `redis` and `imports`. A duplicate source file is validly rejected after approval by the worker and is recorded as `Rejected duplicate` with `Same file as import #<id>.`

## Local Use

`npm run dev` only starts Vite. Start a worker manually only when processing queued imports:

```bash
php artisan queue:work redis --queue=imports --tries=3 --timeout=900 --sleep=3
```

For one job only:

```bash
php artisan queue:work redis --queue=imports --once --tries=3 --timeout=900
```
