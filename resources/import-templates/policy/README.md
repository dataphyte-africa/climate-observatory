# Policy

Use `documents.csv` for document-level records and `sector-responses.csv` for
text extracted from a document's sectors or subsectors. Do not combine the two
grains in one file.

Document natural key: dataset version, country, document code.

Sector response natural key: dataset version, country, document code, question
code, subsector code.
