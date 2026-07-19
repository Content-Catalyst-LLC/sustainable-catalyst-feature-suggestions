from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import List, Literal

from pydantic import BaseModel, ConfigDict, Field


class PublicSupportRecord(BaseModel):
    record_id: str
    record_type: Literal['support_article', 'known_issue', 'release']
    title: str
    url: str = ''
    product: str
    versions: List[str] = Field(default_factory=list)
    components: List[str] = Field(default_factory=list)
    public: bool = True


class VersionVerificationRequest(BaseModel):
    product: str
    requested_version: str
    releases: List[PublicSupportRecord] = Field(default_factory=list)


class VersionVerificationResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_: str = Field(default='scfs-support-version-verification/1.0', alias='schema')
    version: str = '5.9.0'
    product: str
    requested_version: str
    normalized_version: str
    state: Literal['verified', 'not_found', 'version_required', 'unknown_product']
    verified: bool
    matched_release_ids: List[str] = Field(default_factory=list)
    human_review_required: bool


class SupportEmbedPlanRequest(BaseModel):
    product: str
    requested_version: str = ''
    component: str = ''
    view: Literal['compact', 'standard', 'articles', 'issues', 'releases'] = 'compact'
    limit: int = Field(default=6, ge=1, le=20)
    records: List[PublicSupportRecord] = Field(default_factory=list)


class SupportEmbedPlanResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_: str = Field(default='scfs-support-embed/1.0', alias='schema')
    version: str = '5.9.0'
    product: str
    requested_version: str
    component: str
    view: str
    records: List[PublicSupportRecord]
    support_center_query: dict
    public_records_only: bool = True


class InstitutionalContractEvidence(BaseModel):
    contract_key: str
    schema_name: str
    transport: str
    authentication: str
    public_records_only: bool = True
    read_only: bool = True
    personal_identifiers_exposed: bool = False
    private_case_content_exposed: bool = False
    private_documents_exposed: bool = False
    versioned: bool = True


class InstitutionalContractResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_: str = Field(default='scfs-institutional-support-integration/1.0', alias='schema')
    version: str = '5.9.0'
    valid: bool
    errors: List[str] = Field(default_factory=list)
    warnings: List[str] = Field(default_factory=list)
    checksum_sha256: str
    human_review_required: bool = True


class PublicIntegrationReportEvidence(BaseModel):
    product_count: int = Field(ge=0)
    contract_count: int = Field(ge=0)
    public_route_count: int = Field(ge=0)
    embed_enabled: bool
    public_api_enabled: bool
    private_case_content_exposed: bool = False


class PublicIntegrationReportResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)
    schema_: str = Field(default='scfs-public-support-integration-health/1.0', alias='schema')
    version: str = '5.9.0'
    ok: bool
    state: Literal['healthy', 'review', 'blocked']
    score: int = Field(ge=0, le=100)
    findings: List[str] = Field(default_factory=list)
    checked_at: str


def _normalize_version(value: str) -> str:
    return value.strip().lower().removeprefix('v')


def verify_support_version(payload: VersionVerificationRequest) -> VersionVerificationResult:
    requested = _normalize_version(payload.requested_version)
    product = payload.product.strip().lower().replace(' ', '-')
    matched: list[str] = []
    for record in payload.releases:
        if record.record_type != 'release' or record.product.strip().lower().replace(' ', '-') != product:
            continue
        haystack = ' '.join([record.title, *record.versions]).lower()
        if requested and (requested in haystack or f'v{requested}' in haystack):
            matched.append(record.record_id)
    if not product:
        state = 'unknown_product'
    elif not requested:
        state = 'version_required'
    elif matched:
        state = 'verified'
    else:
        state = 'not_found'
    return VersionVerificationResult(
        product=product,
        requested_version=payload.requested_version,
        normalized_version=requested,
        state=state,
        verified=state == 'verified',
        matched_release_ids=sorted(set(matched)),
        human_review_required=state != 'verified',
    )


def plan_support_embed(payload: SupportEmbedPlanRequest) -> SupportEmbedPlanResult:
    product = payload.product.strip().lower().replace(' ', '-')
    records = [record for record in payload.records if record.public and record.product.strip().lower().replace(' ', '-') == product]
    if payload.requested_version:
        requested = _normalize_version(payload.requested_version)
        records = [record for record in records if not record.versions or any(requested == _normalize_version(value) for value in record.versions)]
    if payload.component:
        component = payload.component.strip().lower()
        records = [record for record in records if not record.components or any(component == value.strip().lower() for value in record.components)]
    type_map = {'articles': 'support_article', 'issues': 'known_issue', 'releases': 'release'}
    if payload.view in type_map:
        records = [record for record in records if record.record_type == type_map[payload.view]]
    records = sorted(records, key=lambda item: (item.record_type, item.title.lower(), item.record_id))[: payload.limit]
    query = {'product': product}
    if payload.requested_version:
        query['version'] = payload.requested_version
    if payload.component:
        query['component'] = payload.component
    return SupportEmbedPlanResult(
        product=product,
        requested_version=payload.requested_version,
        component=payload.component,
        view=payload.view,
        records=records,
        support_center_query=query,
    )


def validate_institutional_contract(payload: InstitutionalContractEvidence) -> InstitutionalContractResult:
    errors: list[str] = []
    warnings: list[str] = []
    if not payload.contract_key.strip():
        errors.append('contract_key_required')
    if not payload.schema_name.strip():
        errors.append('schema_name_required')
    if not payload.transport.strip():
        errors.append('transport_required')
    if not payload.authentication.strip():
        errors.append('authentication_required')
    if not payload.public_records_only:
        errors.append('public_records_only_required')
    if not payload.read_only:
        errors.append('read_only_contract_required')
    if payload.personal_identifiers_exposed:
        errors.append('personal_identifiers_must_not_be_exposed')
    if payload.private_case_content_exposed:
        errors.append('private_case_content_must_not_be_exposed')
    if payload.private_documents_exposed:
        errors.append('private_documents_must_not_be_exposed')
    if not payload.versioned:
        warnings.append('versioned_contract_recommended')
    canonical = json.dumps(payload.model_dump(), sort_keys=True, separators=(',', ':'))
    return InstitutionalContractResult(
        valid=not errors,
        errors=errors,
        warnings=warnings,
        checksum_sha256=hashlib.sha256(canonical.encode()).hexdigest(),
    )


def evaluate_public_integration_health(payload: PublicIntegrationReportEvidence) -> PublicIntegrationReportResult:
    findings: list[str] = []
    score = 100
    if payload.private_case_content_exposed:
        findings.append('private_case_content_exposed')
        score -= 70
    if not payload.public_api_enabled:
        findings.append('public_api_disabled')
        score -= 15
    if not payload.embed_enabled:
        findings.append('embeds_disabled')
        score -= 10
    if payload.contract_count < 3:
        findings.append('contract_coverage_incomplete')
        score -= 15
    if payload.public_route_count < 6:
        findings.append('public_route_coverage_incomplete')
        score -= 10
    if payload.product_count == 0:
        findings.append('product_catalog_empty')
        score -= 20
    score = max(0, min(100, score))
    state: Literal['healthy', 'review', 'blocked'] = 'healthy' if score >= 85 else 'review' if score >= 50 else 'blocked'
    return PublicIntegrationReportResult(ok=state != 'blocked', state=state, score=score, findings=findings, checked_at=datetime.now(timezone.utc).isoformat())
