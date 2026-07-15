from math import log2
from typing import Literal
from pydantic import BaseModel, Field


class DocumentationGapEvidence(BaseModel):
    search_count: int = Field(default=0, ge=0)
    no_match_count: int = Field(default=0, ge=0)
    low_confidence_count: int = Field(default=0, ge=0)
    negative_feedback_count: int = Field(default=0, ge=0)
    case_count: int = Field(default=0, ge=0)
    negative_feedback_weight: int = Field(default=7, ge=1, le=25)
    case_relationship_weight: int = Field(default=10, ge=1, le=25)


class DocumentationGapScore(BaseModel):
    schema_id: str = Field(default="scfs-documentation-gap-score/1.0", alias="schema")
    version: str = "4.5.0"
    score: float = Field(ge=0, le=100)
    priority: Literal["low", "moderate", "high", "critical"]
    signals: dict[str, float]
    human_review_required: bool = True


class SupportDemandEvidence(BaseModel):
    case_relationships: int = Field(default=0, ge=0)
    documentation_gap_count: int = Field(default=0, ge=0)
    unresolved_searches: int = Field(default=0, ge=0)
    documentation_gap_score_total: float = Field(default=0, ge=0)
    guided_result_views: int = Field(default=0, ge=0)


class SupportDemandScore(BaseModel):
    schema_id: str = Field(default="scfs-support-demand-score/1.0", alias="schema")
    version: str = "4.5.0"
    score: float = Field(ge=0, le=5)
    evidence_count: int = Field(ge=0, le=4)
    signals: dict[str, float | int]
    human_review_required: bool = True


def score_documentation_gap(evidence: DocumentationGapEvidence) -> DocumentationGapScore:
    searches = evidence.search_count
    pressure = min(40.0, log2(1 + searches) * 12.0)
    no_match_pressure = min(25.0, (evidence.no_match_count / searches) * 25.0) if searches else 0.0
    low_pressure = min(10.0, (evidence.low_confidence_count / searches) * 10.0) if searches else 0.0
    feedback_pressure = min(20.0, evidence.negative_feedback_count * evidence.negative_feedback_weight)
    case_pressure = min(25.0, evidence.case_count * evidence.case_relationship_weight)
    score = round(max(0.0, min(100.0, pressure + no_match_pressure + low_pressure + feedback_pressure + case_pressure)), 1)
    priority: Literal["low", "moderate", "high", "critical"]
    if score >= 75:
        priority = "critical"
    elif score >= 50:
        priority = "high"
    elif score >= 25:
        priority = "moderate"
    else:
        priority = "low"
    return DocumentationGapScore(
        score=score,
        priority=priority,
        signals={
            "search_pressure": round(pressure, 2),
            "no_match_pressure": round(no_match_pressure, 2),
            "low_confidence_pressure": round(low_pressure, 2),
            "negative_feedback_pressure": round(feedback_pressure, 2),
            "case_pressure": round(case_pressure, 2),
        },
    )


def score_support_demand(evidence: SupportDemandEvidence) -> SupportDemandScore:
    case_score = min(2.5, log2(1 + evidence.case_relationships) * 1.25)
    search_score = min(1.5, log2(1 + evidence.unresolved_searches) * 0.45)
    gap_score = min(0.75, evidence.documentation_gap_score_total / 250.0)
    view_score = min(0.75, log2(1 + evidence.guided_result_views) * 0.25)
    score = round(min(5.0, case_score + search_score + gap_score + view_score), 2)
    evidence_count = sum(
        1
        for value in (
            evidence.case_relationships,
            evidence.documentation_gap_count,
            evidence.unresolved_searches,
            evidence.guided_result_views,
        )
        if value > 0
    )
    return SupportDemandScore(
        score=score,
        evidence_count=evidence_count,
        signals={
            "case_relationships": evidence.case_relationships,
            "documentation_gap_count": evidence.documentation_gap_count,
            "unresolved_searches": evidence.unresolved_searches,
            "documentation_gap_score_total": round(evidence.documentation_gap_score_total, 2),
            "guided_result_views": evidence.guided_result_views,
            "case_score": round(case_score, 2),
            "search_score": round(search_score, 2),
            "gap_score": round(gap_score, 2),
            "view_score": round(view_score, 2),
        },
    )
