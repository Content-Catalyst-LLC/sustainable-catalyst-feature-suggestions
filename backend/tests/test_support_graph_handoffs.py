from app.support_graph_handoffs import (
    HandoffPlanEvidence,
    SupportGraphEdge,
    SupportGraphEvidence,
    SupportGraphNode,
    SupportPathEvidence,
    build_support_graph,
    find_support_path,
    plan_platform_handoffs,
    verify_support_graph,
)


def nodes():
    return [
        SupportGraphNode(
            slug="decision-studio",
            name="Decision Studio",
            support_route="/support/?scfs_product=decision-studio",
            capabilities=["decision analysis", "scenario comparison"],
            article_count=8,
            known_issue_count=2,
            release_count=3,
            example_count=2,
            troubleshooting_count=2,
        ),
        SupportGraphNode(
            slug="catalyst-data",
            name="Catalyst Data",
            support_route="/support/?scfs_product=catalyst-data",
            capabilities=["datasets", "provenance", "data quality"],
            article_count=4,
            known_issue_count=1,
            release_count=2,
            example_count=1,
            troubleshooting_count=1,
        ),
        SupportGraphNode(
            slug="workbench",
            name="Workbench",
            support_route="/support/?scfs_product=workbench",
            capabilities=["calculations", "graphs", "simulations"],
            article_count=1,
        ),
    ]


def edges():
    return [
        SupportGraphEdge(source="decision-studio", target="catalyst-data", relationship="routes_to"),
        SupportGraphEdge(source="catalyst-data", target="workbench", relationship="integrates_with"),
    ]


def test_build_support_graph_scores_products():
    result = build_support_graph(SupportGraphEvidence(nodes=nodes(), edges=edges()))
    assert result.product_count == 3
    assert result.edge_count == 2
    assert result.average_coverage_score > 0
    assert result.products[0].slug == "catalyst-data"


def test_coverage_states_are_deterministic():
    result = build_support_graph(SupportGraphEvidence(nodes=nodes(), edges=edges()))
    by_slug = {product.slug: product for product in result.products}
    assert by_slug["decision-studio"].state in {"connected", "strong", "partial"}
    assert by_slug["workbench"].state in {"limited", "partial"}


def test_handoff_prefers_configured_route():
    result = plan_platform_handoffs(
        HandoffPlanEvidence(product="decision-studio", intent="data provenance", nodes=nodes(), edges=edges())
    )
    assert result.recommended_start is not None
    assert result.recommended_start.product == "catalyst-data"
    assert result.automatic_redirect is False


def test_handoff_matches_capabilities():
    result = plan_platform_handoffs(
        HandoffPlanEvidence(product="decision-studio", intent="simulation graphs", nodes=nodes(), edges=edges())
    )
    assert any(item.product == "workbench" for item in result.recommendations)


def test_shortest_path_crosses_products():
    result = find_support_path(SupportPathEvidence(source="decision-studio", target="workbench", edges=edges()))
    assert result.reachable is True
    assert result.path == ["decision-studio", "catalyst-data", "workbench"]


def test_integrity_flags_unknown_nodes_without_exposing_private_data():
    bad_edges = edges() + [SupportGraphEdge(source="workbench", target="missing-product")]
    result = verify_support_graph(SupportGraphEvidence(nodes=nodes(), edges=bad_edges))
    assert result.valid is True
    assert result.warning_count == 1
    assert len(result.checksum) == 64


def test_integrity_rejects_self_edges():
    bad_edges = edges() + [SupportGraphEdge(source="workbench", target="workbench")]
    result = verify_support_graph(SupportGraphEvidence(nodes=nodes(), edges=bad_edges))
    assert result.valid is False
    assert result.error_count == 1
