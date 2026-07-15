from typing import List, Literal
from pydantic import BaseModel, ConfigDict, Field


class ProductOnboardingEvidence(BaseModel):
    profile_started: bool = False
    current_version_present: bool = False
    component_count: int = Field(default=0, ge=0)
    required_article_types_present: int = Field(default=0, ge=0, le=4)
    support_article_count: int = Field(default=0, ge=0)
    release_record_count: int = Field(default=0, ge=0)
    known_issue_count: int = Field(default=0, ge=0)
    known_issues_reviewed: bool = False
    fresh_content_percent: int = Field(default=0, ge=0, le=100)


class ProductSupportReadiness(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-readiness/1.0", alias="schema")
    version: str = "4.1.0"
    score: int = Field(ge=0, le=100)
    state: Literal["not_started", "building", "review", "ready"]
    blockers: List[str]
    signals: List[str]
    human_review_required: bool = True


class SourceDocument(BaseModel):
    filename: str
    content: str
    source_type: Literal["auto", "readme", "documentation", "changelog", "release_notes", "json"] = "auto"


class ImportPlan(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_id: str = Field(default="scfs-support-import-plan/1.0", alias="schema")
    version: str = "4.1.0"
    inferred_source_type: Literal["readme", "documentation", "changelog", "release_notes", "json"]
    suggested_record_type: Literal["article", "release", "structured_records"]
    detected_release_headings: int = Field(ge=0)
    review_notes: List[str]
    publish_automatically: bool = False
    human_review_required: bool = True


def score_product_readiness(evidence: ProductOnboardingEvidence) -> ProductSupportReadiness:
    score = 0
    blockers: List[str] = []
    signals: List[str] = []

    if evidence.profile_started:
        score += 10
    else:
        signals.append("Product onboarding profile has not been started.")

    if evidence.current_version_present:
        score += 10
    else:
        blockers.append("current_version_unset")

    score += min(10, evidence.component_count * 2)
    if evidence.component_count == 0:
        signals.append("No product components are mapped.")

    score += round((evidence.required_article_types_present / 4) * 35)
    if evidence.support_article_count == 0:
        blockers.append("no_support_articles")
    elif evidence.required_article_types_present < 4:
        signals.append("The recommended article set is incomplete.")

    if evidence.release_record_count > 0:
        score += 15
    else:
        blockers.append("no_release_record")

    if evidence.known_issue_count > 0 or evidence.known_issues_reviewed:
        score += 10
    else:
        signals.append("Known-issue review is incomplete.")

    if evidence.fresh_content_percent >= 80:
        score += 10
    elif evidence.fresh_content_percent >= 50:
        score += 5
    else:
        signals.append("Most support content is stale or unverified.")

    score = max(0, min(100, score))
    if score >= 80 and not blockers:
        state: Literal["not_started", "building", "review", "ready"] = "ready"
    elif score >= 60:
        state = "review"
    elif score >= 20:
        state = "building"
    else:
        state = "not_started"

    return ProductSupportReadiness(
        score=score,
        state=state,
        blockers=blockers,
        signals=signals,
    )


def plan_source_import(document: SourceDocument) -> ImportPlan:
    filename = document.filename.lower()
    content = document.content.lower()
    requested = document.source_type

    if requested != "auto":
        inferred = requested
    elif filename.endswith(".json"):
        inferred = "json"
    elif "changelog" in filename:
        inferred = "changelog"
    elif "release" in filename:
        inferred = "release_notes"
    elif "readme" in filename:
        inferred = "readme"
    else:
        inferred = "documentation"

    import re
    release_headings = len(re.findall(r"^##\s+(?:\[)?(?:v|version\s*)?\d+(?:\.\d+){1,3}", document.content, re.I | re.M))

    if inferred == "json":
        suggested = "structured_records"
    elif inferred in ("changelog", "release_notes"):
        suggested = "release"
    else:
        suggested = "article"

    notes: List[str] = ["Imported content should be created as draft or pending review."]
    if inferred in ("changelog", "release_notes") and release_headings == 0:
        notes.append("No semantic-version release headings were detected; review the generated release record manually.")
    if "password" in content or "api key" in content or "secret" in content:
        notes.append("Potential secret language was detected; redact sensitive material before import.")
    if len(document.content.encode("utf-8")) > 2_097_152:
        notes.append("The source exceeds the WordPress 2 MB import limit and should be split.")

    return ImportPlan(
        inferred_source_type=inferred,
        suggested_record_type=suggested,
        detected_release_headings=release_headings,
        review_notes=notes,
    )
