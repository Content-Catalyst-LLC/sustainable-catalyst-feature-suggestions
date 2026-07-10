# AI Triage Service

Feature Suggestions v2.2.0 adds an advisory Python/FastAPI classification service. WordPress sends the suggestion title and structured feedback fields to `/v1/analyze`; the service returns a versioned record containing a summary, feature type, platform area, topics, sentiment, safety flags, duplicate keys, suggested action, roadmap destination, scores, confidence, and rationale.

The original submission is never overwritten. Analysis is stored separately in WordPress post metadata and always has `human_review_required: true`.

## Environment

- `SCFS_AI_PROVIDER`: `deterministic`, `gemini`, `deepseek`, `openai`, or `disabled`
- `SCFS_AI_API_KEY`: shared secret used by WordPress
- `SCFS_AI_MODEL`: optional provider model override
- `SCFS_GEMINI_API_KEY`, `SCFS_DEEPSEEK_API_KEY`, or `SCFS_OPENAI_API_KEY`

Start with `deterministic` during deployment validation, then enable Gemini or another provider after health checks pass.
