# Emissions

Use `country-year-sector-gas.csv` for Climate Watch historical emissions and
Climate TRACE country-year sector/gas series.

Required columns: `country`, `sector`, `gas`, and one or more four-digit year
columns. `source` is optional but strongly recommended when a dataset contains
more than one provider series.

Natural key: dataset version, source, sector, gas, country, year.
