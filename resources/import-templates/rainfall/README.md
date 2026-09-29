# Rainfall

Use `subnational-indicators.csv` for state or LGA rainfall measures. Add one
or more numeric indicator columns, such as `rfh`, `r1h`, `r3h`, `rfq`, `r1q`,
or `r3q`.

Required columns: `period_date` or `date`, `geography_code` or `pcode`, and
at least one numeric indicator column.

Natural key: dataset version, indicator, geography code, period date, period type.
