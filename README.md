# ClimateHub

ClimateHub is a Dataphyte climate intelligence portal built with Laravel, Statamic, MySQL, Vite, Tailwind CSS, Alpine.js, AOS, GSAP, and locally bundled Material Symbols.

The product direction is documented in `climatehub-ai.md` and the `docs/` directory.

## Local Setup

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
```

For production assets:

```bash
npm run build
```

## Requirements

- PHP `^8.3`
- Node `^20.19.0` or `>=22.12.0`
- MySQL for structured application and dataset storage
- Redis for queues and cache once ingestion workflows begin

## Documentation

- `climatehub-ai.md`: project index and AI-assisted development map
- `docs/README.md`: documentation index
- `docs/operations/queue-workers.md`: Redis worker and hosting UI setup for CSV imports
- `docs/operations/ingestion-workflows.md`: dataset ingestion and approval workflow
