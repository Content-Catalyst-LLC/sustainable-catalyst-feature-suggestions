# Survey Analysis and Python Research Intelligence

Version 2.6.0 adds administrator-only survey analysis through the configured FastAPI backend.

## Measured outputs

- response and field counts
- completion and missingness
- categorical distributions
- numerical means, ranges, and standard deviations
- descriptive cross-tabs
- Cronbach alpha for administrator-defined multi-item scale groups

## Machine-assisted outputs

Open-text coding uses deterministic term frequency and returns confidence, method labels, and limited illustrative excerpts. Results require human review.

## Boundaries

The service does not claim statistical significance, causality, representativeness, validity, or professional research conclusions. Small samples and sparse subgroup cells produce warnings. Raw responses remain private WordPress records and are sent only to the backend configured by the site administrator.
