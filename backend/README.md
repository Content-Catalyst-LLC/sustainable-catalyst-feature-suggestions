# Feature Suggestions Intelligence Backend v4.4.0

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



## v4.2.0 editorial governance

Deterministic endpoints evaluate editorial transitions, documentation standards, and governance queue summaries. These endpoints never approve or publish content; WordPress remains the source of truth and human review is required.

## v4.1.1 content-operations reliability

The backend adds deterministic source inspection, import recovery planning, and export integrity verification:

- `POST /v1/support-content/import/inspect`
- `POST /v1/support-content/import/recovery`
- `POST /v1/support-content/export/verify`

These endpoints do not publish content, delete records, or import private support cases. WordPress remains the system of record.

## v4.3.0 repository and release synchronization

Deterministic advisory endpoints evaluate repository candidates, drift, release ingestion plans, and link health:

- `GET /v1/repository-sync/capabilities`
- `POST /v1/repository-sync/candidates/evaluate`
- `POST /v1/repository-sync/drift/evaluate`
- `POST /v1/repository-sync/releases/plan`
- `POST /v1/repository-sync/link-health/summarize`

These endpoints never fetch private repositories, modify WordPress, approve content, or publish records.


## v4.4.0 support reliability intelligence

Deterministic advisory endpoints score product reliability, summarize trends, prioritize unresolved-query clusters, and verify report integrity:

- `GET /v1/support-reliability/capabilities`
- `POST /v1/support-reliability/score`
- `POST /v1/support-reliability/trends/summarize`
- `POST /v1/support-reliability/clusters/prioritize`
- `POST /v1/support-reliability/reports/verify`

These endpoints do not store private case content, alter roadmaps, declare incidents, or publish WordPress records.

