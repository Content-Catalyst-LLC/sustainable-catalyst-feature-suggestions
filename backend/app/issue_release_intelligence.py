from __future__ import annotations

from datetime import datetime, timezone
from typing import Dict, List, Literal

from pydantic import BaseModel, ConfigDict, Field


IssueStatus = Literal[
    "investigating",
    "identified",
    "workaround_available",
    "fix_planned",
    "resolved",
    "monitoring",
    "closed",
]
IssueSeverity = Literal["informational", "minor", "moderate", "major", "critical"]
ReleaseStatus = Literal["planned", "preview", "current", "maintenance", "superseded", "retired"]
HealthState = Literal["linked", "review_required", "blocked"]


class IssueReleaseEvidence(BaseModel):
    issue_id: str = Field(min_length=1, max_length=120)
    title: str = Field(min_length=1, max_length=300)
    status: IssueStatus = "investigating"
    severity: IssueSeverity = "moderate"
    affected_versions: List[str] = Field(default_factory=list)
    components: List[str] = Field(default_factory=list)
    workaround: str = ""
    resolution: str = ""
    target_release_ids: List[str] = Field(default_factory=list)
    fixed_release_ids: List[str] = Field(default_factory=list)
    related_article_ids: List[str] = Field(default_factory=list)
    last_verified_at: str = ""


class ReleaseIssueEvidence(BaseModel):
    release_id: str = Field(min_length=1, max_length=120)
    title: str = Field(min_length=1, max_length=300)
    status: ReleaseStatus = "planned"
    release_date: str = ""
    changelog_url: str = ""
    documentation_url: str = ""
    related_issue_ids: List[str] = Field(default_factory=list)
    related_article_ids: List[str] = Field(default_factory=list)
    last_verified_at: str = ""


class IssueReleaseIntelligenceRequest(BaseModel):
    issues: List[IssueReleaseEvidence] = Field(default_factory=list, max_length=2000)
    releases: List[ReleaseIssueEvidence] = Field(default_factory=list, max_length=1000)


class RelationshipWarning(BaseModel):
    code: str
    record_id: str
    record_type: Literal["known_issue", "release_record"]
    severity: Literal["info", "warning", "error"]
    message: str


class IssueIntelligenceRecord(BaseModel):
    issue_id: str
    state: HealthState
    relationship_count: int = Field(ge=0)
    affected_version_count: int = Field(ge=0)
    target_release_count: int = Field(ge=0)
    fixed_release_count: int = Field(ge=0)
    related_article_count: int = Field(ge=0)
    warnings: List[str] = Field(default_factory=list)


class ReleaseIntelligenceRecord(BaseModel):
    release_id: str
    state: HealthState
    open_issue_ids: List[str] = Field(default_factory=list)
    resolved_issue_ids: List[str] = Field(default_factory=list)
    major_open_issue_ids: List[str] = Field(default_factory=list)
    related_article_count: int = Field(ge=0)
    warnings: List[str] = Field(default_factory=list)


class IssueReleaseIntelligenceSummary(BaseModel):
    issue_count: int = Field(ge=0)
    active_issue_count: int = Field(ge=0)
    resolved_issue_count: int = Field(ge=0)
    issue_with_workaround_count: int = Field(ge=0)
    issue_with_release_or_article_links_count: int = Field(ge=0)
    release_count: int = Field(ge=0)
    release_with_open_issues_count: int = Field(ge=0)
    review_required_count: int = Field(ge=0)


class IssueReleaseIntelligenceResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-known-issue-release-intelligence/1.0", alias="schema")
    version: str = "5.4.0"
    summary: IssueReleaseIntelligenceSummary
    issues: List[IssueIntelligenceRecord]
    releases: List[ReleaseIntelligenceRecord]
    warnings: List[RelationshipWarning]
    generated_at: str
    wordpress_source_of_truth: bool = True
    automatic_incident_declaration: bool = False
    automatic_release_status_changes: bool = False
    automatic_publication: bool = False
    human_review_required: bool = True


RESOLVED_STATUSES = {"resolved", "closed"}
ACTIVE_RELEASE_STATUSES = {"current", "maintenance"}


def _issue_warnings(issue: IssueReleaseEvidence, releases: Dict[str, ReleaseIssueEvidence]) -> List[str]:
    warnings: List[str] = []
    if not issue.affected_versions:
        warnings.append("affected_versions_missing")
    if issue.status in {"workaround_available", "fix_planned"} and not issue.workaround.strip():
        warnings.append("workaround_missing")
    if issue.status == "fix_planned" and not issue.target_release_ids:
        warnings.append("target_release_missing")
    if issue.status in RESOLVED_STATUSES and not issue.fixed_release_ids:
        warnings.append("fixed_release_missing")
    if issue.severity in {"major", "critical"} and not issue.related_article_ids:
        warnings.append("support_article_missing")
    for release_id in issue.target_release_ids + issue.fixed_release_ids:
        if release_id not in releases:
            warnings.append("release_reference_missing")
            break
    return list(dict.fromkeys(warnings))


def _release_warnings(
    release: ReleaseIssueEvidence,
    open_issue_ids: List[str],
    major_open_issue_ids: List[str],
    issue_map: Dict[str, IssueReleaseEvidence],
) -> List[str]:
    warnings: List[str] = []
    if release.status in ACTIVE_RELEASE_STATUSES and major_open_issue_ids:
        warnings.append("major_open_issues")
    if release.status in {"current", "maintenance", "superseded"} and not release.changelog_url.strip():
        warnings.append("changelog_missing")
    if release.status in ACTIVE_RELEASE_STATUSES and not release.related_article_ids:
        warnings.append("support_articles_missing")
    if any(issue_id not in issue_map for issue_id in release.related_issue_ids):
        warnings.append("issue_reference_missing")
    if release.status == "current" and not release.last_verified_at:
        warnings.append("verification_date_missing")
    if open_issue_ids and not release.documentation_url.strip():
        warnings.append("issue_guidance_url_missing")
    return list(dict.fromkeys(warnings))


def evaluate_issue_release_intelligence(
    payload: IssueReleaseIntelligenceRequest,
) -> IssueReleaseIntelligenceResult:
    issue_map = {issue.issue_id: issue for issue in payload.issues}
    release_map = {release.release_id: release for release in payload.releases}
    issue_records: List[IssueIntelligenceRecord] = []
    release_records: List[ReleaseIntelligenceRecord] = []
    warnings: List[RelationshipWarning] = []

    active_issue_count = 0
    resolved_issue_count = 0
    workaround_count = 0
    linked_issue_count = 0

    for issue in payload.issues:
        issue_warnings = _issue_warnings(issue, release_map)
        if issue.status in RESOLVED_STATUSES:
            resolved_issue_count += 1
        else:
            active_issue_count += 1
        if issue.workaround.strip():
            workaround_count += 1
        relationship_count = len(
            set(issue.target_release_ids + issue.fixed_release_ids + issue.related_article_ids)
        )
        if relationship_count:
            linked_issue_count += 1
        state: HealthState = "review_required" if issue_warnings else "linked"
        if issue.status in RESOLVED_STATUSES and "fixed_release_missing" in issue_warnings:
            state = "blocked"
        issue_records.append(
            IssueIntelligenceRecord(
                issue_id=issue.issue_id,
                state=state,
                relationship_count=relationship_count,
                affected_version_count=len(set(issue.affected_versions)),
                target_release_count=len(set(issue.target_release_ids)),
                fixed_release_count=len(set(issue.fixed_release_ids)),
                related_article_count=len(set(issue.related_article_ids)),
                warnings=issue_warnings,
            )
        )
        for code in issue_warnings:
            warnings.append(
                RelationshipWarning(
                    code=code,
                    record_id=issue.issue_id,
                    record_type="known_issue",
                    severity="error" if code == "fixed_release_missing" else "warning",
                    message=code.replace("_", " ").capitalize(),
                )
            )

    releases_with_open_issues = 0
    for release in payload.releases:
        related_issue_ids = list(dict.fromkeys(release.related_issue_ids))
        open_ids = [
            issue_id
            for issue_id in related_issue_ids
            if issue_id in issue_map and issue_map[issue_id].status not in RESOLVED_STATUSES
        ]
        resolved_ids = [
            issue_id
            for issue_id in related_issue_ids
            if issue_id in issue_map and issue_map[issue_id].status in RESOLVED_STATUSES
        ]
        major_open_ids = [
            issue_id
            for issue_id in open_ids
            if issue_map[issue_id].severity in {"major", "critical"}
        ]
        if open_ids:
            releases_with_open_issues += 1
        release_warnings = _release_warnings(
            release, open_ids, major_open_ids, issue_map
        )
        state: HealthState = "review_required" if release_warnings else "linked"
        if release.status == "current" and major_open_ids:
            state = "blocked"
        release_records.append(
            ReleaseIntelligenceRecord(
                release_id=release.release_id,
                state=state,
                open_issue_ids=open_ids,
                resolved_issue_ids=resolved_ids,
                major_open_issue_ids=major_open_ids,
                related_article_count=len(set(release.related_article_ids)),
                warnings=release_warnings,
            )
        )
        for code in release_warnings:
            warnings.append(
                RelationshipWarning(
                    code=code,
                    record_id=release.release_id,
                    record_type="release_record",
                    severity="error" if code == "major_open_issues" else "warning",
                    message=code.replace("_", " ").capitalize(),
                )
            )

    review_required_count = sum(
        record.state != "linked" for record in issue_records + release_records
    )
    summary = IssueReleaseIntelligenceSummary(
        issue_count=len(payload.issues),
        active_issue_count=active_issue_count,
        resolved_issue_count=resolved_issue_count,
        issue_with_workaround_count=workaround_count,
        issue_with_release_or_article_links_count=linked_issue_count,
        release_count=len(payload.releases),
        release_with_open_issues_count=releases_with_open_issues,
        review_required_count=review_required_count,
    )
    return IssueReleaseIntelligenceResult(
        summary=summary,
        issues=issue_records,
        releases=release_records,
        warnings=warnings,
        generated_at=datetime.now(timezone.utc).isoformat(),
    )
