# Risk

Use `subnational-risk-indicators.csv` for state or LGA risk measures. Add one
or more numeric columns named for the approved indicator codes, for example
`flood_exposure_score` or `risk_vulnerability_score`.

Required columns: `period_date` or `date`, `geography_code` or `pcode`, and
at least one numeric indicator column.

Natural key: dataset version, indicator, geography code, period date, period type.
