# ClimateHub CSV Import Templates

These files are controlled column templates for the ClimateHub import pipeline.
The controlled templates contain headers only; the Kyoto GHG file is a
source-derived example that demonstrates the required aggregate and
component-sector rows. Copy a template, add source rows, and place the copy in
an approved runtime import directory before staging it in the control panel.

Do not upload these repository files directly. Runtime imports must live under
`climate-data` or `storage/app/climatehub/imports`; the control panel validates,
stages, and requires approval before rows are applied.

## Topic folders

| Topic | Template | Controlled schema |
| --- | --- | --- |
| Emissions | `emissions/country-year-sector-gas.csv` | `wide_country_sector_gas_year` |
| Emissions example | `emissions/kyoto-ghg-total-excluding-lulucf-example.csv` | `wide_country_sector_gas_year` |
| Rainfall | `rainfall/subnational-indicators.csv` | `long_admin_period_indicator` |
| Floods | `floods/event-impacts.csv` | `event_impact` |
| Risk | `risk/subnational-risk-indicators.csv` | `long_admin_period_indicator` |
| Policy | `policy/documents.csv` | `country_document` |
| Policy | `policy/sector-responses.csv` | `country_document_sector_response` |
| Finance | `finance/country-year-indicators.csv` | `country_year_indicator` |
| Environment | `environment/country-year-indicators.csv` | `country_year_indicator` |

## Required conventions

- Use canonical country codes such as `NGA` and canonical geography PCODEs.
- Keep all numeric values unformatted: use `1250.5`, not `1,250.5` or `N1,250.5`.
- A row with the same dataset version and natural key updates the existing fact;
  a new key inserts a new fact. The relevant key is shown beside each template.
- Use the relevant topic workspace in the control panel for validation,
  approval, application, and publication. Published versions alone are visible
  to public API routes.
- The Kyoto GHG example is source-derived from the approved PRIMAP file for
  Nigeria. `Total excluding LULUCF` is the published source aggregate and must
  not be calculated from the component-sector rows.

## Finance note

The current finance contract stores annual allocation measures through the
country-year indicator schema. It does not yet accept recipient, transaction
state, allocation label, theme, or currency columns. Those fields need an
approved finance-specific schema before they can be imported and published.
