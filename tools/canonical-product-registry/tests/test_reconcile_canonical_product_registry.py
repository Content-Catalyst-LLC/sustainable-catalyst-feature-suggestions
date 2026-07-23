import importlib.util
import json
import tempfile
import unittest
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
MODULE_PATH = HERE.parent / "reconcile_canonical_product_registry.py"
CATALOG_PATH = HERE.parent / "canonical-product-catalog-v1.json"
FIXTURE_PATH = HERE / "fixtures" / "legacy-registry.json"

spec = importlib.util.spec_from_file_location("registry_reconciler", MODULE_PATH)
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
assert spec.loader is not None
spec.loader.exec_module(module)


class ReconcileTests(unittest.TestCase):
    def setUp(self):
        catalog = json.loads(CATALOG_PATH.read_text(encoding="utf-8"))
        self.profiles, _ = module.catalog_profiles(catalog)
        self.records = json.loads(FIXTURE_PATH.read_text(encoding="utf-8"))

    def test_merges_legacy_ids_and_seeds_all_products(self):
        output, report = module.reconcile_registry(
            self.records,
            self.profiles,
            timestamp="2026-07-22T23:59:00Z",
        )
        self.assertEqual(len(self.profiles) + 1, len(output))
        self.assertIn("sustainable-catalyst-core", output)
        self.assertNotIn("platform-core", output)
        self.assertEqual("2.0.0", output["sustainable-catalyst-core"]["public_version"])
        self.assertEqual("Keep this administrator note.", output["sustainable-catalyst-core"]["manual_notes"])
        self.assertIn("platform-core", output["sustainable-catalyst-core"]["legacy_names"])
        self.assertIn("product-support-feedback", output)
        self.assertNotIn("feature-suggestions", output)
        self.assertEqual("7.5.4", output["product-support-feedback"]["public_version"])
        self.assertIn("Old Feedback Tool", output["product-support-feedback"]["legacy_names"])
        self.assertIn("contact-engagement", output)
        self.assertNotIn("contact-and-engagement", output)
        self.assertIn("sustainable-catalyst-lab", output)
        self.assertNotIn("research-lab", output)
        self.assertIn("narrative-risk", output)
        self.assertNotIn("catalyst-narrative-risk", output)
        self.assertIn("custom-private-tool", output)
        self.assertIn("custom-private-tool", report.unknown_records)
        self.assertGreaterEqual(len(report.seeded_products), 10)

    def test_drop_unknown_is_explicit(self):
        output, report = module.reconcile_registry(
            self.records,
            self.profiles,
            preserve_unknown=False,
            timestamp="2026-07-22T23:59:00Z",
        )
        self.assertNotIn("custom-private-tool", output)
        self.assertIn("custom-private-tool", report.unknown_records)

    def test_repository_version_populates_manual_package_product(self):
        output, _ = module.reconcile_registry(
            {},
            self.profiles,
            repository_versions={"catalyst-analytics-r": "2.0.0"},
            timestamp="2026-07-22T23:59:00Z",
        )
        record = output["catalyst-analytics-r"]
        self.assertEqual("2.0.0", record["public_version"])
        self.assertEqual("2.0.0", record["installed_version"])
        self.assertEqual("package_manifest", record["verification_source"])

    def test_offline_cli_is_dry_run_by_default(self):
        with tempfile.TemporaryDirectory() as directory:
            output_path = Path(directory) / "out.json"
            report_path = Path(directory) / "report.json"
            exit_code = module.main([
                "--input", str(FIXTURE_PATH),
                "--catalog", str(CATALOG_PATH),
                "--output", str(output_path),
                "--report", str(report_path),
                "--quiet",
            ])
            self.assertEqual(0, exit_code)
            self.assertTrue(output_path.is_file())
            report = json.loads(report_path.read_text(encoding="utf-8"))
            self.assertFalse(report["applied"])


if __name__ == "__main__":
    unittest.main()
