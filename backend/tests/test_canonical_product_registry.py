from app.canonical_product_registry import (
    DEFAULT_PRODUCT_IDS,
    ProductRegistryEvidence,
    ProductRegistryRecord,
    registry_capabilities,
    validate_product_registry,
)


def product(product_id: str, *, family: str = "foundation", source: str = "wordpress_plugin", public: bool = True, homepage: bool = True, version: str = "1.0.0", order: int = 10):
    return ProductRegistryRecord(
        canonical_id=product_id,
        name=product_id.replace("-", " ").title(),
        family=family,
        product_type="wordpress_plugin",
        version_source=source,
        public_version=version,
        release_channel="stable" if source == "wordpress_plugin" else "development",
        status="current" if source == "wordpress_plugin" else "development",
        public_visible=public,
        homepage_visible=homepage,
        display_order=order,
    )


def foundation_products():
    return [
        product("sustainable-catalyst-core", order=10),
        product("product-support-feedback", order=20),
        product("contact-engagement", order=30),
        product("knowledge-library", order=40),
    ]


def test_default_catalog_contains_required_products_and_manual_commercial_platform():
    assert len(DEFAULT_PRODUCT_IDS) == 17
    assert "sustainable-catalyst-core" in DEFAULT_PRODUCT_IDS
    assert "product-support-feedback" in DEFAULT_PRODUCT_IDS
    assert "contact-engagement" in DEFAULT_PRODUCT_IDS
    assert "knowledge-library" in DEFAULT_PRODUCT_IDS
    assert "catalyst-intelligence" in DEFAULT_PRODUCT_IDS


def test_valid_registry_has_no_errors():
    products = foundation_products()
    products.append(product("catalyst-intelligence", family="commercial", source="manual", version="0.23.1", order=410))
    assessment = validate_product_registry(ProductRegistryEvidence(products=products))
    assert assessment.valid is True
    assert assessment.product_count == 5
    assert assessment.manual_product_count == 1
    assert not [issue for issue in assessment.issues if issue.level == "error"]


def test_homepage_visibility_requires_public_visibility():
    products = foundation_products()
    products[-1] = product("knowledge-library", public=False, homepage=True, order=40)
    assessment = validate_product_registry(ProductRegistryEvidence(products=products))
    assert assessment.valid is False
    assert any(issue.code == "homepage_requires_public_visibility" for issue in assessment.issues)


def test_capabilities_preserve_human_review_and_private_boundaries():
    capabilities = registry_capabilities()
    assert capabilities["automatic_publication"] is False
    assert capabilities["human_review_required"] is True
    assert capabilities["private_repository_fields_publicly_exposed"] is False
    assert capabilities["active_version_sources"] == ["wordpress_plugin", "manual"]
