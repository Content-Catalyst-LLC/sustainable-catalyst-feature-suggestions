from __future__ import annotations

import hashlib
import json
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field


class OperationsModuleEvidence(BaseModel):
    key: str = Field(min_length=1, max_length=80)
    ready: bool = False
    critical: bool = False
    blockers: int = Field(default=0, ge=0)
    overdue_items: int = Field(default=0, ge=0)


class ConnectedOperationsEvidence(BaseModel):
    product: str = Field(default="platform", min_length=1, max_length=120)
    modules: List[OperationsModuleEvidence] = Field(default_factory=list)
    content_readiness_score: int = Field(default=0, ge=0, le=100)
    product_reliability_score: int = Field(default=0, ge=0, le=100)
    active_incidents: int = Field(default=0, ge=0)
    critical_incidents: int = Field(default=0, ge=0)
    unresolved_query_clusters: int = Field(default=0, ge=0)
    governance_blockers: int = Field(default=0, ge=0)
    repository_drift_items: int = Field(default=0, ge=0)
    private_handoff_ready: bool = False


class ConnectedOperationsScore(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-connected-product-support-operations/1.0", alias="schema")
    version: str = "5.0.0"
    product: str
    score: int = Field(ge=0, le=100)
    state: Literal["operational", "attention", "not_ready"]
    dimensions: Dict[str, int]
    blockers: List[str]
    recommended_actions: List[str]
    human_review_required: bool = True
    automatic_publication: bool = False
    automatic_case_creation: bool = False


class OperationsActionEvidence(BaseModel):
    action_type: Literal[
        "validate_content",
        "refresh_reliability",
        "run_editorial_governance",
        "inspect_repositories",
        "refresh_documentation_gaps",
    ]
    product: str = "platform"
    evidence_count: int = Field(default=0, ge=0)
    risk: Literal["low", "moderate", "high"] = "low"
    requested_by_human: bool = True


class OperationsActionPlan(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-connected-operations-action-plan/1.0", alias="schema")
    version: str = "5.0.0"
    action_type: str
    product: str
    permitted: bool
    execution_mode: Literal["human_approved", "blocked"]
    steps: List[str]
    safeguards: List[str]
    prohibited_outcomes: List[str]
    human_review_required: bool = True


class OperationsReportEvidence(BaseModel):
    payload: Dict[str, object]
    checksum: str = Field(min_length=64, max_length=64)
    algorithm: Literal["sha256"] = "sha256"


class OperationsReportResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-connected-operations-report-integrity/1.0", alias="schema")
    version: str = "5.0.0"
    valid: bool
    expected_checksum: str
    supplied_checksum: str
    algorithm: str = "sha256"


def _bounded(value: float) -> int:
    return max(0, min(100, int(round(value))))


def score_connected_operations(payload: ConnectedOperationsEvidence) -> ConnectedOperationsScore:
    total_modules = len(payload.modules)
    ready_modules = sum(1 for module in payload.modules if module.ready)
    module_score = 100 if total_modules == 0 else round((ready_modules / total_modules) * 100)
    overdue = sum(module.overdue_items for module in payload.modules)
    module_blockers = sum(module.blockers for module in payload.modules)
    critical_missing = [module.key for module in payload.modules if module.critical and not module.ready]

    incident_score = 100 - min(70, payload.active_incidents * 8 + payload.critical_incidents * 25)
    governance_score = 100 - min(70, payload.governance_blockers * 12 + overdue * 4)
    repository_score = 100 - min(70, payload.repository_drift_items * 8)
    resolution_score = 100 - min(70, payload.unresolved_query_clusters * 7)
    handoff_score = 100 if payload.private_handoff_ready else 60

    score = _bounded(
        module_score * 0.20
        + payload.content_readiness_score * 0.20
        + payload.product_reliability_score * 0.25
        + incident_score * 0.10
        + governance_score * 0.10
        + repository_score * 0.05
        + resolution_score * 0.05
        + handoff_score * 0.05
        - min(20, module_blockers * 2)
    )

    blockers: List[str] = []
    actions: List[str] = []
    if critical_missing:
        blockers.append("Critical connected modules are unavailable: " + ", ".join(sorted(critical_missing)) + ".")
        actions.append("Restore or configure the unavailable critical modules before declaring the product operational.")
    if payload.content_readiness_score < 60:
        blockers.append("Support-content readiness is below 60.")
        actions.append("Complete product onboarding, starter documentation, and content validation.")
    if payload.product_reliability_score < 60:
        blockers.append("Product reliability evidence is below 60.")
        actions.append("Review resolution failures, documentation feedback, known issues, and release readiness.")
    if payload.critical_incidents:
        blockers.append("One or more critical platform incidents are active.")
        actions.append("Coordinate the active incident through the cross-product support workflow.")
    if payload.governance_blockers or overdue:
        blockers.append("Editorial governance has blocked or overdue work.")
        actions.append("Resolve review, approval, standards, or expiration blockers.")
    if payload.repository_drift_items:
        actions.append("Review repository drift and create approval-gated update drafts where appropriate.")
    if payload.unresolved_query_clusters:
        actions.append("Prioritize repeated unresolved-query clusters and documentation gaps.")
    if not payload.private_handoff_ready:
        actions.append("Verify the Contact and Engagement destination before relying on private-support continuation.")

    state: Literal["operational", "attention", "not_ready"]
    if score >= 75 and not critical_missing and payload.critical_incidents == 0:
        state = "operational"
    elif score >= 50:
        state = "attention"
    else:
        state = "not_ready"

    return ConnectedOperationsScore(
        product=payload.product,
        score=score,
        state=state,
        dimensions={
            "module_readiness": _bounded(module_score),
            "content_readiness": payload.content_readiness_score,
            "product_reliability": payload.product_reliability_score,
            "incident_health": _bounded(incident_score),
            "governance_health": _bounded(governance_score),
            "repository_health": _bounded(repository_score),
            "resolution_health": _bounded(resolution_score),
            "private_handoff_readiness": _bounded(handoff_score),
        },
        blockers=blockers,
        recommended_actions=list(dict.fromkeys(actions)),
    )


def plan_connected_action(payload: OperationsActionEvidence) -> OperationsActionPlan:
    safeguards = [
        "Require an authenticated WordPress administrator.",
        "Record the action in the bounded connected-operations queue.",
        "Preserve specialist modules as the source of truth.",
        "Do not publish, approve, declare incidents, change roadmaps, or create private cases automatically.",
    ]
    permitted = payload.requested_by_human and payload.risk != "high"
    if payload.risk == "high":
        safeguards.append("High-risk operations require manual execution inside the specialist module.")
    steps = [
        "Review the operational evidence and selected product context.",
        "Queue the action for explicit human execution.",
        "Run the corresponding specialist-module operation.",
        "Capture the result in the connected operations log and refresh the platform snapshot.",
    ]
    return OperationsActionPlan(
        action_type=payload.action_type,
        product=payload.product,
        permitted=permitted,
        execution_mode="human_approved" if permitted else "blocked",
        steps=steps if permitted else steps[:2],
        safeguards=safeguards,
        prohibited_outcomes=[
            "automatic_publication",
            "automatic_editorial_approval",
            "automatic_incident_declaration",
            "automatic_roadmap_change",
            "automatic_private_case_creation",
        ],
    )


def verify_connected_operations_report(payload: OperationsReportEvidence) -> OperationsReportResult:
    canonical = json.dumps(payload.payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    expected = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
    return OperationsReportResult(
        valid=expected == payload.checksum.lower(),
        expected_checksum=expected,
        supplied_checksum=payload.checksum.lower(),
    )
