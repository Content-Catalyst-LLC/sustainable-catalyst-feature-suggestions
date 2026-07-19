from app.support_article_integrity import (
    SupportArticleIntegrityEvidence,
    assess_support_article_integrity,
)


def complete_article() -> SupportArticleIntegrityEvidence:
    return SupportArticleIntegrityEvidence(
        title="Validate a Decision Studio export bundle",
        content_text=" ".join(["verified workflow evidence"] * 180),
        excerpt_or_summary="Verify the export bundle and its manifest before publication.",
        products=["Decision Studio"],
        versions=["v2.0.1"],
        components=["Export Studio"],
        article_types=["How-to"],
        collections=["Decision Studio"],
        verified_version="v2.0.1",
        headings=["Goal", "Requirements", "Procedure", "Troubleshooting"],
        heading_levels=[2, 2, 2, 2],
        required_sections=["Goal", "Requirements", "Procedure", "Troubleshooting"],
        related_release_count=1,
        related_article_count=2,
        days_since_updated=4,
        published=True,
    )


def test_complete_article_is_ready():
    result = assess_support_article_integrity(complete_article())
    assert result.version == "5.2.8"
    assert result.state == "ready"
    assert result.score == 100
    assert result.errors == 0
    assert result.stale is False


def test_missing_metadata_blocks_publication_readiness():
    result = assess_support_article_integrity(
        SupportArticleIntegrityEvidence(
            title="Draft",
            content_text="Too short",
            headings=[],
            heading_levels=[],
        )
    )
    codes = {item.code for item in result.issues}
    assert result.state == "blocked"
    assert result.errors >= 6
    assert "summary_missing" in codes
    assert "product_missing" in codes
    assert "version_missing" in codes
    assert "verified_version_missing" in codes


def test_heading_and_accessibility_checks_are_advisory():
    evidence = complete_article().model_copy(
        update={
            "heading_levels": [1, 3, 4, 2],
            "images_missing_alt": 2,
            "tables_missing_headers": 1,
            "invalid_link_count": 1,
        }
    )
    result = assess_support_article_integrity(evidence)
    codes = {item.code for item in result.issues}
    assert "content_h1_present" in codes
    assert "heading_level_jump" in codes
    assert "image_alt_missing" in codes
    assert "table_headers_missing" in codes
    assert "link_invalid" in codes
    assert result.automatic_publication is False
    assert result.human_review_required is True


def test_stale_and_overdue_content_needs_work():
    evidence = complete_article().model_copy(
        update={"days_since_updated": 365, "review_overdue": True}
    )
    result = assess_support_article_integrity(evidence)
    assert result.stale is True
    assert result.state == "needs_work"
    assert any(item.code == "review_overdue" for item in result.issues)


def test_integrity_api_contract():
    from fastapi.testclient import TestClient
    from app.main import app

    client = TestClient(app)
    capabilities = client.get("/v1/support-article-integrity/capabilities")
    assert capabilities.status_code == 200
    assert capabilities.json()["version"] == "5.2.8"
    response = client.post(
        "/v1/support-article-integrity/assess",
        json=complete_article().model_dump(),
    )
    assert response.status_code == 200
    assert response.json()["state"] == "ready"
