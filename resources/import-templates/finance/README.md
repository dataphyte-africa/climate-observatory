# Finance

Use `country-year-indicators.csv` for the current controlled ecological
allocation import shape.

Required columns: `country`, `indicator`, `year`, `value`.
Optional column: `source`.

Natural key: dataset version, source, indicator, country, year.

Recipient, transaction state, allocation label, theme, and currency are not
accepted by the current contract. They require the planned finance-specific
schema before import.
