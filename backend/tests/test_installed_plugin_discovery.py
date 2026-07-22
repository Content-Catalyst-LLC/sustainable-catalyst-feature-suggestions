from app.installed_plugin_discovery import (
    PluginDiscoveryCandidate,
    PluginDiscoveryEvidence,
    PluginDiscoveryMatch,
    discovery_capabilities,
    validate_plugin_discovery,
)


def match(product_id: str = "product-support-feedback", *, active: bool = True, plugin_file: str = "sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php", strategy: str = "exact_plugin_file", confidence: int = 100, version: str = "7.2.0"):
    return PluginDiscoveryMatch(
        product_id=product_id,
        plugin_file=plugin_file,
        plugin_name="Sustainable Catalyst Product Support and Feedback Platform",
        version=version,
        text_domain="sustainable-catalyst-feature-suggestions",
        active=active,
        match_strategy=strategy,
        confidence=confidence,
        discovered_at="2026-07-22T15:00:00Z",
    )


def test_capabilities_preserve_governance_boundaries():
    capabilities = discovery_capabilities()
    assert capabilities["approved_registry_only"] is True
    assert capabilities["unknown_plugins_auto_registered"] is False
    assert capabilities["unknown_plugins_publicly_exposed"] is False
    assert capabilities["absolute_plugin_paths_publicly_exposed"] is False
    assert capabilities["manual_overrides_preserved"] is True
    assert capabilities["product_lock_supported"] is True
    assert capabilities["human_review_required"] is True


def test_valid_snapshot_counts_active_and_pending_records():
    candidate = PluginDiscoveryCandidate(
        plugin_file="catalyst-experimental/catalyst-experimental.php",
        name="Catalyst Experimental",
        version="0.1.0",
        author="Content Catalyst LLC",
        text_domain="catalyst-experimental",
        active=False,
    )
    result = validate_plugin_discovery(PluginDiscoveryEvidence(installed_plugin_count=4, matches=[match()], pending=[candidate]))
    assert result.valid is True
    assert result.matched_product_count == 1
    assert result.pending_candidate_count == 1
    assert result.active_match_count == 1
    assert result.inactive_match_count == 0


def test_duplicate_product_match_is_rejected():
    result = validate_plugin_discovery(PluginDiscoveryEvidence(
        installed_plugin_count=2,
        matches=[match(), match(plugin_file="another-support/another-support.php", strategy="approved_name_alias", confidence=80)],
    ))
    assert result.valid is False
    assert any(issue.code == "duplicate_product_match" for issue in result.issues)


def test_absolute_plugin_path_is_rejected():
    result = validate_plugin_discovery(PluginDiscoveryEvidence(installed_plugin_count=1, matches=[match(plugin_file="/var/www/wp-content/plugins/support/support.php")]))
    assert result.valid is False
    assert any(issue.code == "absolute_or_invalid_plugin_path" for issue in result.issues)


def test_installed_plugin_count_must_cover_discovery_records():
    candidate = PluginDiscoveryCandidate(plugin_file="catalyst-x/catalyst-x.php", name="Catalyst X")
    result = validate_plugin_discovery(PluginDiscoveryEvidence(installed_plugin_count=1, matches=[match()], pending=[candidate]))
    assert result.valid is False
    assert any(issue.code == "plugin_count_inconsistent" for issue in result.issues)


def test_inactive_approved_plugin_is_counted_without_error():
    result = validate_plugin_discovery(PluginDiscoveryEvidence(installed_plugin_count=1, matches=[match(active=False)]))
    assert result.valid is True
    assert result.active_match_count == 0
    assert result.inactive_match_count == 1
