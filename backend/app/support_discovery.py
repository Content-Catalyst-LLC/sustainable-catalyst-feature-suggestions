from __future__ import annotations

import re
from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field


SYNONYMS: Dict[str, List[str]] = {
    "broken": ["error", "failed", "failure", "not working", "issue"],
    "error": ["broken", "failure", "failed", "exception", "problem"],
    "install": ["installation", "setup", "configure", "configuration"],
    "login": ["sign in", "authentication", "access"],
    "export": ["download", "report", "csv", "pdf"],
    "import": ["upload", "ingest", "migration"],
    "api": ["endpoint", "rest", "integration", "connector"],
    "mobile": ["responsive", "phone", "tablet"],
    "slow": ["performance", "timeout", "latency"],
    "version": ["release", "upgrade", "compatibility"],
}


class DiscoveryArticle(BaseModel):
    article_id: str = Field(min_length=1, max_length=120)
    title: str = Field(min_length=1, max_length=300)
    summary: str = ""
    content: str = ""
    product: List[str] = Field(default_factory=list)
    version: List[str] = Field(default_factory=list)
    component: List[str] = Field(default_factory=list)
    article_type: List[str] = Field(default_factory=list)
    updated_at: str = ""


class DiscoverySearchRequest(BaseModel):
    query: str = Field(default="", max_length=500)
    articles: List[DiscoveryArticle] = Field(default_factory=list)
    sort: Literal["relevance", "recent", "title"] = "relevance"
    limit: int = Field(default=20, ge=1, le=100)


class DiscoverySearchResultItem(BaseModel):
    article_id: str
    title: str
    score: int = Field(ge=0)
    reasons: List[str]
    matched_tokens: List[str]


class DiscoverySearchResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-discovery/1.0", alias="schema")
    version: str = "5.4.0"
    query: str
    sort: str
    count: int
    results: List[DiscoverySearchResultItem]
    generated_at: str
    personal_data_stored: bool = False


def normalize(text: str) -> str:
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9._+\-\s]+", " ", (text or "").lower())).strip()


def tokens(query: str, expand: bool = True) -> List[str]:
    base = list(dict.fromkeys(token for token in normalize(query).split() if len(token) > 1))
    if not expand:
        return base
    expanded = list(base)
    for token in base:
        expanded.extend(SYNONYMS.get(token, []))
    return list(dict.fromkeys(normalize(item) for item in expanded if normalize(item)))


def score_article(article: DiscoveryArticle, query: str) -> DiscoverySearchResultItem:
    query = normalize(query)
    title = normalize(article.title)
    summary = normalize(article.summary)
    content = normalize(article.content)
    terms = normalize(" ".join(article.product + article.component + article.article_type))
    versions = normalize(" ".join(article.version))
    if not query:
        return DiscoverySearchResultItem(article_id=article.article_id, title=article.title, score=1, reasons=["catalog"], matched_tokens=[])
    original = tokens(query, False)
    expanded = tokens(query, True)
    score = 0
    reasons: List[str] = []
    matched: List[str] = []
    if query in title:
        score += 140
        reasons.append("exact title phrase")
    if query in summary:
        score += 90
        reasons.append("exact summary phrase")
    if query in terms or query in versions:
        score += 70
        reasons.append("exact product or version context")
    for token in expanded:
        token_score = 0
        if token in title:
            token_score += 34
        if token in summary:
            token_score += 22
        if token in terms:
            token_score += 26
        if token in versions:
            token_score += 30
        if token in content:
            token_score += 7
        if token_score:
            score += token_score
            matched.append(token)
    original_matches = sum(1 for token in original if token in matched)
    minimum = max(1, (len(original) + 1) // 2)
    if original_matches < minimum:
        score = 0
    elif len(original) > 1 and original_matches == len(original):
        score += 45
        reasons.append("all query terms")
    return DiscoverySearchResultItem(article_id=article.article_id, title=article.title, score=score, reasons=list(dict.fromkeys(reasons)), matched_tokens=list(dict.fromkeys(matched)))


def search_support_articles(payload: DiscoverySearchRequest) -> DiscoverySearchResult:
    indexed = [(article, score_article(article, payload.query)) for article in payload.articles]
    if payload.query:
        indexed = [row for row in indexed if row[1].score > 0]
    if payload.sort == "title":
        indexed.sort(key=lambda row: row[0].title.lower())
    elif payload.sort == "recent":
        indexed.sort(key=lambda row: row[0].updated_at, reverse=True)
    else:
        indexed.sort(key=lambda row: (-row[1].score, row[0].title.lower()))
    results = [row[1] for row in indexed[: payload.limit]]
    return DiscoverySearchResult(query=normalize(payload.query), sort=payload.sort, count=len(results), results=results, generated_at=datetime.now(timezone.utc).isoformat())
