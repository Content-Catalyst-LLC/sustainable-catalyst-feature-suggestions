# Advanced Surveys and Conditional Logic

Feature Suggestions v2.5.0 adds reusable WordPress forms and surveys without merging response records into feature suggestions.

## Create an instrument

1. Open **Feature Suggestions → Forms & Surveys**.
2. Create a form or survey and add ordered fields.
3. Publish it.
4. Embed it with `[sc_feedback_form id="published-slug"]`.

Supported fields: short text, long text, email, number, URL, date, dropdown, radio buttons, checkboxes, rating scales, Likert scales, consent, and hidden context.

## Responses and exports

Responses are private WordPress records under **Feature Suggestions → Responses**. Export a selected instrument from **Feature Suggestions → Form Response Export**.

## REST API

- `GET /wp-json/scfs/v1/forms/{id-or-slug}` returns a published form schema.
- `POST /wp-json/scfs/v1/forms/{id-or-slug}/responses` accepts `{ "answers": { ... } }`.

Successful submissions publish a privacy-minimized `form.response_submitted` platform event. Conditional logic, quotas, multi-page surveys, and save-and-resume are intentionally reserved for v2.5.0.
