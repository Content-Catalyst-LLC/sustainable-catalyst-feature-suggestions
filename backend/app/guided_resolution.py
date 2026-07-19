import re
from typing import List, Literal
from pydantic import BaseModel, Field

STOPWORDS = {"the", "and", "for", "with", "this", "that", "from", "into", "when", "what", "where", "your", "have", "does", "please"}


class ResolutionCandidate(BaseModel):
    id: str
    kind: Literal["known_issue", "support_article", "release", "public_suggestion"]
    title: str
    text: str = ""
    products: List[str] = Field(default_factory=list)
    product_versions: List[str] = Field(default_factory=list)
    components: List[str] = Field(default_factory=list)
    status: str = ""
    severity: str = ""
    promoted: bool = False
    editorial_priority: int = Field(default=0, ge=0, le=100)


class ResolutionRankRequest(BaseModel):
    query: str = ""
    error_message: str = ""
    product: str = ""
    product_version: str = ""
    component: str = ""
    candidates: List[ResolutionCandidate] = Field(default_factory=list, max_length=500)


class RankedResolutionCandidate(BaseModel):
    id: str
    kind: str
    title: str
    score: float
    match_reasons: List[str]


class ResolutionRankResult(BaseModel):
    schema_id: str = Field(default="scfs-guided-resolution-ranking/1.0", alias="schema")
    version: str = "5.4.0"
    results: List[RankedResolutionCandidate]
    result_count: int
    confidence: float
    resolution_state: Literal["strong_match", "possible_match", "low_confidence", "no_match"]
    human_review_required: bool = True


def tokens(value: str) -> set[str]:
    found = re.findall(r"[a-z0-9][a-z0-9._-]{2,}", value.lower())
    return {token for token in found if token not in STOPWORDS}


def rank_resolution(payload: ResolutionRankRequest) -> ResolutionRankResult:
    query_tokens = tokens(f"{payload.query} {payload.error_message}")
    query_phrase = payload.query.strip().lower()
    error_phrase = payload.error_message.strip().lower()
    ranked: list[RankedResolutionCandidate] = []

    severity_scores = {"critical": 22, "high": 16, "moderate": 9, "low": 3}
    for candidate in payload.candidates:
        title = candidate.title.lower()
        haystack = f"{candidate.title} {candidate.text}".lower()
        body_tokens = tokens(haystack)
        title_tokens = tokens(title)
        score = float(len(query_tokens & body_tokens) * 4 + len(query_tokens & title_tokens) * 7)
        reasons: list[str] = []

        if query_phrase and query_phrase in haystack:
            score += 28
            reasons.append("Exact query phrase")
        if error_phrase and error_phrase in haystack:
            score += 46
            reasons.append("Error signature match")
        if payload.product and payload.product.lower() in {v.lower() for v in candidate.products}:
            score += 24
            reasons.append("Product match")
        if payload.product_version and payload.product_version.lower() in {v.lower() for v in candidate.product_versions}:
            score += 18
            reasons.append("Version match")
        if payload.component and payload.component.lower() in {v.lower() for v in candidate.components}:
            score += 22
            reasons.append("Component match")
        if candidate.promoted:
            score += 28
            reasons.append("Editorially promoted")
        score += min(20, candidate.editorial_priority / 5)
        if candidate.kind == "known_issue":
            if candidate.status not in {"resolved", "closed"}:
                score += 24
                reasons.append("Current known issue")
            score += severity_scores.get(candidate.severity, 0)

        if score > 0:
            ranked.append(RankedResolutionCandidate(
                id=candidate.id,
                kind=candidate.kind,
                title=candidate.title,
                score=round(score, 2),
                match_reasons=list(dict.fromkeys(reasons)),
            ))

    ranked.sort(key=lambda item: (-item.score, item.title.lower()))
    top = ranked[0].score if ranked else 0.0
    confidence = min(0.99, round(top / 140, 4))
    state: Literal["strong_match", "possible_match", "low_confidence", "no_match"]
    if not ranked:
        state = "no_match"
    elif confidence >= 0.72:
        state = "strong_match"
    elif confidence >= 0.42:
        state = "possible_match"
    else:
        state = "low_confidence"
    return ResolutionRankResult(results=ranked, result_count=len(ranked), confidence=confidence, resolution_state=state)
