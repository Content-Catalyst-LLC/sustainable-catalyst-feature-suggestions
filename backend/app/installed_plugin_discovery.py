"""Installed WordPress plugin discovery validation for v7.2.0."""

from __future__ import annotations

from collections import Counter
from typing import List, Literal

from pydantic import BaseModel, Field

VERSION = "7.2.0"
SCHEMA = "scfs-installed-plugin-discovery/1.0"

MatchStrategy = Literal[
    "exact_plugin_file",
    "sc_product_id_header",
    "plugin_slug",
    "text_domain",
    "approved_name_alias",
]


class PluginDiscoveryMatch(BaseModel):
    product_id: str = Field(min_length=2, max_length=100)
    plugin_file: str = Field(min_length=3, max_length=240)
    plugin_name: str = Field(min_length=1, max_length=200)
    version: str = Field(default="", max_length=80)
    text_domain: str = Field(default="", max_length=120)
    active: bool
    match_strategy: MatchStrategy
    confidence: int = Field(ge=0, le=100)
    discovered_at: str = ""


class PluginDiscoveryCandidate(BaseModel):
    plugin_file: str = Field(min_length=3, max_length=240)
    name: str = Field(min_length=1, max_length=200)
    version: str = Field(default="", max_length=80)
    author: str = Field(default="", max_length=200)
    text_domain: str = Field(default="", max_length=120)
    active: bool = False
    suggested_product_id: str = Field(default="", max_length=100)
    review_state: Literal["pending", "duplicate_match"] = "pending"


class PluginDiscoveryEvidence(BaseModel):
    installed_plugin_count: int = Field(ge=0, le=10000)
    matches: List[PluginDiscoveryMatch] = Field(default_factory=list, max_length=250)
    pending: List[PluginDiscoveryCandidate] = Field(default_factory=list, max_length=250)


class PluginDiscoveryIssue(BaseModel):
    level: Literal["error", "warning"]
    code: str
    product_id: str = ""
    message: str


class PluginDiscoveryAssessment(BaseModel):
    version: str = VERSION
    schema_name: str = SCHEMA
    valid: bool
    installed_plugin_count: int
    matched_product_count: int
    pending_candidate_count: int
    active_match_count: int
    inactive_match_count: int
    strategies: dict[str, int]
    issues: List[PluginDiscoveryIssue]
    automatic_publication: bool = False
    human_review_required: bool = True


def discovery_capabilities() -> dict:
    return {
        "version": VERSION,
        "schema": SCHEMA,
        "matching_hierarchy": [
            "exact_plugin_file",
            "sc_product_id_header",
            "plugin_slug",
            "text_domain",
            "approved_name_alias",
        ],
        "approved_registry_only": True,
        "unknown_plugins_auto_registered": False,
        "unknown_plugins_publicly_exposed": False,
        "absolute_plugin_paths_publicly_exposed": False,
        "automatic_publication": False,
        "manual_overrides_preserved": True,
        "product_lock_supported": True,
        "human_review_required": True,
    }


def validate_plugin_discovery(evidence: PluginDiscoveryEvidence) -> PluginDiscoveryAssessment:
    issues: list[PluginDiscoveryIssue] = []
    product_counts = Counter(match.product_id for match in evidence.matches)
    for product_id, count in sorted(product_counts.items()):
        if count > 1:
            issues.append(PluginDiscoveryIssue(
                level="error",
                code="duplicate_product_match",
                product_id=product_id,
                message="Only one installed plugin may update a canonical product record automatically.",
            ))
    plugin_files = Counter(match.plugin_file for match in evidence.matches)
    for plugin_file, count in sorted(plugin_files.items()):
        if count > 1:
            issues.append(PluginDiscoveryIssue(
                level="error",
                code="duplicate_plugin_file",
                message=f"Plugin file {plugin_file} appears in more than one approved match.",
            ))
    for match in evidence.matches:
        if match.plugin_file.startswith("/") or "\\" in match.plugin_file:
            issues.append(PluginDiscoveryIssue(
                level="error",
                code="absolute_or_invalid_plugin_path",
                product_id=match.product_id,
                message="Discovery records must use WordPress-relative plugin file identifiers.",
            ))
        if not match.version:
            issues.append(PluginDiscoveryIssue(
                level="warning",
                code="plugin_version_missing",
                product_id=match.product_id,
                message="The installed plugin did not provide a version header.",
            ))
        if match.confidence < 80:
            issues.append(PluginDiscoveryIssue(
                level="warning",
                code="low_match_confidence",
                product_id=match.product_id,
                message="The discovery match should be reviewed before it affects release information.",
            ))
    if evidence.installed_plugin_count < len(evidence.matches) + len(evidence.pending):
        issues.append(PluginDiscoveryIssue(
            level="error",
            code="plugin_count_inconsistent",
            message="Matched and pending discovery records cannot exceed the installed plugin count.",
        ))
    strategies = Counter(match.match_strategy for match in evidence.matches)
    return PluginDiscoveryAssessment(
        valid=not any(issue.level == "error" for issue in issues),
        installed_plugin_count=evidence.installed_plugin_count,
        matched_product_count=len(evidence.matches),
        pending_candidate_count=len(evidence.pending),
        active_match_count=sum(1 for match in evidence.matches if match.active),
        inactive_match_count=sum(1 for match in evidence.matches if not match.active),
        strategies=dict(sorted(strategies.items())),
        issues=issues,
    )
