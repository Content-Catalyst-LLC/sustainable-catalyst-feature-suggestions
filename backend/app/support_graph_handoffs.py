from __future__ import annotations

import hashlib
import json
from collections import deque
from typing import Dict, List, Literal, Optional, Set

from pydantic import BaseModel, ConfigDict, Field

Relationship = Literal[
    "depends_on",
    "integrates_with",
    "shares_component",
    "routes_to",
    "provides_data_to",
]
CoverageState = Literal["connected", "strong", "partial", "limited", "unmapped"]


class SupportGraphNode(BaseModel):
    slug: str = Field(min_length=1, max_length=120)
    name: str = Field(min_length=1, max_length=200)
    public_route: str = ""
    support_route: str = ""
    capabilities: List[str] = Field(default_factory=list)
    article_count: int = Field(default=0, ge=0)
    known_issue_count: int = Field(default=0, ge=0)
    release_count: int = Field(default=0, ge=0)
    example_count: int = Field(default=0, ge=0)
    troubleshooting_count: int = Field(default=0, ge=0)


class SupportGraphEdge(BaseModel):
    source: str = Field(min_length=1, max_length=120)
    target: str = Field(min_length=1, max_length=120)
    relationship: Relationship = "routes_to"
    component: str = Field(default="", max_length=200)
    criticality: Literal["low", "moderate", "high", "critical"] = "moderate"
    active: bool = True


class SupportGraphEvidence(BaseModel):
    nodes: List[SupportGraphNode] = Field(default_factory=list)
    edges: List[SupportGraphEdge] = Field(default_factory=list)


class ProductGraphCoverage(BaseModel):
    slug: str
    score: float = Field(ge=0, le=100)
    state: CoverageState
    connected_products: int = Field(ge=0)
    signals: List[str] = Field(default_factory=list)


class SupportGraphSummary(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-support-graph/1.0", alias="schema")
    version: str = "5.8.0"
    product_count: int = Field(ge=0)
    edge_count: int = Field(ge=0)
    average_coverage_score: float = Field(ge=0, le=100)
    products: List[ProductGraphCoverage]
    personal_identifiers_exposed: bool = False
    private_case_content_exposed: bool = False
    human_review_required: bool = True


class HandoffPlanEvidence(BaseModel):
    product: str = ""
    version: str = ""
    component: str = ""
    intent: str = ""
    nodes: List[SupportGraphNode] = Field(default_factory=list)
    edges: List[SupportGraphEdge] = Field(default_factory=list)
    limit: int = Field(default=5, ge=1, le=20)


class HandoffRecommendation(BaseModel):
    product: str
    name: str
    score: float = Field(ge=0, le=100)
    reasons: List[str]
    support_route: str = ""
    public_route: str = ""
    coverage_score: float = Field(ge=0, le=100)


class HandoffPlanResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-platform-support-handoff/1.0", alias="schema")
    version: str = "5.8.0"
    starting_product: str
    recommendations: List[HandoffRecommendation]
    recommended_start: Optional[HandoffRecommendation] = None
    automatic_redirect: bool = False
    automatic_private_case_creation: bool = False
    human_review_required: bool = True


class SupportPathEvidence(BaseModel):
    source: str
    target: str
    edges: List[SupportGraphEdge] = Field(default_factory=list)


class SupportPathResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-support-path/1.0", alias="schema")
    version: str = "5.8.0"
    source: str
    target: str
    path: List[str]
    reachable: bool


class SupportGraphIntegrityResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-cross-product-support-graph-integrity/1.0", alias="schema")
    version: str = "5.8.0"
    valid: bool
    node_count: int = Field(ge=0)
    edge_count: int = Field(ge=0)
    error_count: int = Field(ge=0)
    warning_count: int = Field(ge=0)
    issues: List[Dict]
    checksum: str
    deterministic_ordering: bool = True


def _coverage_score(node: SupportGraphNode) -> float:
    score = 0.0
    score += min(30, node.article_count * 3)
    score += min(15, node.known_issue_count * 3)
    score += min(20, node.release_count * 4)
    score += min(15, node.example_count * 5)
    score += min(15, node.troubleshooting_count * 5)
    score += min(5, len(node.capabilities))
    return round(min(100.0, score), 1)


def _coverage_state(score: float) -> CoverageState:
    if score >= 80:
        return "connected"
    if score >= 60:
        return "strong"
    if score >= 35:
        return "partial"
    if score > 0:
        return "limited"
    return "unmapped"


def _neighbors(slug: str, edges: List[SupportGraphEdge]) -> Set[str]:
    found: Set[str] = set()
    for edge in edges:
        if not edge.active:
            continue
        if edge.source == slug:
            found.add(edge.target)
        elif edge.target == slug:
            found.add(edge.source)
    return found


def build_support_graph(evidence: SupportGraphEvidence) -> SupportGraphSummary:
    products: List[ProductGraphCoverage] = []
    total = 0.0
    for node in sorted(evidence.nodes, key=lambda item: item.slug):
        score = _coverage_score(node)
        total += score
        signals: List[str] = []
        if node.article_count == 0:
            signals.append("No published Support Articles are represented.")
        if node.release_count == 0:
            signals.append("No release intelligence is represented.")
        if node.troubleshooting_count == 0:
            signals.append("No troubleshooting guidance is represented.")
        products.append(
            ProductGraphCoverage(
                slug=node.slug,
                score=score,
                state=_coverage_state(score),
                connected_products=len(_neighbors(node.slug, evidence.edges)),
                signals=signals,
            )
        )
    average = round(total / len(products), 1) if products else 0.0
    return SupportGraphSummary(
        product_count=len(products),
        edge_count=len([edge for edge in evidence.edges if edge.active]),
        average_coverage_score=average,
        products=products,
    )


def _tokens(value: str) -> Set[str]:
    cleaned = "".join(char.lower() if char.isalnum() else " " for char in value)
    return {token for token in cleaned.split() if token}


def plan_platform_handoffs(evidence: HandoffPlanEvidence) -> HandoffPlanResult:
    node_map = {node.slug: node for node in evidence.nodes}
    tokens = _tokens(f"{evidence.intent} {evidence.component}")
    recommendations: List[HandoffRecommendation] = []
    for slug, node in node_map.items():
        if slug == evidence.product:
            continue
        score = 0.0
        reasons: List[str] = []
        for token in tokens:
            for capability in node.capabilities:
                if token in capability.lower():
                    score += 12
                    reasons.append(f"Matches capability: {capability}")
            if token in node.name.lower():
                score += 8
        for edge in evidence.edges:
            if not edge.active:
                continue
            if edge.source == evidence.product and edge.target == slug:
                score += 30 if edge.relationship == "routes_to" else 20
                reasons.append(f"Configured relationship: {edge.relationship.replace('_', ' ')}")
            elif edge.target == evidence.product and edge.source == slug:
                score += 10
                reasons.append("Related upstream product")
            if evidence.component and edge.component and evidence.component.lower() in edge.component.lower():
                score += 12
                reasons.append("Shares the selected component")
        if score <= 0:
            continue
        coverage = _coverage_score(node)
        recommendations.append(
            HandoffRecommendation(
                product=slug,
                name=node.name,
                score=min(100.0, round(score, 1)),
                reasons=sorted(set(reasons)),
                support_route=node.support_route,
                public_route=node.public_route,
                coverage_score=coverage,
            )
        )
    recommendations.sort(key=lambda item: (-item.score, item.name))
    recommendations = recommendations[: evidence.limit]
    return HandoffPlanResult(
        starting_product=evidence.product,
        recommendations=recommendations,
        recommended_start=recommendations[0] if recommendations else None,
    )


def find_support_path(evidence: SupportPathEvidence) -> SupportPathResult:
    if evidence.source == evidence.target:
        return SupportPathResult(source=evidence.source, target=evidence.target, path=[evidence.source], reachable=True)
    adjacency: Dict[str, Set[str]] = {}
    for edge in evidence.edges:
        if not edge.active:
            continue
        adjacency.setdefault(edge.source, set()).add(edge.target)
        adjacency.setdefault(edge.target, set()).add(edge.source)
    queue = deque([[evidence.source]])
    visited = {evidence.source}
    while queue:
        path = queue.popleft()
        current = path[-1]
        for neighbor in sorted(adjacency.get(current, set())):
            if neighbor in visited:
                continue
            next_path = [*path, neighbor]
            if neighbor == evidence.target:
                return SupportPathResult(source=evidence.source, target=evidence.target, path=next_path, reachable=True)
            visited.add(neighbor)
            queue.append(next_path)
    return SupportPathResult(source=evidence.source, target=evidence.target, path=[], reachable=False)


def verify_support_graph(evidence: SupportGraphEvidence) -> SupportGraphIntegrityResult:
    issues: List[Dict] = []
    slugs: Set[str] = set()
    for node in evidence.nodes:
        if node.slug in slugs:
            issues.append({"code": "duplicate_slug", "severity": "error", "message": f"Duplicate product slug: {node.slug}"})
        slugs.add(node.slug)
        if not node.support_route:
            issues.append({"code": "missing_support_route", "severity": "warning", "message": f"Missing support route: {node.slug}"})
        if not node.capabilities:
            issues.append({"code": "missing_capabilities", "severity": "warning", "message": f"No capabilities registered: {node.slug}"})
    edge_keys: Set[str] = set()
    for edge in evidence.edges:
        key = f"{edge.source}|{edge.relationship}|{edge.target}"
        if edge.source == edge.target:
            issues.append({"code": "self_edge", "severity": "error", "message": f"Self-referencing edge: {edge.source}"})
        if edge.source not in slugs or edge.target not in slugs:
            issues.append({"code": "unknown_node", "severity": "warning", "message": f"Edge references an unknown product: {key}"})
        if key in edge_keys:
            issues.append({"code": "duplicate_edge", "severity": "warning", "message": f"Duplicate edge: {key}"})
        edge_keys.add(key)
    error_count = sum(1 for issue in issues if issue["severity"] == "error")
    canonical = json.dumps(
        {"nodes": sorted(slugs), "edges": sorted(edge_keys)},
        sort_keys=True,
        separators=(",", ":"),
    )
    checksum = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
    return SupportGraphIntegrityResult(
        valid=error_count == 0,
        node_count=len(slugs),
        edge_count=len(edge_keys),
        error_count=error_count,
        warning_count=len(issues) - error_count,
        issues=issues,
        checksum=checksum,
    )
