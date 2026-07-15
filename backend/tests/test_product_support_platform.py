from app.product_support_platform import (
    ProductSupportEvidence,
    ReleaseReadinessEvidence,
    score_release_readiness,
    summarize_product_support,
)


def test_critical_issue_prioritizes_known_issues():
    result = summarize_product_support(ProductSupportEvidence(
        support_articles=20,
        active_known_issues=2,
        critical_known_issues=1,
        release_records=4,
    ))
    assert result.version == '4.0.0'
    assert result.support_state == 'incident'
    assert result.recommended_pathway == 'known_issues'
    assert result.private_case_storage is False


def test_stable_support_uses_knowledge_base():
    result = summarize_product_support(ProductSupportEvidence(
        support_articles=25,
        active_known_issues=0,
        release_records=5,
        public_ideas=4,
        open_surveys=1,
    ))
    assert result.support_state == 'stable'
    assert result.recommended_pathway == 'knowledge_base'
    assert result.public_resolution_coverage >= 80


def test_release_readiness_blocks_critical_issues():
    result = score_release_readiness(ReleaseReadinessEvidence(
        documentation_count=4,
        known_issue_count=3,
        unresolved_critical_issues=1,
        public_summary_present=True,
        support_note_present=True,
        release_date_present=True,
        changelog_present=True,
        product_context_present=True,
    ))
    assert result.state == 'not_ready'
    assert 'unresolved_critical_issues' in result.blockers


def test_complete_release_is_ready():
    result = score_release_readiness(ReleaseReadinessEvidence(
        documentation_count=4,
        known_issue_count=2,
        public_summary_present=True,
        support_note_present=True,
        release_date_present=True,
        changelog_present=True,
        product_context_present=True,
    ))
    assert result.score >= 80
    assert result.state == 'ready'
