from __future__ import annotations

from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field

SignalState = Literal[
    "insufficient_evidence",
    "monitor",
    "emerging",
    "elevated",
    "critical_review",
]


class ProductSignalEvidence(BaseModel):
    product: str = "unassigned"
    feature_requests: int = Field(default=0, ge=0)
    public_votes: int = Field(default=0, ge=0)
    article_feedback_total: int = Field(default=0, ge=0)
    article_feedback_negative: int = Field(default=0, ge=0)
    unresolved_searches: int = Field(default=0, ge=0)
    low_confidence_searches: int = Field(default=0, ge=0)
    failed_resolution_paths: int = Field(default=0, ge=0)
    documentation_gaps: int = Field(default=0, ge=0)
    high_priority_documentation_gaps: int = Field(default=0, ge=0)
    active_known_issues: int = Field(default=0, ge=0)
    critical_known_issues: int = Field(default=0, ge=0)
    support_relationships: int = Field(default=0, ge=0)
    minimum_evidence: int = Field(default=3, ge=1, le=1000)
    elevated_score: int = Field(default=45, ge=0, le=100)
    critical_score: int = Field(default=70, ge=0, le=100)


class ProductSignalAssessment(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-feedback-product-signals/1.0", alias="schema")
    version: str = "5.6.0"
    product: str
    signal_score: int = Field(ge=0, le=100)
    signal_state: SignalState
    evidence_count: int = Field(ge=0)
    evidence_dimensions: Dict[str, int]
    recommended_actions: List[str]
    administrator_only: bool = True
    personal_identifiers_exposed: bool = False
    raw_search_text_exposed: bool = False
    private_case_content_exposed: bool = False
    human_review_required: bool = True
    automatic_roadmap_changes: bool = False
    automatic_issue_declaration: bool = False
    automatic_publication: bool = False


class ProductSignalPortfolioEvidence(BaseModel):
    records: List[ProductSignalEvidence] = Field(default_factory=list)


class ProductSignalPortfolioSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-feedback-product-signal-portfolio/1.0", alias="schema")
    version: str = "5.6.0"
    products: int = Field(ge=0)
    evidence_count: int = Field(ge=0)
    feature_requests: int = Field(ge=0)
    public_votes: int = Field(ge=0)
    negative_feedback: int = Field(ge=0)
    unresolved_searches: int = Field(ge=0)
    documentation_gaps: int = Field(ge=0)
    active_known_issues: int = Field(ge=0)
    state_counts: Dict[str, int]
    highest_priority_products: List[ProductSignalAssessment]
    generated_at: str
    human_review_required: bool = True
    automatic_roadmap_changes: bool = False


class ProductSignalClusterEvidence(BaseModel):
    signal_type: Literal[
        "negative_article_feedback",
        "unresolved_support_search",
        "documentation_gap",
        "known_issue_demand",
        "feature_request_demand",
    ]
    evidence_count: int = Field(default=0, ge=0)
    severity: int = Field(default=0, ge=0, le=5)
    recency_days: int = Field(default=0, ge=0, le=3650)
    product_context_complete: bool = True
    linked_record_available: bool = False


class ProductSignalClusterPriority(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-feedback-product-signal-cluster/1.0", alias="schema")
    version: str = "5.6.0"
    signal_type: str
    priority_score: int = Field(ge=0, le=100)
    priority_state: Literal["monitor", "review", "high_review", "urgent_review"]
    recommended_action: str
    evidence_count: int = Field(ge=0)
    human_review_required: bool = True
    automatic_record_creation: bool = False


def score_product_signal(evidence: ProductSignalEvidence) -> ProductSignalAssessment:
    negative = evidence.article_feedback_negative
    unresolved = evidence.unresolved_searches
    gaps = evidence.documentation_gaps
    high_gaps = evidence.high_priority_documentation_gaps
    issues = evidence.active_known_issues
    critical = evidence.critical_known_issues
    requests = evidence.feature_requests
    votes = evidence.public_votes
    relationships = evidence.support_relationships
    failed_paths = evidence.failed_resolution_paths

    score = 0.0
    score += min(24, negative * 8)
    score += min(24, unresolved * 7)
    score += min(20, (gaps + high_gaps) * 10)
    score += min(20, (issues + critical) * 9)
    score += min(8, requests * 4)
    score += min(6, votes)
    score += min(8, relationships * 2)
    score += min(10, failed_paths * 5)
    score_value = max(0, min(100, round(score)))

    evidence_count = negative + unresolved + gaps + issues + requests + relationships + failed_paths
    if evidence_count < evidence.minimum_evidence:
        state: SignalState = "insufficient_evidence"
    elif score_value >= evidence.critical_score:
        state = "critical_review"
    elif score_value >= evidence.elevated_score:
        state = "elevated"
    elif score_value >= 20:
        state = "emerging"
    else:
        state = "monitor"

    actions: List[str] = []
    if unresolved or gaps or negative:
        actions.append("review_documentation_and_search_experience")
    if issues or failed_paths:
        actions.append("review_known_issue_and_resolution_path")
    if requests or votes or relationships:
        actions.append("review_product_opportunity_evidence")
    if not actions:
        actions.append("continue_monitoring")

    return ProductSignalAssessment(
        product=evidence.product,
        signal_score=score_value,
        signal_state=state,
        evidence_count=evidence_count,
        evidence_dimensions={
            "feature_requests": requests,
            "public_votes": votes,
            "negative_article_feedback": negative,
            "unresolved_searches": unresolved,
            "documentation_gaps": gaps,
            "active_known_issues": issues,
            "support_relationships": relationships,
            "failed_resolution_paths": failed_paths,
        },
        recommended_actions=list(dict.fromkeys(actions)),
    )


def summarize_product_signal_portfolio(evidence: ProductSignalPortfolioEvidence) -> ProductSignalPortfolioSummary:
    assessments = [score_product_signal(record) for record in evidence.records]
    assessments.sort(key=lambda item: (-item.signal_score, item.product))
    state_counts: Dict[str, int] = {}
    for item in assessments:
        state_counts[item.signal_state] = state_counts.get(item.signal_state, 0) + 1

    return ProductSignalPortfolioSummary(
        products=len(evidence.records),
        evidence_count=sum(item.evidence_count for item in assessments),
        feature_requests=sum(item.feature_requests for item in evidence.records),
        public_votes=sum(item.public_votes for item in evidence.records),
        negative_feedback=sum(item.article_feedback_negative for item in evidence.records),
        unresolved_searches=sum(item.unresolved_searches for item in evidence.records),
        documentation_gaps=sum(item.documentation_gaps for item in evidence.records),
        active_known_issues=sum(item.active_known_issues for item in evidence.records),
        state_counts=state_counts,
        highest_priority_products=assessments[:10],
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def prioritize_product_signal_cluster(evidence: ProductSignalClusterEvidence) -> ProductSignalClusterPriority:
    type_weight = {
        "negative_article_feedback": 8,
        "unresolved_support_search": 9,
        "documentation_gap": 10,
        "known_issue_demand": 10,
        "feature_request_demand": 5,
    }[evidence.signal_type]
    recency_bonus = 12 if evidence.recency_days <= 7 else 8 if evidence.recency_days <= 30 else 4 if evidence.recency_days <= 90 else 0
    score = min(100, evidence.evidence_count * type_weight + evidence.severity * 8 + recency_bonus)
    if not evidence.product_context_complete:
        score = max(0, score - 8)

    if score >= 75:
        state = "urgent_review"
    elif score >= 50:
        state = "high_review"
    elif score >= 25:
        state = "review"
    else:
        state = "monitor"

    action_map = {
        "negative_article_feedback": "review_support_article_quality",
        "unresolved_support_search": "review_search_and_documentation_gap",
        "documentation_gap": "review_documentation_gap",
        "known_issue_demand": "review_known_issue_and_resolution_path",
        "feature_request_demand": "review_product_opportunity_evidence",
    }
    action = action_map[evidence.signal_type]
    if not evidence.linked_record_available and state in {"high_review", "urgent_review"}:
        action += "_and_link_review_record"

    return ProductSignalClusterPriority(
        signal_type=evidence.signal_type,
        priority_score=score,
        priority_state=state,
        recommended_action=action,
        evidence_count=evidence.evidence_count,
    )
