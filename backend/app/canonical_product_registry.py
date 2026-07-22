"""Canonical Product Registry validation for v7.3.1."""

from __future__ import annotations

import re
from collections import Counter
from typing import List, Literal

from pydantic import BaseModel, Field

VERSION = "7.3.1"
SCHEMA = "scfs-canonical-product-registry/1.1"

ProductFamily = Literal[
    "foundation",
    "research-intelligence",
    "data-analysis",
    "creation-systems",
    "commercial",
]
VersionSource = Literal[
    "wordpress_plugin",
    "manual",
    "remote_manifest",
    "service_endpoint",
    "package_manifest",
]
ProductStatus = Literal[
    "current",
    "stable",
    "development",
    "preview",
    "private_beta",
    "release_candidate",
    "maintenance",
    "update_available",
    "inactive",
    "deprecated",
    "unavailable",
    "unverified",
]
ReleaseChannel = Literal[
    "stable",
    "development",
    "preview",
    "private_beta",
    "release_candidate",
    "maintenance",
]


class ProductRegistryRecord(BaseModel):
    canonical_id: str = Field(min_length=2, max_length=100)
    name: str = Field(min_length=2, max_length=200)
    short_name: str = Field(default="", max_length=120)
    family: ProductFamily
    product_type: str = Field(min_length=2, max_length=80)
    version_source: VersionSource
    installed_version: str = Field(default="", max_length=80)
    public_version: str = Field(default="", max_length=80)
    release_channel: ReleaseChannel = "stable"
    status: ProductStatus = "unverified"
    public_visible: bool = True
    homepage_visible: bool = True
    display_order: int = Field(default=999, ge=0, le=100000)
    product_url: str = ""
    documentation_url: str = ""
    support_url: str = ""
    release_notes_url: str = ""
    commercial: bool = False
    public_interest: bool = True
    last_verified_at: str = ""


class ProductRegistryEvidence(BaseModel):
    products: List[ProductRegistryRecord] = Field(min_length=1, max_length=250)


class ProductRegistryIssue(BaseModel):
    level: Literal["error", "warning"]
    product_id: str = ""
    code: str
    message: str


class ProductRegistryAssessment(BaseModel):
    version: str = VERSION
    schema_name: str = SCHEMA
    valid: bool
    product_count: int
    public_product_count: int
    homepage_product_count: int
    manual_product_count: int
    families: dict[str, int]
    version_sources: dict[str, int]
    issues: List[ProductRegistryIssue]
    human_review_required: bool = True


DEFAULT_PRODUCT_IDS = [
    "sustainable-catalyst-core",
    "product-support-feedback",
    "contact-engagement",
    "knowledge-library",
    "research-librarian",
    "site-intelligence",
    "decision-studio",
    "narrative-risk",
    "catalyst-data",
    "catalyst-analytics-r",
    "catalyst-finance",
    "global-impact-catalyst",
    "catalyst-canvas",
    "catalyst-grit",
    "workbench",
    "sustainable-catalyst-lab",
    "catalyst-intelligence",
]


def registry_capabilities() -> dict:
    return {
        "version": VERSION,
        "schema": SCHEMA,
        "source_of_truth": "product-support-feedback-platform",
        "families": [
            "foundation",
            "research-intelligence",
            "data-analysis",
            "creation-systems",
            "commercial",
        ],
        "active_version_sources": ["wordpress_plugin", "manual"],
        "reserved_version_sources": [
            "remote_manifest",
            "service_endpoint",
            "package_manifest",
        ],
        "canonical_id_immutable": True,
        "public_visibility_governed": True,
        "homepage_visibility_governed": True,
        "private_repository_fields_publicly_exposed": False,
        "automatic_publication": False,
        "human_review_required": True,
    }


def validate_product_registry(evidence: ProductRegistryEvidence) -> ProductRegistryAssessment:
    issues: list[ProductRegistryIssue] = []
    ids = [product.canonical_id for product in evidence.products]
    duplicate_ids = sorted(product_id for product_id, count in Counter(ids).items() if count > 1)
    for product_id in duplicate_ids:
        issues.append(
            ProductRegistryIssue(
                level="error",
                product_id=product_id,
                code="duplicate_canonical_id",
                message="Canonical product identifiers must be unique.",
            )
        )

    id_pattern = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
    orders = Counter(product.display_order for product in evidence.products)
    for product in evidence.products:
        if not id_pattern.fullmatch(product.canonical_id):
            issues.append(
                ProductRegistryIssue(
                    level="error",
                    product_id=product.canonical_id,
                    code="invalid_canonical_id",
                    message="Canonical IDs must use lowercase letters, numbers, and single hyphens.",
                )
            )
        if product.version_source == "manual" and not product.public_version:
            issues.append(
                ProductRegistryIssue(
                    level="warning",
                    product_id=product.canonical_id,
                    code="manual_version_missing",
                    message="Manually maintained products should provide a public version.",
                )
            )
        if product.version_source in {"remote_manifest", "service_endpoint", "package_manifest"}:
            issues.append(
                ProductRegistryIssue(
                    level="warning",
                    product_id=product.canonical_id,
                    code="reserved_version_source",
                    message="This version source is reserved until a later release activates it.",
                )
            )
        if product.homepage_visible and not product.public_visible:
            issues.append(
                ProductRegistryIssue(
                    level="error",
                    product_id=product.canonical_id,
                    code="homepage_requires_public_visibility",
                    message="Homepage-visible products must also be public registry products.",
                )
            )
        if product.commercial and product.public_interest:
            issues.append(
                ProductRegistryIssue(
                    level="warning",
                    product_id=product.canonical_id,
                    code="commercial_public_interest_overlap",
                    message="Confirm the product is intentionally marked both commercial and public-interest.",
                )
            )
        if orders[product.display_order] > 1:
            issues.append(
                ProductRegistryIssue(
                    level="warning",
                    product_id=product.canonical_id,
                    code="duplicate_display_order",
                    message="Duplicate display order values may produce name-based tie breaking.",
                )
            )

    missing_foundation = sorted(
        {
            "sustainable-catalyst-core",
            "product-support-feedback",
            "contact-engagement",
            "knowledge-library",
        }
        - set(ids)
    )
    for product_id in missing_foundation:
        issues.append(
            ProductRegistryIssue(
                level="error",
                product_id=product_id,
                code="required_foundation_product_missing",
                message="Required foundation products must remain in the canonical registry.",
            )
        )

    families = Counter(product.family for product in evidence.products)
    sources = Counter(product.version_source for product in evidence.products)
    return ProductRegistryAssessment(
        valid=not any(issue.level == "error" for issue in issues),
        product_count=len(evidence.products),
        public_product_count=sum(1 for product in evidence.products if product.public_visible),
        homepage_product_count=sum(1 for product in evidence.products if product.homepage_visible),
        manual_product_count=sum(1 for product in evidence.products if product.version_source == "manual"),
        families=dict(sorted(families.items())),
        version_sources=dict(sorted(sources.items())),
        issues=issues,
    )
