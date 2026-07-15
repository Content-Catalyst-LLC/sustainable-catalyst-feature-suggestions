from __future__ import annotations

from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field

WorkflowState = Literal[
    "draft",
    "submitted",
    "in_review",
    "changes_requested",
    "approved",
    "scheduled",
    "published",
    "expired",
    "archived",
]

ALLOWED_TRANSITIONS: Dict[str, List[str]] = {
    "draft": ["submitted", "archived"],
    "submitted": ["in_review", "changes_requested", "draft"],
    "in_review": ["approved", "changes_requested", "draft"],
    "changes_requested": ["submitted", "draft", "archived"],
    "approved": ["scheduled", "published", "changes_requested", "archived"],
    "scheduled": ["published", "approved", "archived"],
    "published": ["in_review", "expired", "archived"],
    "expired": ["in_review", "archived"],
    "archived": ["draft"],
}


class EditorialTransitionEvidence(BaseModel):
    current_state: WorkflowState
    target_state: WorkflowState
    author_assigned: bool = False
    reviewer_assigned: bool = False
    approver_assigned: bool = False
    approver_is_author: bool = False
    require_separate_approver: bool = True
    require_change_summary: bool = True
    change_summary_present: bool = False
    standards_score: int = Field(default=0, ge=0, le=100)
    minimum_standards_score: int = Field(default=80, ge=0, le=100)
    standards_blockers: List[str] = Field(default_factory=list)
    require_version_approval: bool = True
    assigned_version_ids: List[int] = Field(default_factory=list)
    approved_version_ids: List[int] = Field(default_factory=list)
    scheduled_at: str = ""


class EditorialTransitionDecision(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-editorial-transition-decision/1.0", alias="schema")
    version: str = "4.5.0"
    allowed: bool
    current_state: WorkflowState
    target_state: WorkflowState
    blockers: List[str]
    required_actions: List[str]
    human_review_required: bool = True
    automatic_approval: bool = False


class DocumentationStandardsEvidence(BaseModel):
    title_characters: int = Field(default=0, ge=0)
    content_characters: int = Field(default=0, ge=0)
    summary_characters: int = Field(default=0, ge=0)
    product_context: bool = False
    required_sections: List[str] = Field(default_factory=list)
    present_sections: List[str] = Field(default_factory=list)
    provenance_present: bool = False
    change_summary_present: bool = False
    require_product_context: bool = True
    require_change_summary: bool = True
    minimum_score: int = Field(default=80, ge=0, le=100)


class DocumentationStandardsScore(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-documentation-standards/1.0", alias="schema")
    version: str = "4.5.0"
    score: int = Field(ge=0, le=100)
    state: Literal["blocked", "review", "ready"]
    blockers: List[str]
    warnings: List[str]
    matched_sections: int = Field(ge=0)
    required_sections: int = Field(ge=0)
    human_review_required: bool = True


class EditorialQueueEvidence(BaseModel):
    state_counts: Dict[str, int] = Field(default_factory=dict)
    overdue_reviews: int = Field(default=0, ge=0)
    expiring_records: int = Field(default=0, ge=0)
    standards_blocked: int = Field(default=0, ge=0)


class EditorialGovernanceSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-editorial-governance-summary/1.0", alias="schema")
    version: str = "4.5.0"
    total: int = Field(ge=0)
    review_queue: int = Field(ge=0)
    approved_or_scheduled: int = Field(ge=0)
    overdue_reviews: int = Field(ge=0)
    expiring_records: int = Field(ge=0)
    standards_blocked: int = Field(ge=0)
    generated_at: str
    human_review_required: bool = True


def evaluate_editorial_transition(evidence: EditorialTransitionEvidence) -> EditorialTransitionDecision:
    blockers: List[str] = []
    actions: List[str] = []
    allowed_targets = ALLOWED_TRANSITIONS.get(evidence.current_state, [])
    if evidence.target_state not in allowed_targets:
        blockers.append("transition_not_allowed")

    governed_states = {"submitted", "in_review", "approved", "scheduled", "published"}
    approval_states = {"approved", "scheduled", "published"}

    if evidence.target_state in governed_states and not evidence.author_assigned:
        blockers.append("author_required")
        actions.append("assign_author")
    if evidence.target_state in {"in_review", *approval_states} and not evidence.reviewer_assigned:
        blockers.append("reviewer_required")
        actions.append("assign_reviewer")
    if evidence.target_state in approval_states:
        if evidence.require_separate_approver and not evidence.approver_assigned:
            blockers.append("approver_required")
            actions.append("assign_approver")
        if evidence.require_separate_approver and evidence.approver_is_author:
            blockers.append("approver_must_differ_from_author")
            actions.append("separate_author_and_approver")
        if evidence.require_change_summary and not evidence.change_summary_present:
            blockers.append("change_summary_required")
            actions.append("add_change_summary")
        if evidence.standards_score < evidence.minimum_standards_score:
            blockers.append("standards_score_below_minimum")
            actions.append("resolve_documentation_standards")
        if evidence.standards_blockers:
            blockers.append("standards_blockers_present")
            actions.extend(f"resolve:{item}" for item in evidence.standards_blockers)
        if evidence.require_version_approval and evidence.assigned_version_ids:
            assigned = set(item for item in evidence.assigned_version_ids if item > 0)
            approved = set(item for item in evidence.approved_version_ids if item > 0)
            if assigned and not assigned.intersection(approved):
                blockers.append("version_approval_required")
                actions.append("approve_assigned_product_version")
    if evidence.target_state == "scheduled":
        try:
            scheduled = datetime.fromisoformat(evidence.scheduled_at.replace("Z", "+00:00"))
            if scheduled.tzinfo is None:
                scheduled = scheduled.replace(tzinfo=timezone.utc)
            if scheduled <= datetime.now(timezone.utc):
                blockers.append("schedule_must_be_future")
                actions.append("set_future_schedule")
        except ValueError:
            blockers.append("valid_schedule_required")
            actions.append("set_future_schedule")

    return EditorialTransitionDecision(
        allowed=not blockers,
        current_state=evidence.current_state,
        target_state=evidence.target_state,
        blockers=list(dict.fromkeys(blockers)),
        required_actions=list(dict.fromkeys(actions)),
    )


def score_documentation_standards(evidence: DocumentationStandardsEvidence) -> DocumentationStandardsScore:
    score = 0
    blockers: List[str] = []
    warnings: List[str] = []

    if evidence.title_characters >= 6:
        score += 10
    else:
        blockers.append("title_too_short")

    if evidence.content_characters >= 120:
        score += 20
    else:
        score += min(10, evidence.content_characters // 12)
        blockers.append("content_too_short")

    if evidence.summary_characters >= 20:
        score += 10
    else:
        warnings.append("summary_missing")

    if evidence.product_context:
        score += 15
    elif evidence.require_product_context:
        blockers.append("product_context_missing")

    normalized_present = {section.strip().lower() for section in evidence.present_sections if section.strip()}
    matched = sum(1 for section in evidence.required_sections if section.strip().lower() in normalized_present)
    required_count = len(evidence.required_sections)
    ratio = matched / required_count if required_count else 1.0
    score += round(ratio * 25)
    if required_count and matched < max(1, (required_count + 1) // 2):
        blockers.append("required_sections_missing")
    elif matched < required_count:
        warnings.append("some_required_sections_missing")

    if evidence.provenance_present:
        score += 10
    else:
        warnings.append("provenance_unverified")

    if evidence.change_summary_present or not evidence.require_change_summary:
        score += 10
    else:
        blockers.append("change_summary_missing")

    score = max(0, min(100, int(score)))
    state: Literal["blocked", "review", "ready"]
    if blockers:
        state = "blocked"
    elif score >= evidence.minimum_score:
        state = "ready"
    else:
        state = "review"

    return DocumentationStandardsScore(
        score=score,
        state=state,
        blockers=list(dict.fromkeys(blockers)),
        warnings=list(dict.fromkeys(warnings)),
        matched_sections=matched,
        required_sections=required_count,
    )


def summarize_editorial_queue(evidence: EditorialQueueEvidence) -> EditorialGovernanceSummary:
    counts = {key: max(0, int(value)) for key, value in evidence.state_counts.items()}
    total = sum(counts.values())
    review_queue = sum(counts.get(key, 0) for key in ("submitted", "in_review", "changes_requested"))
    approved_or_scheduled = counts.get("approved", 0) + counts.get("scheduled", 0)
    return EditorialGovernanceSummary(
        total=total,
        review_queue=review_queue,
        approved_or_scheduled=approved_or_scheduled,
        overdue_reviews=evidence.overdue_reviews,
        expiring_records=evidence.expiring_records,
        standards_blocked=evidence.standards_blocked,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
