"""Release board projection and validation for v7.3.0."""

from __future__ import annotations

from collections import defaultdict
from typing import List, Literal

from pydantic import BaseModel, Field

VERSION = "7.3.0"
SCHEMA = "scfs-release-board/1.0"

Family = Literal[
    "foundation",
    "research-intelligence",
    "data-analysis",
    "creation-systems",
    "commercial",
]
Layout = Literal["blackboard", "compact", "directory"]
Context = Literal["homepage", "directory", "generic"]
InactiveMode = Literal["hide", "show"]


class ReleaseBoardProduct(BaseModel):
    canonical_id: str = Field(min_length=2, max_length=100)
    name: str = Field(min_length=2, max_length=200)
    short_name: str = Field(default="", max_length=120)
    family: Family
    version: str = Field(default="", max_length=80)
    status: str = Field(default="unverified", max_length=80)
    display_order: int = Field(default=999, ge=0, le=100000)
    public_visible: bool = True
    homepage_visible: bool = True
    product_url: str = ""
    release_notes_url: str = ""
    version_source: str = Field(default="manual", max_length=80)


class ReleaseBoardProjectionRequest(BaseModel):
    products: List[ReleaseBoardProduct] = Field(default_factory=list, max_length=250)
    layout: Layout = "blackboard"
    context: Context = "homepage"
    groups: List[Family] = Field(default_factory=list, max_length=5)
    product_ids: List[str] = Field(default_factory=list, max_length=250)
    limit: int = Field(default=0, ge=0, le=250)
    inactive: InactiveMode = "hide"


class ReleaseBoardGroup(BaseModel):
    family: Family
    product_count: int
    products: List[ReleaseBoardProduct]


class ReleaseBoardProjection(BaseModel):
    version: str = VERSION
    schema_name: str = SCHEMA
    layout: Layout
    context: Context
    total_products: int
    group_count: int
    groups: List[ReleaseBoardGroup]
    installed_and_manual_versions_combined: bool = True
    private_plugin_paths_exposed: bool = False
    private_repository_metadata_exposed: bool = False


FAMILY_ORDER: list[Family] = [
    "foundation",
    "research-intelligence",
    "data-analysis",
    "creation-systems",
    "commercial",
]


def release_board_capabilities() -> dict:
    return {
        "version": VERSION,
        "schema": SCHEMA,
        "shortcode": "sc_release_board",
        "layouts": ["blackboard", "compact", "directory"],
        "contexts": ["homepage", "directory", "generic"],
        "canonical_registry_source": True,
        "installed_and_manual_versions_combined": True,
        "homepage_visibility_governed": True,
        "private_plugin_paths_exposed": False,
        "private_repository_metadata_exposed": False,
        "semantic_list_output": True,
        "human_review_required": True,
    }


def project_release_board(payload: ReleaseBoardProjectionRequest) -> ReleaseBoardProjection:
    allowed_groups = set(payload.groups)
    allowed_products = set(payload.product_ids)
    filtered: list[ReleaseBoardProduct] = []
    seen: set[str] = set()

    for product in payload.products:
        if product.canonical_id in seen:
            continue
        seen.add(product.canonical_id)
        if not product.public_visible:
            continue
        if payload.context == "homepage" and not product.homepage_visible:
            continue
        if allowed_groups and product.family not in allowed_groups:
            continue
        if allowed_products and product.canonical_id not in allowed_products:
            continue
        if payload.inactive == "hide" and product.status == "inactive":
            continue
        filtered.append(product)

    filtered.sort(key=lambda product: (product.display_order, product.name.casefold(), product.canonical_id))
    if payload.limit:
        filtered = filtered[: payload.limit]

    grouped: dict[str, list[ReleaseBoardProduct]] = defaultdict(list)
    for product in filtered:
        grouped[product.family].append(product)

    groups = [
        ReleaseBoardGroup(
            family=family,
            product_count=len(grouped[family]),
            products=grouped[family],
        )
        for family in FAMILY_ORDER
        if grouped.get(family)
    ]

    return ReleaseBoardProjection(
        layout=payload.layout,
        context=payload.context,
        total_products=len(filtered),
        group_count=len(groups),
        groups=groups,
    )
