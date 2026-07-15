# Feature Suggestions Intelligence Backend v4.0.2

FastAPI service for advisory classification of Feature Suggestions. It supports deterministic local classification and optional Gemini, DeepSeek, or OpenAI structured analysis. AI output never changes roadmap status automatically and always requires human review.

## Run locally

```bash
cd backend
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

Configure WordPress with the deployed base URL and the same `SCFS_AI_API_KEY` value.


Survey endpoints: `POST /v1/surveys/analyze` and `GET /v1/surveys/methodology`.

Knowledge Base capability contract: `GET /v1/knowledge-base/capabilities`. WordPress remains the source of truth for Support Articles and Known Issues.


## Guided-resolution ranking

`POST /v1/guided-resolution/rank` applies the same deterministic relevance signals used by the WordPress workflow to supplied public candidates. WordPress remains the content and analytics source of truth.


## Documentation intelligence endpoints

- `GET /v1/documentation-intelligence/capabilities`
- `POST /v1/documentation-intelligence/gaps/score`
- `POST /v1/documentation-intelligence/support-demand/score`

These deterministic endpoints mirror the WordPress scoring methods. WordPress remains the source of truth for feedback, documentation gaps, case relationships, and roadmap records.

## Product Support Platform endpoints

- `GET /v1/product-support/capabilities`
- `POST /v1/product-support/overview`
- `POST /v1/product-support/releases/score`

These deterministic endpoints summarize public support signals and release readiness. They do not store private cases or make automatic release or roadmap decisions.
