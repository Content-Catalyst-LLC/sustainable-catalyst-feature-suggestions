from app.public_support_integrations import (
    InstitutionalContractEvidence,
    PublicIntegrationReportEvidence,
    PublicSupportRecord,
    SupportEmbedPlanRequest,
    VersionVerificationRequest,
    evaluate_public_integration_health,
    plan_support_embed,
    validate_institutional_contract,
    verify_support_version,
)


def release(record_id='release-201', version='2.0.1'):
    return PublicSupportRecord(record_id=record_id, record_type='release', title=f'Decision Studio v{version}', product='decision-studio', versions=[version])


def article(record_id='article-1', version='2.0.1'):
    return PublicSupportRecord(record_id=record_id, record_type='support_article', title='Export a decision brief', product='decision-studio', versions=[version], components=['briefing'])


def test_version_verification_matches_release():
    result = verify_support_version(VersionVerificationRequest(product='decision-studio', requested_version='v2.0.1', releases=[release()]))
    assert result.verified is True
    assert result.state == 'verified'
    assert result.matched_release_ids == ['release-201']


def test_version_verification_requires_version():
    result = verify_support_version(VersionVerificationRequest(product='decision-studio', requested_version='', releases=[release()]))
    assert result.state == 'version_required'
    assert result.human_review_required is True


def test_embed_plan_filters_product_version_component():
    payload = SupportEmbedPlanRequest(product='decision-studio', requested_version='2.0.1', component='briefing', records=[article(), article('other', '1.0.0'), PublicSupportRecord(record_id='canvas', record_type='support_article', title='Canvas', product='catalyst-canvas')])
    result = plan_support_embed(payload)
    assert [item.record_id for item in result.records] == ['article-1']
    assert result.public_records_only is True


def test_embed_plan_filters_view():
    payload = SupportEmbedPlanRequest(product='decision-studio', view='releases', records=[article(), release()])
    result = plan_support_embed(payload)
    assert len(result.records) == 1
    assert result.records[0].record_type == 'release'


def test_contract_validation_accepts_safe_contract():
    result = validate_institutional_contract(InstitutionalContractEvidence(contract_key='public-support', schema_name='scfs-public-support-integration/1.0', transport='REST JSON', authentication='public read-only'))
    assert result.valid is True
    assert len(result.checksum_sha256) == 64


def test_contract_validation_rejects_private_content():
    result = validate_institutional_contract(InstitutionalContractEvidence(contract_key='unsafe', schema_name='x/1.0', transport='REST', authentication='key', private_case_content_exposed=True))
    assert result.valid is False
    assert 'private_case_content_must_not_be_exposed' in result.errors


def test_health_is_healthy_for_complete_integration():
    result = evaluate_public_integration_health(PublicIntegrationReportEvidence(product_count=12, contract_count=3, public_route_count=6, embed_enabled=True, public_api_enabled=True))
    assert result.ok is True
    assert result.state == 'healthy'
    assert result.score == 100


def test_health_blocks_private_case_exposure():
    result = evaluate_public_integration_health(PublicIntegrationReportEvidence(product_count=12, contract_count=3, public_route_count=6, embed_enabled=True, public_api_enabled=True, private_case_content_exposed=True))
    assert result.state == 'blocked'
    assert result.ok is False
