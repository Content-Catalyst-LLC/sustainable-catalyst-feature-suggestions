from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field

Criticality = Literal["low", "moderate", "high", "critical"]
ImpactState = Literal["low", "moderate", "high", "critical"]
RelationshipType = Literal[
    "depends_on",
    "integrates_with",
    "shares_component",
    "routes_to",
    "provides_data_to",
]


class DependencyEdge(BaseModel):
    source: str = Field(min_length=1, max_length=120)
    target: str = Field(min_length=1, max_length=120)
    relationship: RelationshipType = "depends_on"
    component: str = Field(default="", max_length=200)
    criticality: Criticality = "moderate"
    active: bool = True


class IncidentImpactEvidence(BaseModel):
    affected_products: int = Field(default=0, ge=0)
    dependent_products: int = Field(default=0, ge=0)
    criticality: Criticality = "moderate"
    active_known_issues: int = Field(default=0, ge=0)
    blocked_releases: int = Field(default=0, ge=0)
    support_handoffs: int = Field(default=0, ge=0)
    shared_components: int = Field(default=0, ge=0)


class IncidentImpactResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-incident-impact/1.0", alias="schema")
    version: str = "4.5.0"
    score: float = Field(ge=0, le=100)
    state: ImpactState
    signals: List[str]
    generated_at: str
    human_review_required: bool = True
    automatic_incident_declaration: bool = False
    automatic_release_blocking: bool = False


class ProductRouteEvidence(BaseModel):
    product: str = ""
    component: str = ""
    issue_type: str = ""
    graph: List[DependencyEdge] = Field(default_factory=list)


class ProductRoute(BaseModel):
    product: str
    relationship: RelationshipType
    component: str = ""
    criticality: Criticality
    direction: Literal["inbound", "outbound"]
    score: float = Field(ge=0, le=100)
    reason: str


class ProductRouteResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-route-recommendations/1.0", alias="schema")
    version: str = "4.5.0"
    starting_product: str
    routes: List[ProductRoute]
    human_review_required: bool = True


class ResolutionJourneyEvidence(BaseModel):
    product: str = ""
    component: str = ""
    issue_type: str = ""
    has_symptom: bool = False
    graph: List[DependencyEdge] = Field(default_factory=list)


class JourneyStep(BaseModel):
    key: str
    label: str
    view: str
    product: str = ""
    relationship: str = ""
    component: str = ""


class ResolutionJourneyResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-resolution-journey/1.0", alias="schema")
    version: str = "4.5.0"
    starting_product: str
    steps: List[JourneyStep]
    related_products: List[ProductRoute]
    private_case_content_stored: bool = False
    automatic_private_case_creation: bool = False
    human_review_required: bool = True


class OrchestrationReportEvidence(BaseModel):
    records: List[Dict] = Field(default_factory=list)
    expected_checksum: str = ""


class OrchestrationReportResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-report-integrity/1.0", alias="schema")
    version: str = "4.5.0"
    record_count: int = Field(ge=0)
    checksum: str
    matches_expected: bool
    deterministic_ordering: bool = True


CRITICALITY_WEIGHT = {"low": 8, "moderate": 18, "high": 32, "critical": 48}
RELATIONSHIP_BONUS = {
    "depends_on": 18,
    "integrates_with": 10,
    "shares_component": 15,
    "routes_to": 25,
    "provides_data_to": 12,
}
CRITICALITY_BONUS = {"low": 0, "moderate": 5, "high": 10, "critical": 15}
RELATIONSHIP_LABEL = {
    "depends_on": "Depends on",
    "integrates_with": "Integrates with",
    "shares_component": "Shares component with",
    "routes_to": "Routes support to",
    "provides_data_to": "Provides data to",
}


def evaluate_incident_impact(evidence: IncidentImpactEvidence) -> IncidentImpactResult:
    score = float(CRITICALITY_WEIGHT[evidence.criticality])
    score += min(24, evidence.affected_products * 6)
    score += min(12, evidence.dependent_products * 3)
    score += min(8, evidence.active_known_issues * 2)
    score += min(10, evidence.blocked_releases * 5)
    score += min(8, evidence.support_handoffs * 2)
    score += min(6, evidence.shared_components * 2)
    score = round(max(0.0, min(100.0, score)), 1)
    if score >= 80:
        state: ImpactState = "critical"
    elif score >= 60:
        state = "high"
    elif score >= 35:
        state = "moderate"
    else:
        state = "low"
    signals: List[str] = []
    if evidence.affected_products > 1:
        signals.append("The incident affects multiple products.")
    if evidence.dependent_products:
        signals.append("Downstream product dependencies may propagate the incident.")
    if evidence.blocked_releases:
        signals.append("One or more release records require human review.")
    if evidence.support_handoffs:
        signals.append("The issue is generating private-support handoffs.")
    return IncidentImpactResult(
        score=score,
        state=state,
        signals=signals,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )


def recommend_product_routes(evidence: ProductRouteEvidence) -> ProductRouteResult:
    product = evidence.product.strip().lower()
    component = evidence.component.strip().lower()
    recommendations: Dict[str, ProductRoute] = {}
    for edge in evidence.graph:
        if not edge.active:
            continue
        candidate = ""
        direction: Literal["inbound", "outbound"] = "outbound"
        if edge.source.strip().lower() == product:
            candidate = edge.target.strip().lower()
            direction = "outbound"
        elif edge.target.strip().lower() == product:
            candidate = edge.source.strip().lower()
            direction = "inbound"
        if not candidate or candidate == product:
            continue
        score = 40 + RELATIONSHIP_BONUS[edge.relationship] + CRITICALITY_BONUS[edge.criticality]
        if component and edge.component and component in edge.component.lower():
            score += 15
        route = ProductRoute(
            product=candidate,
            relationship=edge.relationship,
            component=edge.component,
            criticality=edge.criticality,
            direction=direction,
            score=min(100, score),
            reason=RELATIONSHIP_LABEL[edge.relationship],
        )
        existing = recommendations.get(candidate)
        if existing is None or route.score > existing.score:
            recommendations[candidate] = route
    ordered = sorted(recommendations.values(), key=lambda route: (-route.score, route.product))
    return ProductRouteResult(starting_product=product, routes=ordered)


def build_resolution_journey(evidence: ResolutionJourneyEvidence) -> ResolutionJourneyResult:
    routes = recommend_product_routes(
        ProductRouteEvidence(
            product=evidence.product,
            component=evidence.component,
            issue_type=evidence.issue_type,
            graph=evidence.graph,
        )
    )
    steps: List[JourneyStep] = [
        JourneyStep(key="diagnose", label="Diagnose the visible symptom", view="resolve", product=evidence.product),
        JourneyStep(key="status", label="Check product and platform incidents", view="platform", product=evidence.product),
        JourneyStep(key="learn", label="Review product-aware documentation", view="documentation", product=evidence.product),
    ]
    for route in routes.routes[:3]:
        steps.append(
            JourneyStep(
                key="related-product",
                label=f"Review related product: {route.product}",
                view="resolve",
                product=route.product,
                relationship=route.relationship,
                component=route.component,
            )
        )
    steps.append(
        JourneyStep(
            key="private-support",
            label="Continue to private support when public guidance is insufficient",
            view="private-support",
            product=evidence.product,
        )
    )
    return ResolutionJourneyResult(
        starting_product=evidence.product,
        steps=steps,
        related_products=routes.routes,
    )


def verify_orchestration_report(evidence: OrchestrationReportEvidence) -> OrchestrationReportResult:
    ordered = sorted(evidence.records, key=lambda row: json.dumps(row, sort_keys=True, separators=(",", ":")))
    payload = json.dumps(ordered, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    checksum = hashlib.sha256(payload.encode("utf-8")).hexdigest()
    return OrchestrationReportResult(
        record_count=len(ordered),
        checksum=checksum,
        matches_expected=bool(evidence.expected_checksum) and checksum == evidence.expected_checksum,
    )
