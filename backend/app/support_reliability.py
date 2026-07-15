from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field

ReliabilityState = Literal["healthy", "watch", "attention", "critical"]
TrendDirection = Literal["improving", "stable", "declining"]


class ProductReliabilityEvidence(BaseModel):
    resolution_success_percent: float = Field(default=0, ge=0, le=100)
    documentation_helpfulness_percent: float = Field(default=0, ge=0, le=100)
    known_issue_health_percent: float = Field(default=0, ge=0, le=100)
    release_readiness_percent: float = Field(default=0, ge=0, le=100)
    content_readiness_percent: float = Field(default=0, ge=0, le=100)
    repository_health_percent: float = Field(default=0, ge=0, le=100)
    governance_health_percent: float = Field(default=0, ge=0, le=100)
    unresolved_searches: int = Field(default=0, ge=0)
    critical_open_issues: int = Field(default=0, ge=0)
    high_priority_gaps: int = Field(default=0, ge=0)
    overdue_reviews: int = Field(default=0, ge=0)


class ReliabilityDimension(BaseModel):
    key: str
    label: str
    score: float = Field(ge=0, le=100)
    weight: float = Field(ge=0, le=1)
    contribution: float = Field(ge=0, le=100)


class ProductReliabilityScore(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-product-reliability-score/1.0", alias="schema")
    version: str = "4.5.0"
    score: float = Field(ge=0, le=100)
    state: ReliabilityState
    dimensions: List[ReliabilityDimension]
    blockers: List[str]
    signals: List[str]
    generated_at: str
    human_review_required: bool = True
    automatic_roadmap_change: bool = False


class ReliabilityTrendEvidence(BaseModel):
    current_score: float = Field(default=0, ge=0, le=100)
    previous_score: float = Field(default=0, ge=0, le=100)
    current_unresolved_searches: int = Field(default=0, ge=0)
    previous_unresolved_searches: int = Field(default=0, ge=0)
    current_active_issues: int = Field(default=0, ge=0)
    previous_active_issues: int = Field(default=0, ge=0)
    current_helpfulness_percent: float = Field(default=0, ge=0, le=100)
    previous_helpfulness_percent: float = Field(default=0, ge=0, le=100)


class ReliabilityTrendSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-product-reliability-trend/1.0", alias="schema")
    version: str = "4.5.0"
    direction: TrendDirection
    score_delta: float
    unresolved_delta: int
    active_issue_delta: int
    helpfulness_delta: float
    alerts: List[str]
    generated_at: str
    human_review_required: bool = True


class UnresolvedClusterEvidence(BaseModel):
    searches: int = Field(default=0, ge=0)
    no_match_searches: int = Field(default=0, ge=0)
    low_confidence_searches: int = Field(default=0, ge=0)
    private_handoffs: int = Field(default=0, ge=0)
    negative_feedback: int = Field(default=0, ge=0)
    related_cases: int = Field(default=0, ge=0)
    recency_days: int = Field(default=30, ge=0)
    product_count: int = Field(default=1, ge=0)


class UnresolvedClusterPriority(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-unresolved-query-cluster-priority/1.0", alias="schema")
    version: str = "4.5.0"
    score: float = Field(ge=0, le=100)
    priority: Literal["low", "medium", "high", "critical"]
    signals: List[str]
    human_review_required: bool = True


class ReliabilityReportIntegrityEvidence(BaseModel):
    records: List[Dict] = Field(default_factory=list)
    expected_checksum: str = ""


class ReliabilityReportIntegrityResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-reliability-report-integrity/1.0", alias="schema")
    version: str = "4.5.0"
    record_count: int = Field(ge=0)
    checksum: str
    matches_expected: bool
    deterministic_ordering: bool = True


WEIGHTS = {
    "resolution_success": 0.25,
    "documentation_helpfulness": 0.20,
    "known_issue_health": 0.15,
    "release_readiness": 0.15,
    "content_readiness": 0.10,
    "repository_health": 0.10,
    "governance_health": 0.05,
}

LABELS = {
    "resolution_success": "Resolution success",
    "documentation_helpfulness": "Documentation usefulness",
    "known_issue_health": "Known-issue health",
    "release_readiness": "Release readiness",
    "content_readiness": "Content readiness",
    "repository_health": "Repository health",
    "governance_health": "Editorial governance",
}


def score_product_reliability(evidence: ProductReliabilityEvidence) -> ProductReliabilityScore:
    values = {
        "resolution_success": evidence.resolution_success_percent,
        "documentation_helpfulness": evidence.documentation_helpfulness_percent,
        "known_issue_health": evidence.known_issue_health_percent,
        "release_readiness": evidence.release_readiness_percent,
        "content_readiness": evidence.content_readiness_percent,
        "repository_health": evidence.repository_health_percent,
        "governance_health": evidence.governance_health_percent,
    }
    dimensions: List[ReliabilityDimension] = []
    total = 0.0
    for key, weight in WEIGHTS.items():
        value = round(float(values[key]), 2)
        contribution = round(value * weight, 2)
        total += contribution
        dimensions.append(
            ReliabilityDimension(
                key=key,
                label=LABELS[key],
                score=value,
                weight=weight,
                contribution=contribution,
            )
        )

    blockers: List[str] = []
    signals: List[str] = []
    if evidence.critical_open_issues:
        blockers.append("critical_open_issues")
        total -= min(30, evidence.critical_open_issues * 12)
    if evidence.high_priority_gaps:
        blockers.append("high_priority_documentation_gaps")
        total -= min(15, evidence.high_priority_gaps * 3)
    if evidence.overdue_reviews:
        blockers.append("overdue_editorial_reviews")
        total -= min(10, evidence.overdue_reviews * 2)
    if evidence.unresolved_searches:
        signals.append(f"{evidence.unresolved_searches} unresolved searches require review.")
    if evidence.documentation_helpfulness_percent < 60:
        signals.append("Documentation usefulness is below the review threshold.")
    if evidence.repository_health_percent < 70:
        signals.append("Repository drift or link health needs attention.")

    total = round(max(0.0, min(100.0, total)), 1)
    if blockers and total < 45:
        state: ReliabilityState = "critical"
    elif total < 60:
        state = "attention"
    elif total < 80:
        state = "watch"
    else:
        state = "healthy"
    return ProductReliabilityScore(
        score=total,
        state=state,
        dimensions=dimensions,
        blockers=blockers,
        signals=signals,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def summarize_reliability_trend(evidence: ReliabilityTrendEvidence) -> ReliabilityTrendSummary:
    score_delta = round(evidence.current_score - evidence.previous_score, 1)
    unresolved_delta = evidence.current_unresolved_searches - evidence.previous_unresolved_searches
    issue_delta = evidence.current_active_issues - evidence.previous_active_issues
    helpfulness_delta = round(evidence.current_helpfulness_percent - evidence.previous_helpfulness_percent, 1)
    alerts: List[str] = []
    if unresolved_delta > 0:
        alerts.append("Unresolved search volume increased.")
    if issue_delta > 0:
        alerts.append("Active known issues increased.")
    if helpfulness_delta < -5:
        alerts.append("Documentation usefulness declined materially.")
    if score_delta >= 3:
        direction: TrendDirection = "improving"
    elif score_delta <= -3:
        direction = "declining"
    else:
        direction = "stable"
    return ReliabilityTrendSummary(
        direction=direction,
        score_delta=score_delta,
        unresolved_delta=unresolved_delta,
        active_issue_delta=issue_delta,
        helpfulness_delta=helpfulness_delta,
        alerts=alerts,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def prioritize_unresolved_cluster(evidence: UnresolvedClusterEvidence) -> UnresolvedClusterPriority:
    searches = max(1, evidence.searches)
    no_match_ratio = min(1.0, evidence.no_match_searches / searches)
    low_ratio = min(1.0, evidence.low_confidence_searches / searches)
    score = min(35.0, searches * 3.0)
    score += no_match_ratio * 20.0
    score += low_ratio * 10.0
    score += min(15.0, evidence.private_handoffs * 4.0)
    score += min(10.0, evidence.negative_feedback * 2.5)
    score += min(10.0, evidence.related_cases * 2.5)
    if evidence.recency_days <= 7:
        score += 8.0
    elif evidence.recency_days <= 30:
        score += 4.0
    score += min(5.0, max(0, evidence.product_count - 1) * 1.5)
    score = round(max(0.0, min(100.0, score)), 1)
    if score >= 80:
        priority: Literal["low", "medium", "high", "critical"] = "critical"
    elif score >= 60:
        priority = "high"
    elif score >= 35:
        priority = "medium"
    else:
        priority = "low"
    signals: List[str] = []
    if no_match_ratio >= 0.5:
        signals.append("Most searches in this cluster return no match.")
    if evidence.private_handoffs:
        signals.append("The cluster is producing private-support handoffs.")
    if evidence.related_cases:
        signals.append("Private case relationships confirm operational demand.")
    if evidence.product_count > 1:
        signals.append("The cluster spans multiple products.")
    return UnresolvedClusterPriority(score=score, priority=priority, signals=signals)


def verify_reliability_report(evidence: ReliabilityReportIntegrityEvidence) -> ReliabilityReportIntegrityResult:
    ordered = sorted(evidence.records, key=lambda row: json.dumps(row, sort_keys=True, separators=(",", ":")))
    payload = json.dumps(ordered, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    checksum = hashlib.sha256(payload.encode("utf-8")).hexdigest()
    return ReliabilityReportIntegrityResult(
        record_count=len(ordered),
        checksum=checksum,
        matches_expected=bool(evidence.expected_checksum) and checksum == evidence.expected_checksum,
    )
