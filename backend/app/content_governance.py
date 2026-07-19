from __future__ import annotations

from datetime import date, datetime, timezone
from typing import Dict, List, Literal, Optional

from pydantic import BaseModel, ConfigDict, Field

QueueState = Literal[
    "publication_blocked",
    "overdue",
    "due_soon",
    "unassigned",
    "review_required",
    "ready_for_verification",
    "verified",
    "superseded",
]

VerificationState = Literal[
    "not_verified",
    "review_required",
    "verified",
    "verified_with_limitations",
    "superseded",
]


class ContentGovernanceEvidence(BaseModel):
    record_id: int = Field(default=0, ge=0)
    post_type: str = "sc_support_article"
    post_status: str = "draft"
    workflow_state: str = "draft"
    verification_state: VerificationState = "not_verified"
    owner_assigned: bool = False
    technical_owner_assigned: bool = False
    require_content_owner: bool = True
    require_technical_owner: bool = False
    integrity_state: str = "unscanned"
    integrity_score: int = Field(default=0, ge=0, le=100)
    minimum_integrity_score: int = Field(default=80, ge=0, le=100)
    integrity_stale: bool = False
    last_verified_at: str = ""
    next_review_at: str = ""
    review_warning_days: int = Field(default=30, ge=0, le=365)
    superseded_by_id: int = Field(default=0, ge=0)


class ContentGovernanceAssessment(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-content-governance/1.0", alias="schema")
    version: str = "5.5.0"
    record_id: int = Field(ge=0)
    queue_state: QueueState
    governance_score: int = Field(ge=0, le=100)
    blockers: List[str]
    required_actions: List[str]
    days_until_review: Optional[int] = None
    verification_state: VerificationState
    human_review_required: bool = True
    automatic_publication: bool = False
    automatic_editorial_approval: bool = False


class ContentGovernanceQueueEvidence(BaseModel):
    state_counts: Dict[str, int] = Field(default_factory=dict)
    priority_counts: Dict[str, int] = Field(default_factory=dict)
    post_type_counts: Dict[str, int] = Field(default_factory=dict)


class ContentGovernanceQueueSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-content-governance-summary/1.0", alias="schema")
    version: str = "5.5.0"
    total: int = Field(ge=0)
    publication_blocked: int = Field(ge=0)
    overdue: int = Field(ge=0)
    due_soon: int = Field(ge=0)
    unassigned: int = Field(ge=0)
    review_required: int = Field(ge=0)
    ready_for_verification: int = Field(ge=0)
    verified: int = Field(ge=0)
    superseded: int = Field(ge=0)
    priority_counts: Dict[str, int]
    post_type_counts: Dict[str, int]
    generated_at: str
    human_review_required: bool = True


class ContentGovernanceBulkRequest(BaseModel):
    record_ids: List[int] = Field(default_factory=list)
    action: Literal[
        "request_review",
        "verify",
        "set_priority",
        "assign_owner",
        "assign_technical_owner",
        "set_cadence",
    ]
    value: str = ""
    note: str = ""
    actor_can_verify: bool = False


class ContentGovernanceBulkPlan(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-content-governance-bulk-plan/1.0", alias="schema")
    version: str = "5.5.0"
    requested_records: int = Field(ge=0)
    unique_records: int = Field(ge=0)
    action: str
    allowed: bool
    blockers: List[str]
    required_actions: List[str]
    human_execution_required: bool = True
    automatic_publication: bool = False


def _days_until(value: str) -> Optional[int]:
    if not value:
        return None
    try:
        parsed = date.fromisoformat(value)
    except ValueError:
        return None
    return (parsed - datetime.now(timezone.utc).date()).days


def evaluate_content_governance(evidence: ContentGovernanceEvidence) -> ContentGovernanceAssessment:
    blockers: List[str] = []
    actions: List[str] = []
    score = 100
    days_until_review = _days_until(evidence.next_review_at)

    if evidence.superseded_by_id > 0 or evidence.verification_state == "superseded":
        return ContentGovernanceAssessment(
            record_id=evidence.record_id,
            queue_state="superseded",
            governance_score=100,
            blockers=[],
            required_actions=[],
            days_until_review=days_until_review,
            verification_state="superseded",
        )

    if evidence.require_content_owner and not evidence.owner_assigned:
        blockers.append("content_owner_required")
        actions.append("assign_content_owner")
        score -= 20

    if evidence.require_technical_owner and not evidence.technical_owner_assigned:
        blockers.append("technical_owner_required")
        actions.append("assign_technical_owner")
        score -= 20

    if evidence.workflow_state in {"changes_requested", "expired"}:
        blockers.append("editorial_workflow_blocked")
        actions.append("resolve_editorial_workflow")
        score -= 30

    if evidence.integrity_state != "unscanned":
        if evidence.integrity_state == "publication_blocked" or evidence.integrity_score < evidence.minimum_integrity_score:
            blockers.append("integrity_threshold_not_met")
            actions.append("resolve_article_integrity")
            score -= 30
        elif evidence.integrity_stale:
            actions.append("refresh_integrity_scan")
            score -= 10

    if not evidence.next_review_at:
        actions.append("set_next_review_date")
        score -= 10
    elif days_until_review is not None and days_until_review < 0:
        blockers.append("review_overdue")
        actions.append("complete_verification_review")
        score -= 30
    elif days_until_review is not None and days_until_review <= evidence.review_warning_days:
        actions.append("schedule_verification_review")
        score -= 10

    if not evidence.last_verified_at or evidence.verification_state not in {"verified", "verified_with_limitations"}:
        actions.append("verify_content")
        score -= 15

    score = max(0, min(100, score))
    blocker_set = set(blockers)
    if blocker_set.intersection({"editorial_workflow_blocked", "integrity_threshold_not_met"}):
        queue_state: QueueState = "publication_blocked"
    elif "review_overdue" in blocker_set:
        queue_state = "overdue"
    elif blocker_set.intersection({"content_owner_required", "technical_owner_required"}):
        queue_state = "unassigned"
    elif days_until_review is not None and days_until_review <= evidence.review_warning_days:
        queue_state = "due_soon"
    elif evidence.verification_state in {"verified", "verified_with_limitations"} and not blockers:
        queue_state = "verified"
    elif not blockers and score >= 80:
        queue_state = "ready_for_verification"
    else:
        queue_state = "review_required"

    return ContentGovernanceAssessment(
        record_id=evidence.record_id,
        queue_state=queue_state,
        governance_score=score,
        blockers=list(dict.fromkeys(blockers)),
        required_actions=list(dict.fromkeys(actions)),
        days_until_review=days_until_review,
        verification_state=evidence.verification_state,
    )


def summarize_content_governance_queue(evidence: ContentGovernanceQueueEvidence) -> ContentGovernanceQueueSummary:
    states = {key: max(0, int(value)) for key, value in evidence.state_counts.items()}
    priorities = {key: max(0, int(value)) for key, value in evidence.priority_counts.items()}
    post_types = {key: max(0, int(value)) for key, value in evidence.post_type_counts.items()}
    keys = [
        "publication_blocked",
        "overdue",
        "due_soon",
        "unassigned",
        "review_required",
        "ready_for_verification",
        "verified",
        "superseded",
    ]
    normalized = {key: states.get(key, 0) for key in keys}
    return ContentGovernanceQueueSummary(
        total=sum(normalized.values()),
        publication_blocked=normalized["publication_blocked"],
        overdue=normalized["overdue"],
        due_soon=normalized["due_soon"],
        unassigned=normalized["unassigned"],
        review_required=normalized["review_required"],
        ready_for_verification=normalized["ready_for_verification"],
        verified=normalized["verified"],
        superseded=normalized["superseded"],
        priority_counts=priorities,
        post_type_counts=post_types,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def plan_content_governance_bulk_action(request: ContentGovernanceBulkRequest) -> ContentGovernanceBulkPlan:
    ids = [record_id for record_id in request.record_ids if record_id > 0]
    unique_ids = list(dict.fromkeys(ids))
    blockers: List[str] = []
    actions: List[str] = []

    if not unique_ids:
        blockers.append("record_selection_required")
        actions.append("select_support_content_records")
    if request.action == "verify":
        if not request.actor_can_verify:
            blockers.append("verification_capability_required")
            actions.append("use_authorized_human_reviewer")
        if not request.note.strip():
            blockers.append("verification_note_required")
            actions.append("add_verification_note")
    if request.action in {"set_priority", "assign_owner", "assign_technical_owner", "set_cadence"} and not request.value.strip():
        blockers.append("bulk_value_required")
        actions.append("provide_bulk_action_value")

    return ContentGovernanceBulkPlan(
        requested_records=len(request.record_ids),
        unique_records=len(unique_ids),
        action=request.action,
        allowed=not blockers,
        blockers=list(dict.fromkeys(blockers)),
        required_actions=list(dict.fromkeys(actions)),
    )
