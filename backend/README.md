# Feature Suggestions Intelligence Backend v3.2.0

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
