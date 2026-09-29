# Rainfall And Emissions Ingestion Workflows

## Shared Operator Flow

Rainfall and emissions use the same controlled ingestion lifecycle:

1. The operator selects a CSV and clicks **Stage CSV** in the Statamic CP.
2. The web request stores the file and creates a pending `imports` record. It does not parse or hash the CSV.
3. Redis queue `imports` runs `ProcessCsvDatasetImport`, which hashes the file, validates the schema, stages accepted facts, and records row errors.
4. A fully valid import becomes `awaiting_approval`; imports with validation errors remain rejected from approval.
5. The operator clicks **Approve**. Redis queue `imports` runs `ApplyApprovedCsvDatasetImport`.
6. The worker checks the file hash against earlier imports for the same dataset version. A repeat is recorded as `rejected_duplicate` with `Same file as import #<id>.` No facts are written.
7. A non-duplicate import is upserted by its schema natural key, then marked `applied`.

The CP import history shows ten imports per page, duplicate links or rejection messages, validation counts, and the approval action where applicable.

## Rainfall

| Item | Contract |
| --- | --- |
| Dataset | `nigeria_rainfall_subnational` |
| Schema | `long_admin_period_indicator` |
| Input shape | One observation per row: period/date, geography code, and rainfall indicator values |
| Fact table | `admin_period_indicator_values` |
| Fact key | Dataset version, indicator, geography code, period date, and period type |
| CP facts filters | Indicator, geography code, from date, to date |

Rainfall CSV rows are validated and staged before approval. Applying a valid file upserts the matching natural keys and queues the Statamic rainfall-fact synchronization.

## Emissions

| Item | Contract |
| --- | --- |
| Dataset | `climatewatch_historical_emissions` |
| Schema | `wide_country_sector_gas_year` |
| Input shape | One source row contains country, sector, gas, optional source, and one or more `YYYY` columns |
| Fact table | `country_year_sector_gas_values` |
| Fact key | Dataset version, source, sector, gas, country code, and reporting year |
| CP facts filters | Country code, sector, gas, from year, to year |

Each emissions CSV row can create several annual facts, one for each populated year column. The CP never merges Climate Watch and Climate TRACE data: their dataset version and source fields remain part of the fact scope.

## Worker Requirement

Both flows require `QUEUE_CONNECTION=redis` and an `imports` worker. Production worker setup is documented in [Production Queue Workers](queue-workers.md).
