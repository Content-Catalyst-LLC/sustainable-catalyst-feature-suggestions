from app.unified_support_search import (
    UnifiedSupportRecord,
    UnifiedSupportSearchRequest,
    search_unified_support,
)


def records():
    return [
        UnifiedSupportRecord(
            record_id="issue-1",
            kind="known_issue",
            title="Decision Studio export fails after upgrade",
            summary="Exports can fail after upgrading to version 2.0.1.",
            products=["decision-studio"],
            product_versions=["2.0.1"],
            components=["export"],
            status="investigating",
            severity="high",
        ),
        UnifiedSupportRecord(
            record_id="article-1",
            kind="support_article",
            title="Repair Decision Studio export workflows",
            summary="Verify the current release and rebuild the export package.",
            content="Troubleshooting steps for failed PDF and ZIP exports.",
            products=["decision-studio"],
            product_versions=["2.0.1"],
            components=["export"],
        ),
        UnifiedSupportRecord(
            record_id="release-1",
            kind="release",
            title="Decision Studio 2.0.1",
            summary="Compatibility and export repair release.",
            products=["decision-studio"],
            product_versions=["2.0.1"],
        ),
        UnifiedSupportRecord(
            record_id="idea-1",
            kind="public_suggestion",
            title="Add export package verification",
            summary="Public idea for preflight checks.",
            products=["decision-studio"],
            components=["export"],
        ),
    ]


def test_unified_search_prioritizes_current_known_issue():
    result = search_unified_support(UnifiedSupportSearchRequest(
        query="Decision Studio export fails after upgrade",
        product="decision-studio",
        product_version="2.0.1",
        component="export",
        records=records(),
    ))
    assert result.result_count >= 2
    assert result.groups.known_issues[0].record_id == "issue-1"
    assert result.journey[0].status == "start_here"
    assert result.start_step == "known_issues"
    assert result.personal_data_stored is False
    assert result.automatic_case_creation is False


def test_unified_search_uses_support_article_when_no_issue_matches():
    result = search_unified_support(UnifiedSupportSearchRequest(
        query="rebuild PDF package troubleshooting",
        records=records()[1:],
    ))
    assert result.groups.support_articles
    assert result.start_step == "support_articles"


def test_unified_search_no_match_recommends_private_support():
    result = search_unified_support(UnifiedSupportSearchRequest(
        query="quantum banana telemetry",
        records=records(),
    ))
    assert result.resolution_state == "no_match"
    assert result.result_count == 0
    assert result.start_step == "private_support"
    assert result.journey[-1].status == "recommended"


def test_unified_search_limits_each_group():
    duplicates = [
        UnifiedSupportRecord(
            record_id=f"article-{i}",
            kind="support_article",
            title=f"Export guide {i}",
            summary="Export troubleshooting",
        ) for i in range(10)
    ]
    result = search_unified_support(UnifiedSupportSearchRequest(
        query="export",
        records=duplicates,
        limit_per_group=3,
    ))
    assert len(result.groups.support_articles) == 3


def test_schema_and_human_review_contract():
    result = search_unified_support(UnifiedSupportSearchRequest(query="export", records=records()))
    payload = result.model_dump(by_alias=True)
    assert payload["schema"] == "scfs-unified-support-search/1.0"
    assert payload["journey_schema"] == "scfs-support-resolution-journey/1.0"
    assert payload["version"] == "5.3.0"
    assert payload["human_review_required"] is True
