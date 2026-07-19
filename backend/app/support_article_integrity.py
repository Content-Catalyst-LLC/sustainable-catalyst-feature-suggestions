from __future__ import annotations

import re
from typing import List, Literal

from pydantic import BaseModel, Field

VERSION = "5.2.8"
SCHEMA = "scfs-support-article-integrity/1.0"


class SupportArticleIntegrityEvidence(BaseModel):
    title: str = ""
    content_text: str = ""
    excerpt_or_summary: str = ""
    products: List[str] = Field(default_factory=list)
    versions: List[str] = Field(default_factory=list)
    components: List[str] = Field(default_factory=list)
    article_types: List[str] = Field(default_factory=list)
    collections: List[str] = Field(default_factory=list)
    verified_version: str = ""
    headings: List[str] = Field(default_factory=list)
    heading_levels: List[int] = Field(default_factory=list)
    required_sections: List[str] = Field(default_factory=list)
    placeholder_count: int = Field(default=0, ge=0)
    invalid_link_count: int = Field(default=0, ge=0)
    image_count: int = Field(default=0, ge=0)
    images_missing_alt: int = Field(default=0, ge=0)
    figure_count: int = Field(default=0, ge=0)
    figures_missing_caption: int = Field(default=0, ge=0)
    table_count: int = Field(default=0, ge=0)
    tables_missing_headers: int = Field(default=0, ge=0)
    related_release_count: int = Field(default=0, ge=0)
    related_issue_count: int = Field(default=0, ge=0)
    related_article_count: int = Field(default=0, ge=0)
    days_since_updated: int = Field(default=0, ge=0)
    review_overdue: bool = False
    published: bool = False
    minimum_word_count: int = Field(default=180, ge=1)
    recommended_word_count: int = Field(default=450, ge=1)
    stale_after_days: int = Field(default=180, ge=1)


class IntegrityIssue(BaseModel):
    code: str
    severity: Literal["error", "warning", "info"]
    message: str


class SupportArticleIntegrityResult(BaseModel):
    version: str = VERSION
    schema_name: str = SCHEMA
    score: int = Field(ge=0, le=100)
    state: Literal["ready", "review", "needs_work", "blocked"]
    stale: bool
    word_count: int = Field(ge=0)
    reading_minutes: int = Field(ge=1)
    errors: int = Field(ge=0)
    warnings: int = Field(ge=0)
    information: int = Field(ge=0)
    issues: List[IntegrityIssue]
    automatic_content_changes: bool = False
    automatic_publication: bool = False
    human_review_required: bool = True


def _words(value: str) -> List[str]:
    return re.findall(r"\b[\w'-]+\b", value or "", flags=re.UNICODE)


def assess_support_article_integrity(
    evidence: SupportArticleIntegrityEvidence,
) -> SupportArticleIntegrityResult:
    issues: List[IntegrityIssue] = []

    def add(code: str, severity: Literal["error", "warning", "info"], message: str) -> None:
        issues.append(IntegrityIssue(code=code, severity=severity, message=message))

    word_count = len(_words(evidence.content_text))
    reading_minutes = max(1, (word_count + 219) // 220)

    if len(evidence.title.strip()) < 8:
        add("title_incomplete", "error", "Add a specific Support Article title.")
    if word_count < evidence.minimum_word_count:
        add("content_too_short", "error", "Article body is below the minimum word count.")
    elif word_count < evidence.recommended_word_count:
        add("content_brief", "warning", "Article body is shorter than the recommended publication length.")
    if not evidence.excerpt_or_summary.strip():
        add("summary_missing", "error", "Add an excerpt or Knowledge Base summary.")
    if not evidence.products:
        add("product_missing", "error", "Assign at least one product.")
    if not evidence.versions:
        add("version_missing", "error", "Assign at least one supported product version.")
    if not evidence.components:
        add("component_missing", "warning", "Assign the product component covered by this article.")
    if not evidence.article_types:
        add("article_type_missing", "error", "Assign an Article Type.")
    if not evidence.verified_version.strip():
        add("verified_version_missing", "error", "Set the last verified version.")
    elif evidence.versions:
        normalized = {re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-") for value in evidence.versions}
        verified = re.sub(r"[^a-z0-9]+", "-", evidence.verified_version.lower()).strip("-")
        if verified not in normalized:
            add("verified_version_unassigned", "warning", "Verified version does not match an assigned version.")

    if not evidence.headings:
        add("headings_missing", "error", "Add descriptive H2 sections.")
    if evidence.heading_levels:
        if 2 not in evidence.heading_levels:
            add("h2_missing", "error", "Use H2 headings for primary article sections.")
        if 1 in evidence.heading_levels:
            add("content_h1_present", "warning", "Remove H1 headings from the article body.")
        previous = 1
        for level in evidence.heading_levels:
            if level > previous + 1:
                add("heading_level_jump", "warning", "Repair skipped heading levels.")
                break
            previous = level

    heading_values = [value.strip().lower() for value in evidence.headings]
    for required in evidence.required_sections:
        required_value = required.strip().lower()
        if required_value and not any(required_value in heading for heading in heading_values):
            add("required_section_missing", "warning", f"Add the required {required} section.")

    if evidence.placeholder_count:
        add("template_placeholder", "error", "Replace template placeholder text before publication.")
    if evidence.invalid_link_count:
        add("link_invalid", "warning", "Repair unresolved, empty, or unsafe links.")
    if evidence.images_missing_alt:
        add("image_alt_missing", "warning", "Add meaningful alternative text to each image.")
    if evidence.figures_missing_caption:
        add("figure_caption_missing", "info", "Add figure captions where context or attribution is required.")
    if evidence.tables_missing_headers:
        add("table_headers_missing", "warning", "Add table header cells.")
    if not (evidence.related_release_count or evidence.related_issue_count or evidence.related_article_count):
        add("relationship_context_empty", "info", "Consider linking this article to related releases, issues, or articles.")

    stale = evidence.days_since_updated > evidence.stale_after_days or evidence.review_overdue
    if evidence.days_since_updated > evidence.stale_after_days:
        add("content_stale", "warning", "Article has exceeded the freshness interval.")
    if evidence.review_overdue:
        add("review_overdue", "error", "Editorial review is overdue.")

    weights = {"error": 16, "warning": 6, "info": 2}
    score = max(0, min(100, 100 - sum(weights[item.severity] for item in issues)))
    errors = sum(item.severity == "error" for item in issues)
    warnings = sum(item.severity == "warning" for item in issues)
    information = sum(item.severity == "info" for item in issues)

    if errors:
        state: Literal["ready", "review", "needs_work", "blocked"] = "needs_work" if score >= 50 else "blocked"
    elif score >= 90:
        state = "ready"
    elif score >= 75:
        state = "review"
    else:
        state = "needs_work"

    return SupportArticleIntegrityResult(
        score=score,
        state=state,
        stale=stale,
        word_count=word_count,
        reading_minutes=reading_minutes,
        errors=errors,
        warnings=warnings,
        information=information,
        issues=issues,
    )
