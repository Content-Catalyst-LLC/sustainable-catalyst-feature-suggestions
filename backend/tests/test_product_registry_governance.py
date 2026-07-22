from datetime import datetime, timezone

from app.canonical_product_registry import (
    ProductRegistryEvidence,
    ProductRegistryMigrationEvidence,
    ProductRegistryRecord,
    migrate_product_registry,
    registry_capabilities,
    validate_product_registry,
)


def record(product_id: str, **overrides):
    values = {
        "canonical_id": product_id,
        "name": product_id.replace("-", " ").title(),
        "family": "foundation",
        "console_screen": "foundation",
        "product_type": "wordpress_plugin",
        "version_source": "wordpress_plugin",
        "version_precedence": "discovered",
        "discovered_plugin_version": "1.2.3",
        "release_channel": "stable",
        "status": "current",
        "lifecycle_state": "active",
        "public_visible": True,
        "homepage_visible": True,
        "display_order": 10,
        "verification_source": "wordpress_plugin",
        "source_verified_at": datetime.now(timezone.utc).isoformat(),
    }
    values.update(overrides)
    return ProductRegistryRecord(**values)


def foundation():
    return [
        record("sustainable-catalyst-core", display_order=10),
        record("product-support-feedback", display_order=20),
        record("contact-engagement", display_order=30),
        record("knowledge-library", display_order=40),
    ]


def test_governance_capabilities_are_explicit_and_private():
    capabilities = registry_capabilities()
    assert capabilities["schema"] == "scfs-canonical-product-registry/2.0"
    assert capabilities["lifecycle_states"] == ["active", "planned", "maintenance", "superseded", "retired"]
    assert capabilities["version_precedence"] == ["manual", "discovered", "installed"]
    assert capabilities["integrity_reporting"] is True
    assert capabilities["schema_migration_supported"] is True
    assert capabilities["private_repository_fields_publicly_exposed"] is False


def test_console_screen_is_governed_separately_from_family():
    products = foundation()
    products.append(record("research-librarian", family="research-intelligence", console_screen="commercial", display_order=410))
    assessment = validate_product_registry(ProductRegistryEvidence(products=products))
    assert assessment.valid is True
    assert assessment.families["research-intelligence"] == 1
    assert assessment.console_screens["commercial"] == 1


def test_superseded_product_requires_registered_replacement():
    products = foundation()
    products.append(record("old-product", lifecycle_state="superseded", superseded_by="missing-product", homepage_visible=False, display_order=50))
    assessment = validate_product_registry(ProductRegistryEvidence(products=products))
    assert assessment.valid is False
    assert any(issue.code == "invalid_supersession_target" for issue in assessment.issues)


def test_manual_source_precedence_and_resolved_version():
    manual = record(
        "manual-product",
        family="commercial",
        console_screen="commercial",
        version_source="manual",
        version_precedence="manual",
        public_version="2.0.0",
        discovered_plugin_version="9.9.9",
        display_order=410,
    )
    assert manual.resolved_version() == "2.0.0"


def test_legacy_registry_migration_adds_governance_fields():
    raw = [
        {
            "canonical_id": "legacy-product",
            "name": "Legacy Product",
            "family": "creation-systems",
            "product_type": "wordpress_plugin",
            "version_source": "manual",
            "public_version": "1.0.0",
            "status": "maintenance",
            "public_visible": True,
            "homepage_visible": True,
            "display_order": 320,
        }
    ]
    result = migrate_product_registry(ProductRegistryMigrationEvidence(from_schema="scfs-canonical-product-registry/1.1", products=raw))
    migrated = result.products[0]
    assert result.to_schema == "scfs-canonical-product-registry/2.0"
    assert migrated.console_screen == "creation-systems"
    assert migrated.lifecycle_state == "maintenance"
    assert migrated.version_precedence == "manual"
    assert migrated.verification_source == "migration"


def test_release_intelligence_fields_survive_registry_migration():
    raw = [{
        "canonical_id": "release-aware-product",
        "name": "Release Aware Product",
        "family": "creation-systems",
        "product_type": "wordpress_plugin",
        "version_source": "manual",
        "public_version": "2.0.0",
        "status": "current",
        "public_visible": True,
        "homepage_visible": True,
        "display_order": 320,
        "previous_version": "1.9.0",
        "release_date": "2026-07-22",
        "change_summary": "Improved release evidence.",
        "validation_state": "validated",
        "documentation_state": "ready",
        "known_issue_count": 1,
    }]
    result = migrate_product_registry(ProductRegistryMigrationEvidence(from_schema="scfs-canonical-product-registry/2.0", products=raw))
    migrated = result.products[0]
    assert migrated.previous_version == "1.9.0"
    assert migrated.release_date == "2026-07-22"
    assert migrated.validation_state == "validated"
    assert migrated.documentation_state == "ready"
    assert migrated.known_issue_count == 1


def test_release_intelligence_governance_capabilities_are_explicit():
    capabilities = registry_capabilities()
    assert capabilities["release_intelligence_governed"] is True
    assert capabilities["previous_version_comparison"] is True
    assert capabilities["release_date_governed"] is True
    assert capabilities["known_issue_count_governed"] is True
