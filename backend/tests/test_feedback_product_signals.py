from app.feedback_product_signals import (
    ProductSignalClusterEvidence,
    ProductSignalEvidence,
    ProductSignalPortfolioEvidence,
    prioritize_product_signal_cluster,
    score_product_signal,
    summarize_product_signal_portfolio,
)


def test_low_evidence_is_not_overstated():
    result = score_product_signal(ProductSignalEvidence(product="canvas", feature_requests=1))
    assert result.signal_state == "insufficient_evidence"
    assert result.human_review_required is True
    assert result.automatic_roadmap_changes is False


def test_cross_signal_demand_reaches_critical_review():
    result = score_product_signal(
        ProductSignalEvidence(
            product="decision-studio",
            feature_requests=8,
            public_votes=15,
            article_feedback_negative=5,
            unresolved_searches=7,
            documentation_gaps=3,
            active_known_issues=2,
        )
    )
    assert result.signal_score >= 70
    assert result.signal_state == "critical_review"
    assert "review_documentation_and_search_experience" in result.recommended_actions
    assert "review_product_opportunity_evidence" in result.recommended_actions


def test_issue_demand_recommends_issue_review():
    result = score_product_signal(
        ProductSignalEvidence(product="site-intelligence", active_known_issues=2, failed_resolution_paths=3)
    )
    assert "review_known_issue_and_resolution_path" in result.recommended_actions


def test_privacy_boundaries_are_explicit():
    result = score_product_signal(ProductSignalEvidence(product="workbench", unresolved_searches=3))
    assert result.personal_identifiers_exposed is False
    assert result.raw_search_text_exposed is False
    assert result.private_case_content_exposed is False


def test_portfolio_summary_orders_priority():
    result = summarize_product_signal_portfolio(
        ProductSignalPortfolioEvidence(
            records=[
                ProductSignalEvidence(product="low", feature_requests=1),
                ProductSignalEvidence(product="high", unresolved_searches=8, documentation_gaps=3),
            ]
        )
    )
    assert result.products == 2
    assert result.highest_priority_products[0].product == "high"
    assert result.automatic_roadmap_changes is False


def test_cluster_priority_is_deterministic():
    result = prioritize_product_signal_cluster(
        ProductSignalClusterEvidence(
            signal_type="documentation_gap",
            evidence_count=6,
            severity=4,
            recency_days=5,
            linked_record_available=False,
        )
    )
    assert result.priority_state == "urgent_review"
    assert result.priority_score == 100
    assert result.automatic_record_creation is False
    assert result.recommended_action.endswith("_and_link_review_record")
