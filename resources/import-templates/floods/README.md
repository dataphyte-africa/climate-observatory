# Floods

Use `event-impacts.csv` for a source-reported flood event and a single impact
metric. Use a separate row for each metric reported for the same event.

Required columns: `country`, `event_date`, `event_type`, `metric_code`.
Optional columns: `event_name`, `geography_code`, `value`, `unit`, `source`.

Natural key: dataset version, country, geography, event date, event type,
event name, metric code.
