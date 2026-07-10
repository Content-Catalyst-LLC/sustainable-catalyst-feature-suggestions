# Advanced Surveys and Conditional Logic

Version 2.5.0 extends the form foundation with multi-step pages, field-level branching, response quotas, question and option randomization, browser-local save and resume, and schema revision metadata.

## Conditional logic
Each field may reference a prior stable field key and use `equals`, `not_equals`, `contains`, or `answered`. Hidden fields are disabled and excluded from submission.

## Multi-step surveys
Assign page numbers to fields and enable Multi-step pages. Required fields are validated before advancing.

## Save and resume
Optional browser-local storage retains answers on the same device. No draft response is sent to the server until final submission.

## Quotas
A response quota closes the instrument after the configured number of stored responses. This is an application-level limit and should not be treated as a high-concurrency transactional quota.

## Randomization
Questions can be randomized within their assigned page, and choice options can be randomized per field.
