from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field

EffectivenessState = Literal["insufficient_evidence", "effective", "healthy", "watch", "intervention"]
TrendDirection = Literal["improving", "stable", "declining"]


class DocumentationEffectivenessEvidence(BaseModel):
    product: str = "all"
    searches: int = Field(default=0, ge=0)
    matched_searches: int = Field(default=0, ge=0)
    viewed_searches: int = Field(default=0, ge=0)
    feedback_responses: int = Field(default=0, ge=0)
    helpful_responses: int = Field(default=0, ge=0)
    published_articles: int = Field(default=0, ge=0)
    average_integrity_score: float = Field(default=100, ge=0, le=100)
    fresh_articles: int = Field(default=0, ge=0)
    known_issues: int = Field(default=0, ge=0)
    known_issues_with_guidance: int = Field(default=0, ge=0)
    releases: int = Field(default=0, ge=0)
    releases_with_documentation: int = Field(default=0, ge=0)
    documentation_gaps: int = Field(default=0, ge=0)
    resolved_documentation_gaps: int = Field(default=0, ge=0)
    minimum_evidence: int = Field(default=5, ge=1, le=1000)
    effective_threshold: float = Field(default=80, ge=0, le=100)
    healthy_threshold: float = Field(default=65, ge=0, le=100)
    watch_threshold: float = Field(default=45, ge=0, le=100)


class EffectivenessDimension(BaseModel):
    key: str
    score: float = Field(ge=0, le=100)
    weight: float = Field(ge=0, le=1)
    contribution: float = Field(ge=0, le=100)


class DocumentationEffectivenessAssessment(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-analytics-documentation-effectiveness/1.0", alias="schema")
    version: str = "5.7.0"
    product: str
    score: float = Field(ge=0, le=100)
    state: EffectivenessState
    evidence_count: int = Field(ge=0)
    dimensions: List[EffectivenessDimension]
    recommendations: List[str]
    administrator_only: bool = True
    personal_identifiers_exposed: bool = False
    raw_search_text_exposed: bool = False
    private_case_content_exposed: bool = False
    human_review_required: bool = True
    automatic_publication: bool = False
    automatic_issue_resolution: bool = False
    automatic_roadmap_changes: bool = False


class DocumentationEffectivenessPortfolioEvidence(BaseModel):
    records: List[DocumentationEffectivenessEvidence] = Field(default_factory=list)


class DocumentationEffectivenessPortfolioSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-analytics-portfolio/1.0", alias="schema")
    version: str = "5.7.0"
    products: int = Field(ge=0)
    state_counts: Dict[str, int]
    average_score: float = Field(ge=0, le=100)
    lowest_effectiveness_products: List[DocumentationEffectivenessAssessment]
    generated_at: str
    human_review_required: bool = True


class DocumentationEffectivenessTrendEvidence(BaseModel):
    product: str = "all"
    previous_score: float = Field(ge=0, le=100)
    current_score: float = Field(ge=0, le=100)
    previous_search_success: float = Field(default=0, ge=0, le=100)
    current_search_success: float = Field(default=0, ge=0, le=100)
    previous_helpfulness: float = Field(default=0, ge=0, le=100)
    current_helpfulness: float = Field(default=0, ge=0, le=100)


class DocumentationEffectivenessTrend(BaseModel):
    version: str = "5.7.0"
    product: str
    direction: TrendDirection
    score_delta: float
    search_success_delta: float
    helpfulness_delta: float
    alerts: List[str]


class AnalyticsReportIntegrityEvidence(BaseModel):
    records: List[dict] = Field(default_factory=list)
    expected_checksum: str = ""


class AnalyticsReportIntegrityResult(BaseModel):
    version: str = "5.7.0"
    record_count: int = Field(ge=0)
    checksum: str
    matches_expected: bool


def _percent(numerator: int, denominator: int, empty: float = 100.0) -> float:
    if denominator <= 0:
        return round(empty, 1)
    return round(numerator / denominator * 100, 1)


def evaluate_documentation_effectiveness(evidence: DocumentationEffectivenessEvidence) -> DocumentationEffectivenessAssessment:
    scores = {
        "search_success": _percent(evidence.matched_searches, evidence.searches),
        "search_engagement": _percent(evidence.viewed_searches, evidence.searches),
        "article_helpfulness": _percent(evidence.helpful_responses, evidence.feedback_responses),
        "publication_integrity": round(evidence.average_integrity_score, 1),
        "content_freshness": _percent(evidence.fresh_articles, evidence.published_articles),
        "known_issue_coverage": _percent(evidence.known_issues_with_guidance, evidence.known_issues),
        "release_coverage": _percent(evidence.releases_with_documentation, evidence.releases),
        "gap_resolution": _percent(evidence.resolved_documentation_gaps, evidence.documentation_gaps),
    }
    weights = {
        "search_success": 0.20,
        "search_engagement": 0.10,
        "article_helpfulness": 0.20,
        "publication_integrity": 0.15,
        "content_freshness": 0.10,
        "known_issue_coverage": 0.10,
        "release_coverage": 0.10,
        "gap_resolution": 0.05,
    }
    dimensions: List[EffectivenessDimension] = []
    total = 0.0
    for key, weight in weights.items():
        contribution = round(scores[key] * weight, 2)
        total += contribution
        dimensions.append(EffectivenessDimension(key=key, score=scores[key], weight=weight, contribution=contribution))
    total = round(max(0.0, min(100.0, total)), 1)
    evidence_count = evidence.searches + evidence.feedback_responses + evidence.published_articles + evidence.known_issues + evidence.releases
    if evidence_count < evidence.minimum_evidence:
        state: EffectivenessState = "insufficient_evidence"
    elif total >= evidence.effective_threshold:
        state = "effective"
    elif total >= evidence.healthy_threshold:
        state = "healthy"
    elif total >= evidence.watch_threshold:
        state = "watch"
    else:
        state = "intervention"
    recommendations: List[str] = []
    if scores["search_success"] < 65:
        recommendations.append("improve_search_relevance_and_no_results_recovery")
    if scores["article_helpfulness"] < 70:
        recommendations.append("review_low_helpfulness_articles")
    if scores["content_freshness"] < 80:
        recommendations.append("reverify_stale_support_articles")
    if scores["known_issue_coverage"] < 80:
        recommendations.append("link_known_issues_to_workarounds_and_articles")
    if scores["release_coverage"] < 80:
        recommendations.append("complete_release_documentation_relationships")
    if scores["gap_resolution"] < 50 and evidence.documentation_gaps:
        recommendations.append("prioritize_open_documentation_gaps")
    if not recommendations:
        recommendations.append("continue_monitoring_documentation_effectiveness")
    return DocumentationEffectivenessAssessment(
        product=evidence.product,
        score=total,
        state=state,
        evidence_count=evidence_count,
        dimensions=dimensions,
        recommendations=recommendations,
    )


def summarize_documentation_effectiveness_portfolio(evidence: DocumentationEffectivenessPortfolioEvidence) -> DocumentationEffectivenessPortfolioSummary:
    assessments = [evaluate_documentation_effectiveness(record) for record in evidence.records]
    assessments.sort(key=lambda record: (record.score, record.product))
    state_counts: Dict[str, int] = {}
    for record in assessments:
        state_counts[record.state] = state_counts.get(record.state, 0) + 1
    average = round(sum(record.score for record in assessments) / len(assessments), 1) if assessments else 0.0
    return DocumentationEffectivenessPortfolioSummary(
        products=len(assessments),
        state_counts=state_counts,
        average_score=average,
        lowest_effectiveness_products=assessments[:10],
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def compare_documentation_effectiveness(evidence: DocumentationEffectivenessTrendEvidence) -> DocumentationEffectivenessTrend:
    score_delta = round(evidence.current_score - evidence.previous_score, 1)
    search_delta = round(evidence.current_search_success - evidence.previous_search_success, 1)
    helpfulness_delta = round(evidence.current_helpfulness - evidence.previous_helpfulness, 1)
    direction: TrendDirection = "improving" if score_delta >= 3 else "declining" if score_delta <= -3 else "stable"
    alerts: List[str] = []
    if search_delta <= -5:
        alerts.append("Search success declined materially.")
    if helpfulness_delta <= -5:
        alerts.append("Article helpfulness declined materially.")
    if score_delta <= -3:
        alerts.append("Documentation effectiveness is declining.")
    return DocumentationEffectivenessTrend(
        product=evidence.product,
        direction=direction,
        score_delta=score_delta,
        search_success_delta=search_delta,
        helpfulness_delta=helpfulness_delta,
        alerts=alerts,
    )


def verify_support_analytics_report(evidence: AnalyticsReportIntegrityEvidence) -> AnalyticsReportIntegrityResult:
    ordered = sorted(evidence.records, key=lambda row: json.dumps(row, sort_keys=True, separators=(",", ":")))
    payload = json.dumps(ordered, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    checksum = hashlib.sha256(payload.encode("utf-8")).hexdigest()
    return AnalyticsReportIntegrityResult(record_count=len(ordered), checksum=checksum, matches_expected=bool(evidence.expected_checksum) and checksum == evidence.expected_checksum)
