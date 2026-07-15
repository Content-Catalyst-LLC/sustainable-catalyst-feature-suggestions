from typing import List, Literal
from pydantic import BaseModel, ConfigDict, Field


class ProductSupportEvidence(BaseModel):
    support_articles: int = Field(default=0, ge=0)
    active_known_issues: int = Field(default=0, ge=0)
    critical_known_issues: int = Field(default=0, ge=0)
    unresolved_searches: int = Field(default=0, ge=0)
    public_ideas: int = Field(default=0, ge=0)
    open_surveys: int = Field(default=0, ge=0)
    release_records: int = Field(default=0, ge=0)


class ProductSupportOverview(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-product-support-overview/1.0", alias="schema")
    version: str = "4.3.0"
    support_state: Literal["stable", "attention", "incident"]
    public_resolution_coverage: int = Field(ge=0, le=100)
    signals: List[str]
    recommended_pathway: Literal[
        "guided_resolution",
        "known_issues",
        "knowledge_base",
        "release_intelligence",
        "public_feedback",
    ]
    human_review_required: bool = True
    private_case_storage: bool = False


class ReleaseReadinessEvidence(BaseModel):
    documentation_count: int = Field(default=0, ge=0)
    known_issue_count: int = Field(default=0, ge=0)
    unresolved_critical_issues: int = Field(default=0, ge=0)
    public_summary_present: bool = False
    support_note_present: bool = False
    release_date_present: bool = False
    changelog_present: bool = False
    product_context_present: bool = False


class ReleaseReadinessScore(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-release-readiness/1.0", alias="schema")
    version: str = "4.3.0"
    score: int = Field(ge=0, le=100)
    state: Literal["not_ready", "review", "ready"]
    missing: List[str]
    blockers: List[str]
    human_review_required: bool = True


def summarize_product_support(evidence: ProductSupportEvidence) -> ProductSupportOverview:
    signals: List[str] = []
    if evidence.critical_known_issues:
        support_state = "incident"
        recommended = "known_issues"
        signals.append(f"{evidence.critical_known_issues} critical known issue(s) are active.")
    elif evidence.active_known_issues or evidence.unresolved_searches >= 10:
        support_state = "attention"
        recommended = "guided_resolution"
        if evidence.active_known_issues:
            signals.append(f"{evidence.active_known_issues} known issue(s) are active.")
        if evidence.unresolved_searches:
            signals.append(f"{evidence.unresolved_searches} unresolved support search(es) need review.")
    else:
        support_state = "stable"
        recommended = "knowledge_base"
        signals.append("No elevated public support signal was detected.")

    coverage = 0
    coverage += min(45, evidence.support_articles * 3)
    coverage += min(20, evidence.release_records * 5)
    coverage += 15 if evidence.public_ideas else 0
    coverage += 10 if evidence.open_surveys else 0
    coverage += 10 if evidence.active_known_issues == 0 else 5
    coverage = max(0, min(100, coverage))

    if not evidence.support_articles and evidence.release_records:
        recommended = "release_intelligence"
        signals.append("Release records exist, but support documentation coverage is empty.")
    elif not evidence.support_articles and not evidence.release_records:
        recommended = "public_feedback"
        signals.append("Public documentation and release intelligence need editorial setup.")

    return ProductSupportOverview(
        support_state=support_state,
        public_resolution_coverage=coverage,
        signals=signals,
        recommended_pathway=recommended,
    )


def score_release_readiness(evidence: ReleaseReadinessEvidence) -> ReleaseReadinessScore:
    score = 0
    missing: List[str] = []
    blockers: List[str] = []

    boolean_signals = [
        (evidence.public_summary_present, "public_summary", 15),
        (evidence.support_note_present, "support_note", 15),
        (evidence.release_date_present, "release_date", 15),
        (evidence.changelog_present, "changelog", 10),
        (evidence.product_context_present, "product_context", 15),
    ]
    for present, label, points in boolean_signals:
        if present:
            score += points
        else:
            missing.append(label)

    score += min(20, evidence.documentation_count * 5)
    score += min(10, evidence.known_issue_count * 2)

    if evidence.unresolved_critical_issues:
        penalty = min(60, evidence.unresolved_critical_issues * 25)
        score -= penalty
        blockers.append("unresolved_critical_issues")

    score = max(0, min(100, score))
    state: Literal["not_ready", "review", "ready"]
    if blockers or score < 50:
        state = "not_ready"
    elif score < 80:
        state = "review"
    else:
        state = "ready"

    return ReleaseReadinessScore(score=score, state=state, missing=missing, blockers=blockers)
