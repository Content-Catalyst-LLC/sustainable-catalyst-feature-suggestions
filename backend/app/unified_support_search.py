from __future__ import annotations

import re
from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field


RecordKind = Literal["known_issue", "support_article", "release", "public_suggestion"]
ResolutionState = Literal["strong_match", "possible_match", "low_confidence", "no_match"]

SYNONYMS: Dict[str, List[str]] = {
    "broken": ["error", "failed", "failure", "not working", "issue"],
    "error": ["broken", "failure", "failed", "exception", "problem"],
    "install": ["installation", "setup", "configure", "configuration"],
    "export": ["download", "report", "csv", "pdf"],
    "import": ["upload", "ingest", "migration"],
    "api": ["endpoint", "rest", "integration", "connector"],
    "mobile": ["responsive", "phone", "tablet"],
    "slow": ["performance", "timeout", "latency"],
    "version": ["release", "upgrade", "compatibility"],
    "article": ["guide", "documentation", "support article"],
}

STOPWORDS = {
    "the", "and", "for", "with", "this", "that", "from", "into", "when",
    "what", "where", "your", "have", "does", "please",
}


class UnifiedSupportRecord(BaseModel):
    record_id: str = Field(min_length=1, max_length=120)
    kind: RecordKind
    title: str = Field(min_length=1, max_length=300)
    summary: str = ""
    content: str = ""
    products: List[str] = Field(default_factory=list)
    product_versions: List[str] = Field(default_factory=list)
    components: List[str] = Field(default_factory=list)
    article_types: List[str] = Field(default_factory=list)
    status: str = ""
    severity: str = ""
    updated_at: str = ""
    promoted: bool = False
    editorial_priority: int = Field(default=0, ge=0, le=100)


class UnifiedSupportSearchRequest(BaseModel):
    query: str = Field(default="", max_length=500)
    error_message: str = Field(default="", max_length=1200)
    product: str = ""
    product_version: str = ""
    component: str = ""
    records: List[UnifiedSupportRecord] = Field(default_factory=list, max_length=1000)
    limit_per_group: int = Field(default=6, ge=1, le=25)


class UnifiedSupportResultItem(BaseModel):
    record_id: str
    kind: RecordKind
    title: str
    score: float = Field(ge=0)
    reasons: List[str]
    matched_tokens: List[str]
    status: str = ""
    severity: str = ""


class UnifiedSupportGroups(BaseModel):
    known_issues: List[UnifiedSupportResultItem] = Field(default_factory=list)
    support_articles: List[UnifiedSupportResultItem] = Field(default_factory=list)
    releases: List[UnifiedSupportResultItem] = Field(default_factory=list)
    public_suggestions: List[UnifiedSupportResultItem] = Field(default_factory=list)


class ResolutionJourneyStep(BaseModel):
    key: str
    label: str
    status: Literal["start_here", "available", "not_found", "recommended"]
    count: int = Field(ge=0)


class UnifiedSupportSearchResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-unified-support-search/1.0", alias="schema")
    journey_schema: str = "scfs-support-resolution-journey/1.0"
    version: str = "5.4.0"
    query: str
    result_count: int
    top_score: float
    confidence: float = Field(ge=0, le=1)
    resolution_state: ResolutionState
    groups: UnifiedSupportGroups
    journey: List[ResolutionJourneyStep]
    start_step: str
    generated_at: str
    personal_data_stored: bool = False
    automatic_case_creation: bool = False
    human_review_required: bool = True


def normalize(value: str) -> str:
    value = re.sub(r"[^a-z0-9._+\-\s]+", " ", (value or "").lower())
    return re.sub(r"\s+", " ", value).strip()


def query_tokens(value: str, expand: bool = True) -> List[str]:
    base = [token for token in normalize(value).split() if len(token) > 1 and token not in STOPWORDS]
    tokens = list(dict.fromkeys(base))
    if not expand:
        return tokens
    expanded = list(tokens)
    for token in tokens:
        expanded.extend(SYNONYMS.get(token, []))
    return list(dict.fromkeys(normalize(item) for item in expanded if normalize(item)))


def score_record(record: UnifiedSupportRecord, payload: UnifiedSupportSearchRequest) -> UnifiedSupportResultItem:
    query = normalize(payload.query)
    error = normalize(payload.error_message)
    title = normalize(record.title)
    summary = normalize(record.summary)
    content = normalize(record.content)
    taxonomies = normalize(" ".join(record.products + record.product_versions + record.components + record.article_types))
    haystack = f"{title} {summary} {content} {taxonomies}"
    original = query_tokens(f"{query} {error}", False)
    expanded = query_tokens(f"{query} {error}", True)
    reasons: List[str] = []
    matched: List[str] = []
    score = 0.0

    if query and query in title:
        score += 140
        reasons.append("Exact title phrase")
    if query and query in summary:
        score += 90
        reasons.append("Exact summary phrase")
    if error and error in haystack:
        score += 48
        reasons.append("Error signature match")

    for token in expanded:
        token_score = 0
        if token in title:
            token_score += 34
        if token in summary:
            token_score += 22
        if token in taxonomies:
            token_score += 28
        if token in content:
            token_score += 7
        if token_score:
            score += token_score
            matched.append(token)

    original_matches = sum(1 for token in original if token in matched)
    minimum = max(1, (len(original) + 1) // 2) if original else 0
    if original and original_matches < minimum:
        return UnifiedSupportResultItem(
            record_id=record.record_id,
            kind=record.kind,
            title=record.title,
            score=0.0,
            reasons=[],
            matched_tokens=list(dict.fromkeys(matched)),
            status=record.status,
            severity=record.severity,
        )

    product_values = {normalize(value) for value in record.products}
    version_values = {normalize(value) for value in record.product_versions}
    component_values = {normalize(value) for value in record.components}
    if payload.product and normalize(payload.product) in product_values:
        score += 24
        reasons.append("Product match")
    if payload.product_version and normalize(payload.product_version) in version_values:
        score += 20
        reasons.append("Version match")
    if payload.component and normalize(payload.component) in component_values:
        score += 22
        reasons.append("Component match")

    if record.promoted:
        score += 28
        reasons.append("Editorially promoted")
    score += min(20, record.editorial_priority / 5)

    if record.kind == "known_issue":
        if normalize(record.status) not in {"resolved", "closed"}:
            score += 26
            reasons.append("Current known issue")
        score += {"critical": 22, "high": 16, "moderate": 9, "low": 3}.get(normalize(record.severity), 0)
    elif record.kind == "support_article":
        score += 8
        reasons.append("Verified guidance candidate")
    elif record.kind == "release" and payload.product_version:
        score += 10
        reasons.append("Release context")

    return UnifiedSupportResultItem(
        record_id=record.record_id,
        kind=record.kind,
        title=record.title,
        score=round(max(0, score), 2),
        reasons=list(dict.fromkeys(reasons)),
        matched_tokens=list(dict.fromkeys(matched)),
        status=record.status,
        severity=record.severity,
    )


def _journey(groups: UnifiedSupportGroups, state: ResolutionState) -> tuple[List[ResolutionJourneyStep], str]:
    definitions = [
        ("known_issues", "Check current known issues", len(groups.known_issues)),
        ("support_articles", "Follow verified guidance", len(groups.support_articles)),
        ("releases", "Review release context", len(groups.releases)),
        ("public_suggestions", "Consider related improvements", len(groups.public_suggestions)),
    ]
    steps: List[ResolutionJourneyStep] = []
    start_step = "private_support"
    started = False
    for key, label, count in definitions:
        if count and not started:
            status: Literal["start_here", "available", "not_found", "recommended"] = "start_here"
            start_step = key
            started = True
        elif count:
            status = "available"
        else:
            status = "not_found"
        steps.append(ResolutionJourneyStep(key=key, label=label, status=status, count=count))
    steps.append(ResolutionJourneyStep(
        key="private_support",
        label="Continue to private support when unresolved",
        status="recommended" if state in {"no_match", "low_confidence"} else "available",
        count=0,
    ))
    return steps, start_step


def search_unified_support(payload: UnifiedSupportSearchRequest) -> UnifiedSupportSearchResult:
    scored = [(record, score_record(record, payload)) for record in payload.records]
    query_present = bool(normalize(f"{payload.query} {payload.error_message}"))
    if query_present:
        scored = [row for row in scored if row[1].score > 0]
    scored.sort(key=lambda row: (-row[1].score, row[0].title.lower()))

    grouped: Dict[str, List[UnifiedSupportResultItem]] = {
        "known_issues": [],
        "support_articles": [],
        "releases": [],
        "public_suggestions": [],
    }
    kind_to_group = {
        "known_issue": "known_issues",
        "support_article": "support_articles",
        "release": "releases",
        "public_suggestion": "public_suggestions",
    }
    for _, item in scored:
        group = kind_to_group[item.kind]
        if len(grouped[group]) < payload.limit_per_group:
            grouped[group].append(item)

    groups = UnifiedSupportGroups(**grouped)
    flattened = groups.known_issues + groups.support_articles + groups.releases + groups.public_suggestions
    flattened.sort(key=lambda item: (-item.score, item.title.lower()))
    top_score = flattened[0].score if flattened else 0.0
    confidence = min(0.99, round(top_score / 190, 4))
    if not flattened:
        state: ResolutionState = "no_match"
    elif confidence >= 0.72:
        state = "strong_match"
    elif confidence >= 0.42:
        state = "possible_match"
    else:
        state = "low_confidence"
    journey, start_step = _journey(groups, state)

    return UnifiedSupportSearchResult(
        query=normalize(payload.query),
        result_count=len(flattened),
        top_score=top_score,
        confidence=confidence,
        resolution_state=state,
        groups=groups,
        journey=journey,
        start_step=start_step,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
