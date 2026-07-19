from app.support_analytics_documentation_effectiveness import (
    AnalyticsReportIntegrityEvidence,
    DocumentationEffectivenessEvidence,
    DocumentationEffectivenessPortfolioEvidence,
    DocumentationEffectivenessTrendEvidence,
    compare_documentation_effectiveness,
    evaluate_documentation_effectiveness,
    summarize_documentation_effectiveness_portfolio,
    verify_support_analytics_report,
)


def test_high_quality_documentation_is_effective():
    result = evaluate_documentation_effectiveness(
        DocumentationEffectivenessEvidence(
            product="decision-studio",
            searches=100,
            matched_searches=86,
            viewed_searches=78,
            feedback_responses=40,
            helpful_responses=35,
            published_articles=20,
            average_integrity_score=92,
            fresh_articles=19,
            known_issues=5,
            known_issues_with_guidance=5,
            releases=8,
            releases_with_documentation=8,
            documentation_gaps=4,
            resolved_documentation_gaps=3,
        )
    )
    assert result.score >= 80
    assert result.state == "effective"


def test_low_evidence_is_not_overstated():
    result = evaluate_documentation_effectiveness(DocumentationEffectivenessEvidence(product="canvas", published_articles=1))
    assert result.state == "insufficient_evidence"
    assert result.human_review_required is True


def test_poor_search_and_helpfulness_require_intervention():
    result = evaluate_documentation_effectiveness(
        DocumentationEffectivenessEvidence(
            product="lab",
            searches=20,
            matched_searches=2,
            viewed_searches=1,
            feedback_responses=10,
            helpful_responses=2,
            published_articles=10,
            average_integrity_score=45,
            fresh_articles=2,
            known_issues=6,
            known_issues_with_guidance=1,
            releases=5,
            releases_with_documentation=1,
            documentation_gaps=10,
            resolved_documentation_gaps=1,
        )
    )
    assert result.state == "intervention"
    assert "improve_search_relevance_and_no_results_recovery" in result.recommendations
    assert "review_low_helpfulness_articles" in result.recommendations


def test_privacy_and_governance_boundaries_are_explicit():
    result = evaluate_documentation_effectiveness(DocumentationEffectivenessEvidence(searches=10, matched_searches=8))
    assert result.administrator_only is True
    assert result.personal_identifiers_exposed is False
    assert result.raw_search_text_exposed is False
    assert result.private_case_content_exposed is False
    assert result.automatic_publication is False
    assert result.automatic_issue_resolution is False
    assert result.automatic_roadmap_changes is False


def test_portfolio_orders_lowest_effectiveness_first():
    summary = summarize_documentation_effectiveness_portfolio(
        DocumentationEffectivenessPortfolioEvidence(records=[
            DocumentationEffectivenessEvidence(product="strong", searches=20, matched_searches=20, viewed_searches=20, published_articles=10, fresh_articles=10),
            DocumentationEffectivenessEvidence(product="weak", searches=20, matched_searches=1, viewed_searches=1, published_articles=10, fresh_articles=1),
        ])
    )
    assert summary.products == 2
    assert summary.lowest_effectiveness_products[0].product == "weak"


def test_trend_comparison_detects_decline():
    result = compare_documentation_effectiveness(
        DocumentationEffectivenessTrendEvidence(
            product="site-intelligence",
            previous_score=80,
            current_score=70,
            previous_search_success=85,
            current_search_success=70,
            previous_helpfulness=90,
            current_helpfulness=78,
        )
    )
    assert result.direction == "declining"
    assert len(result.alerts) == 3


def test_report_integrity_is_deterministic():
    first = verify_support_analytics_report(AnalyticsReportIntegrityEvidence(records=[{"product": "b", "score": 60}, {"product": "a", "score": 80}]))
    second = verify_support_analytics_report(AnalyticsReportIntegrityEvidence(records=[{"product": "a", "score": 80}, {"product": "b", "score": 60}], expected_checksum=first.checksum))
    assert second.matches_expected is True
    assert second.record_count == 2
