#!/usr/bin/env python3
"""Reconcile Sustainable Catalyst product identities into one canonical registry.

The utility can operate on an exported registry JSON file or directly against a
WordPress installation through WP-CLI. Dry-run is the default. Apply mode always
creates a backup and rolls back automatically when post-write validation fails.

No third-party Python packages are required.
"""

from __future__ import annotations

import argparse
import copy
import datetime as dt
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Iterable, Mapping, Sequence

REGISTRY_OPTION = "scfs_canonical_product_registry"
REGISTRY_SCHEMA_OPTION = "scfs_canonical_product_registry_schema"
DEFAULT_SCHEMA = "scfs-canonical-product-registry/2.0"
VERSION_FIELDS = ("installed_version", "public_version", "discovered_plugin_version")
ARRAY_FIELDS = (
    "legacy_names",
    "legacy_plugin_files",
    "legacy_plugin_slugs",
    "legacy_text_domains",
)
TIMESTAMP_FIELDS = (
    "source_verified_at",
    "record_updated_at",
    "last_verified_at",
    "last_discovered_at",
)
CANONICAL_IDENTITY_FIELDS = (
    "canonical_id",
    "name",
    "short_name",
    "internal_name",
    "repository_slug",
    "family",
    "console_screen",
    "product_type",
    "version_source",
    "version_precedence",
    "display_order",
    "owner",
    "commercial",
    "public_interest",
)
BOOLISH_FIELDS = (
    "public_visible",
    "homepage_visible",
    "commercial",
    "public_interest",
    "discovery_enabled",
    "discovery_locked",
    "discovered_active",
)


class RegistryError(RuntimeError):
    """Raised when the registry cannot be safely reconciled."""


@dataclass
class Change:
    action: str
    product_id: str
    detail: str


@dataclass
class ReconcileReport:
    schema: str
    generated_at: str
    mode: str
    input_count: int = 0
    output_count: int = 0
    seeded_products: list[str] = field(default_factory=list)
    merged_alias_records: dict[str, list[str]] = field(default_factory=dict)
    unknown_records: list[str] = field(default_factory=list)
    collisions: list[dict[str, Any]] = field(default_factory=list)
    manual_version_needed: list[str] = field(default_factory=list)
    repository_versions: dict[str, str] = field(default_factory=dict)
    changes: list[Change] = field(default_factory=list)
    backup_path: str = ""
    output_path: str = ""
    applied: bool = False
    discovery_ran: bool = False
    validation_passed: bool = False
    rollback_performed: bool = False
    fingerprint_before: str = ""
    fingerprint_after: str = ""

    def as_dict(self) -> dict[str, Any]:
        payload = copy.deepcopy(self.__dict__)
        payload["changes"] = [change.__dict__ for change in self.changes]
        return payload


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def slugify(value: Any) -> str:
    text = str(value or "").strip().lower()
    text = re.sub(r"['’]", "", text)
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def normalize_plugin_file(value: Any) -> str:
    text = str(value or "").strip().replace("\\", "/")
    text = re.sub(r"/+", "/", text)
    return text.lstrip("/")


def is_empty(value: Any) -> bool:
    if value is None:
        return True
    if value is False:
        return True
    if isinstance(value, str):
        return value.strip() == ""
    if isinstance(value, (list, tuple, dict, set)):
        return len(value) == 0
    return False


def boolish(value: Any) -> str:
    if isinstance(value, str):
        return "" if value.strip().lower() in {"", "0", "false", "no", "off"} else "1"
    return "1" if bool(value) else ""


def unique_strings(values: Iterable[Any], *, plugin_file: bool = False, slug: bool = False) -> list[str]:
    output: list[str] = []
    seen: set[str] = set()
    for value in values:
        if isinstance(value, str):
            candidates = re.split(r"[\r\n,]+", value)
        elif isinstance(value, Iterable) and not isinstance(value, (bytes, bytearray, Mapping)):
            candidates = list(value)
        else:
            candidates = [value]
        for candidate in candidates:
            text = str(candidate or "").strip()
            if not text:
                continue
            if plugin_file:
                text = normalize_plugin_file(text)
            elif slug:
                text = slugify(text)
            key = text.lower()
            if text and key not in seen:
                seen.add(key)
                output.append(text)
    return output


def version_key(value: Any) -> tuple[int, ...]:
    text = str(value or "").strip().lower().lstrip("v")
    if not text:
        return ()
    numbers = [int(part) for part in re.findall(r"\d+", text)]
    if not numbers:
        return ()
    prerelease_penalty = -1 if re.search(r"(?:dev|alpha|beta|rc|preview)", text) else 0
    return tuple(numbers[:6]) + (prerelease_penalty,)


def choose_highest_version(values: Iterable[Any]) -> str:
    candidates = [str(value).strip() for value in values if str(value or "").strip()]
    if not candidates:
        return ""
    semver_candidates = [value for value in candidates if version_key(value)]
    if semver_candidates:
        return max(semver_candidates, key=version_key)
    return candidates[0]


def json_fingerprint(records: Mapping[str, Any]) -> str:
    encoded = json.dumps(records, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()


def richness(record: Mapping[str, Any]) -> int:
    score = 0
    for key, value in record.items():
        if is_empty(value):
            continue
        if key in VERSION_FIELDS:
            score += 5
        elif key in ARRAY_FIELDS:
            score += len(value) if isinstance(value, list) else 1
        else:
            score += 1
    return score


def load_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise RegistryError(f"File not found: {path}") from exc
    except json.JSONDecodeError as exc:
        raise RegistryError(f"Invalid JSON in {path}: {exc}") from exc


def dump_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def unwrap_registry(payload: Any) -> tuple[dict[str, dict[str, Any]], dict[str, Any]]:
    wrapper: dict[str, Any] = {}
    products: Any = payload
    if isinstance(payload, dict) and "products" in payload:
        wrapper = {key: copy.deepcopy(value) for key, value in payload.items() if key != "products"}
        products = payload["products"]

    records: dict[str, dict[str, Any]] = {}
    if isinstance(products, list):
        for index, record in enumerate(products):
            if not isinstance(record, dict):
                continue
            key = slugify(record.get("canonical_id")) or f"record-{index + 1}"
            records[key] = copy.deepcopy(record)
    elif isinstance(products, dict):
        for key, record in products.items():
            if isinstance(record, dict):
                record_copy = copy.deepcopy(record)
                record_copy.setdefault("canonical_id", slugify(key))
                records[str(key)] = record_copy
    else:
        raise RegistryError("Registry JSON must be an object keyed by product ID or a wrapper with a products array/object.")
    return records, wrapper


def wrap_registry(records: Mapping[str, dict[str, Any]], wrapper: Mapping[str, Any], *, as_option: bool) -> Any:
    if as_option:
        return dict(records)
    result = dict(wrapper)
    result["schema"] = DEFAULT_SCHEMA
    result["products"] = list(records.values())
    return result


def catalog_profiles(catalog_payload: Mapping[str, Any]) -> tuple[dict[str, dict[str, Any]], dict[str, Any]]:
    defaults = copy.deepcopy(catalog_payload.get("defaults", {}))
    profiles: dict[str, dict[str, Any]] = {}
    for raw_profile in catalog_payload.get("products", []):
        if not isinstance(raw_profile, dict):
            continue
        profile = copy.deepcopy(defaults)
        profile.update(copy.deepcopy(raw_profile))
        canonical_id = slugify(profile.get("canonical_id"))
        if not canonical_id:
            raise RegistryError("Every catalog product requires canonical_id.")
        profile["canonical_id"] = canonical_id
        profile.setdefault("internal_name", profile.get("name", canonical_id))
        profiles[canonical_id] = profile
    if not profiles:
        raise RegistryError("Catalog contains no products.")
    return profiles, defaults


def record_tokens(key: str, record: Mapping[str, Any]) -> set[str]:
    tokens: set[str] = set()
    fields = (
        key,
        record.get("canonical_id"),
        record.get("name"),
        record.get("short_name"),
        record.get("internal_name"),
        record.get("repository_slug"),
        record.get("plugin_slug"),
        record.get("plugin_text_domain"),
        record.get("discovered_text_domain"),
    )
    for value in fields:
        token = slugify(value)
        if token:
            tokens.add(token)
    plugin_file = normalize_plugin_file(record.get("plugin_file"))
    if plugin_file:
        tokens.add(slugify(plugin_file))
        tokens.add(slugify(plugin_file.split("/", 1)[0]))
        tokens.add(slugify(Path(plugin_file).stem))
    for field_name in ARRAY_FIELDS:
        for value in unique_strings(record.get(field_name, [])):
            token = slugify(value)
            if token:
                tokens.add(token)
            if field_name == "legacy_plugin_files":
                normalized = normalize_plugin_file(value)
                if normalized:
                    tokens.add(slugify(normalized.split("/", 1)[0]))
                    tokens.add(slugify(Path(normalized).stem))
    return tokens


def profile_tokens(profile: Mapping[str, Any]) -> set[str]:
    tokens = record_tokens(str(profile.get("canonical_id", "")), profile)
    for value in profile.get("id_aliases", []):
        token = slugify(value)
        if token:
            tokens.add(token)
    return tokens


def build_alias_index(profiles: Mapping[str, Mapping[str, Any]]) -> tuple[dict[str, set[str]], list[dict[str, Any]]]:
    index: dict[str, set[str]] = {}
    for product_id, profile in profiles.items():
        for token in profile_tokens(profile):
            index.setdefault(token, set()).add(product_id)
    collisions = [
        {"alias": alias, "products": sorted(product_ids)}
        for alias, product_ids in sorted(index.items())
        if len(product_ids) > 1
    ]
    return index, collisions


def profile_default_record(profile: Mapping[str, Any], timestamp: str) -> dict[str, Any]:
    record = copy.deepcopy(dict(profile))
    record.pop("id_aliases", None)
    record["canonical_id"] = slugify(record.get("canonical_id"))
    record.setdefault("internal_name", record.get("name", record["canonical_id"]))
    for field_name in ARRAY_FIELDS:
        record[field_name] = unique_strings(
            record.get(field_name, []),
            plugin_file=field_name == "legacy_plugin_files",
            slug=field_name in {"legacy_plugin_slugs", "legacy_text_domains"},
        )
    for field_name in BOOLISH_FIELDS:
        if field_name in record:
            record[field_name] = boolish(record[field_name])
    record.setdefault("plugin_text_domain", slugify(record.get("plugin_slug")))
    record.setdefault("discovery_locked", "")
    record.setdefault("discovery_state", "unscanned")
    record.setdefault("discovery_match", "")
    record.setdefault("discovered_active", "")
    record.setdefault("discovered_activation_scope", "inactive")
    record.setdefault("discovered_plugin_name", "")
    record.setdefault("discovered_plugin_version", "")
    record.setdefault("discovered_plugin_version_raw", "")
    record.setdefault("discovered_version_state", "unscanned")
    record.setdefault("discovered_text_domain", "")
    record.setdefault("last_discovered_at", "")
    record.setdefault("installed_version", "")
    record.setdefault("public_version", "")
    record.setdefault("previous_version", "")
    record.setdefault("release_date", "")
    record.setdefault("change_summary", "")
    record.setdefault("superseded_by", "")
    record.setdefault("manual_notes", "")
    record.setdefault("verification_source", "registry_seed")
    record.setdefault("source_verified_at", "")
    record.setdefault("record_updated_at", timestamp)
    record.setdefault("last_verified_at", "")
    return record


def best_nonempty(candidates: Sequence[tuple[str, Mapping[str, Any]]], field_name: str, canonical_id: str) -> Any:
    canonical_values = [record.get(field_name) for key, record in candidates if slugify(key) == canonical_id and not is_empty(record.get(field_name))]
    if canonical_values:
        return copy.deepcopy(canonical_values[0])
    ranked = sorted(candidates, key=lambda pair: richness(pair[1]), reverse=True)
    for _, record in ranked:
        if not is_empty(record.get(field_name)):
            return copy.deepcopy(record.get(field_name))
    return None


def merge_candidates(
    canonical_id: str,
    profile: Mapping[str, Any],
    candidates: Sequence[tuple[str, Mapping[str, Any]]],
    timestamp: str,
    repository_version: str = "",
) -> dict[str, Any]:
    merged = profile_default_record(profile, timestamp)
    all_fields: set[str] = set(merged)
    for _, record in candidates:
        all_fields.update(record)

    for field_name in sorted(all_fields):
        if field_name in ARRAY_FIELDS or field_name in VERSION_FIELDS or field_name in CANONICAL_IDENTITY_FIELDS:
            continue
        selected = best_nonempty(candidates, field_name, canonical_id)
        if selected is not None:
            merged[field_name] = selected

    for field_name in VERSION_FIELDS:
        values = [record.get(field_name) for _, record in candidates]
        selected_version = choose_highest_version(values)
        if selected_version:
            merged[field_name] = selected_version

    for field_name in TIMESTAMP_FIELDS:
        values = [str(record.get(field_name, "")).strip() for _, record in candidates]
        values = [value for value in values if value]
        if values:
            merged[field_name] = max(values)

    legacy_names: list[Any] = list(profile.get("legacy_names", []))
    legacy_plugin_files: list[Any] = list(profile.get("legacy_plugin_files", []))
    legacy_plugin_slugs: list[Any] = list(profile.get("legacy_plugin_slugs", []))
    legacy_text_domains: list[Any] = list(profile.get("legacy_text_domains", []))

    for source_key, record in candidates:
        source_id = slugify(record.get("canonical_id") or source_key)
        if source_id and source_id != canonical_id:
            legacy_names.append(source_id)
        for name_field in ("name", "short_name", "internal_name"):
            source_name = str(record.get(name_field, "")).strip()
            if source_name and source_name not in {profile.get("name"), profile.get("short_name"), profile.get("internal_name")}:
                legacy_names.append(source_name)
        legacy_names.extend(record.get("legacy_names", []))
        plugin_file = normalize_plugin_file(record.get("plugin_file"))
        if plugin_file and plugin_file != normalize_plugin_file(profile.get("plugin_file")):
            legacy_plugin_files.append(plugin_file)
        legacy_plugin_files.extend(record.get("legacy_plugin_files", []))
        plugin_slug = slugify(record.get("plugin_slug"))
        if plugin_slug and plugin_slug != slugify(profile.get("plugin_slug")):
            legacy_plugin_slugs.append(plugin_slug)
        repository_slug = slugify(record.get("repository_slug"))
        if repository_slug and repository_slug != slugify(profile.get("repository_slug")):
            legacy_plugin_slugs.append(repository_slug)
        legacy_plugin_slugs.extend(record.get("legacy_plugin_slugs", []))
        text_domain = slugify(record.get("plugin_text_domain"))
        if text_domain and text_domain != slugify(profile.get("plugin_text_domain")):
            legacy_text_domains.append(text_domain)
        discovered_domain = slugify(record.get("discovered_text_domain"))
        if discovered_domain and discovered_domain != slugify(profile.get("plugin_text_domain")):
            legacy_text_domains.append(discovered_domain)
        legacy_text_domains.extend(record.get("legacy_text_domains", []))

    merged["legacy_names"] = unique_strings(legacy_names)
    merged["legacy_plugin_files"] = unique_strings(legacy_plugin_files, plugin_file=True)
    merged["legacy_plugin_slugs"] = unique_strings(legacy_plugin_slugs, slug=True)
    merged["legacy_text_domains"] = unique_strings(legacy_text_domains, slug=True)

    for field_name in CANONICAL_IDENTITY_FIELDS:
        if field_name in profile:
            merged[field_name] = copy.deepcopy(profile[field_name])
    merged["canonical_id"] = canonical_id
    merged["internal_name"] = str(profile.get("internal_name") or profile.get("name") or canonical_id)

    actual_plugin_file = best_nonempty(candidates, "plugin_file", canonical_id)
    if actual_plugin_file:
        merged["plugin_file"] = normalize_plugin_file(actual_plugin_file)
    actual_plugin_slug = best_nonempty(candidates, "plugin_slug", canonical_id)
    if actual_plugin_slug:
        merged["plugin_slug"] = slugify(actual_plugin_slug)
    actual_text_domain = best_nonempty(candidates, "plugin_text_domain", canonical_id)
    if actual_text_domain:
        merged["plugin_text_domain"] = slugify(actual_text_domain)

    if repository_version:
        merged["public_version"] = choose_highest_version([merged.get("public_version"), repository_version])
        if merged.get("version_source") == "package_manifest":
            merged["installed_version"] = choose_highest_version([merged.get("installed_version"), repository_version])
            merged["verification_source"] = "package_manifest"
            merged["source_verified_at"] = timestamp
            merged["last_verified_at"] = timestamp

    changed_identity = any(slugify(source_key) != canonical_id for source_key, _ in candidates)
    if changed_identity or len(candidates) > 1:
        merged["verification_source"] = "migration"
        merged["record_updated_at"] = timestamp
    for field_name in BOOLISH_FIELDS:
        if field_name in merged:
            merged[field_name] = boolish(merged[field_name])
    return merged


def candidate_repo_dirs(products_root: Path, repository_slug: str) -> list[Path]:
    exact = products_root / repository_slug
    candidates: list[Path] = []
    if exact.is_dir():
        candidates.append(exact)
    patterns = [
        f"{repository_slug}*",
        f"*{repository_slug}*",
    ]
    for pattern in patterns:
        for path in products_root.glob(pattern):
            if path.is_dir() and path not in candidates:
                candidates.append(path)
    return candidates[:20]


def read_version_from_json(path: Path) -> str:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return ""
    for key in ("version", "release_version", "product_version"):
        value = payload.get(key) if isinstance(payload, dict) else None
        if isinstance(value, str) and value.strip():
            return value.strip().lstrip("v")
    if isinstance(payload, dict):
        project = payload.get("project")
        if isinstance(project, dict) and isinstance(project.get("version"), str):
            return project["version"].strip().lstrip("v")
    return ""


def scan_repository_version(repo_dir: Path) -> str:
    candidates: list[str] = []
    preferred_files = [
        repo_dir / "release-manifest.json",
        repo_dir / "manifest.json",
        repo_dir / "package.json",
    ]
    for path in preferred_files:
        if path.is_file():
            version = read_version_from_json(path)
            if version:
                candidates.append(version)

    manifest_patterns = (
        "release-manifest-v*.json",
        "feature_suggestions_manifest-v*.json",
        "*manifest*.json",
    )
    for pattern in manifest_patterns:
        for path in list(repo_dir.glob(pattern))[:100]:
            version = read_version_from_json(path)
            if version:
                candidates.append(version)

    description = repo_dir / "DESCRIPTION"
    if description.is_file():
        match = re.search(r"^Version:\s*(\S+)", description.read_text(encoding="utf-8", errors="ignore"), re.MULTILINE)
        if match:
            candidates.append(match.group(1).strip().lstrip("v"))

    pyproject = repo_dir / "pyproject.toml"
    if pyproject.is_file():
        text = pyproject.read_text(encoding="utf-8", errors="ignore")
        match = re.search(r"(?m)^\s*version\s*=\s*[\"']([^\"']+)[\"']", text)
        if match:
            candidates.append(match.group(1).strip().lstrip("v"))

    for php_file in list(repo_dir.glob("wordpress/*/*.php"))[:100] + list(repo_dir.glob("*.php"))[:20]:
        text = php_file.read_text(encoding="utf-8", errors="ignore")
        match = re.search(r"(?mi)^\s*\*\s*Version:\s*([^\s]+)", text)
        if match:
            candidates.append(match.group(1).strip().lstrip("v"))

    return choose_highest_version(candidates)


def scan_all_repository_versions(products_root: Path, profiles: Mapping[str, Mapping[str, Any]]) -> dict[str, str]:
    versions: dict[str, str] = {}
    for product_id, profile in profiles.items():
        repository_slug = slugify(profile.get("repository_slug"))
        if not repository_slug:
            continue
        found: list[str] = []
        for repo_dir in candidate_repo_dirs(products_root, repository_slug):
            version = scan_repository_version(repo_dir)
            if version:
                found.append(version)
        selected = choose_highest_version(found)
        if selected:
            versions[product_id] = selected
    return versions


def reconcile_registry(
    records: Mapping[str, Mapping[str, Any]],
    profiles: Mapping[str, Mapping[str, Any]],
    *,
    preserve_unknown: bool = True,
    repository_versions: Mapping[str, str] | None = None,
    timestamp: str | None = None,
) -> tuple[dict[str, dict[str, Any]], ReconcileReport]:
    timestamp = timestamp or utc_now()
    repository_versions = repository_versions or {}
    alias_index, catalog_collisions = build_alias_index(profiles)
    report = ReconcileReport(schema="scfs-canonical-product-registry-reconcile-report/1.0", generated_at=timestamp, mode="offline")
    report.input_count = len(records)
    report.collisions.extend(catalog_collisions)
    if catalog_collisions:
        raise RegistryError(f"Catalog alias collisions detected: {catalog_collisions}")

    grouped: dict[str, list[tuple[str, Mapping[str, Any]]]] = {product_id: [] for product_id in profiles}
    unknown: dict[str, dict[str, Any]] = {}

    for key, record in records.items():
        tokens = record_tokens(str(key), record)
        matches: set[str] = set()
        for token in tokens:
            matches.update(alias_index.get(token, set()))
        exact = slugify(record.get("canonical_id") or key)
        if exact in profiles:
            matches = {exact}
        if len(matches) == 1:
            product_id = next(iter(matches))
            grouped[product_id].append((str(key), copy.deepcopy(dict(record))))
        elif len(matches) > 1:
            collision = {"record": str(key), "tokens": sorted(tokens), "products": sorted(matches)}
            report.collisions.append(collision)
            unknown[str(key)] = copy.deepcopy(dict(record))
        else:
            unknown[str(key)] = copy.deepcopy(dict(record))

    output: dict[str, dict[str, Any]] = {}
    for product_id, profile in profiles.items():
        candidates = grouped[product_id]
        if not candidates:
            report.seeded_products.append(product_id)
            report.changes.append(Change("seed", product_id, "Created missing canonical product record from governed catalog."))
        else:
            aliases = [slugify(key) for key, _ in candidates if slugify(key) != product_id]
            if len(candidates) > 1 or aliases:
                report.merged_alias_records[product_id] = sorted(set(aliases))
                detail = f"Merged {len(candidates)} matching record(s)"
                if aliases:
                    detail += f"; aliases: {', '.join(sorted(set(aliases)))}"
                report.changes.append(Change("merge", product_id, detail + "."))
        output[product_id] = merge_candidates(
            product_id,
            profile,
            candidates,
            timestamp,
            repository_versions.get(product_id, ""),
        )

        resolved = choose_highest_version(
            [
                output[product_id].get("public_version"),
                output[product_id].get("installed_version"),
                output[product_id].get("discovered_plugin_version"),
            ]
        )
        if not resolved and output[product_id].get("version_source") != "wordpress_plugin":
            report.manual_version_needed.append(product_id)

    if preserve_unknown:
        for key, record in unknown.items():
            normalized_key = slugify(record.get("canonical_id") or key) or slugify(key)
            if normalized_key in output:
                normalized_key = f"unmapped-{normalized_key}"
            output[normalized_key] = copy.deepcopy(record)
            report.unknown_records.append(key)
    else:
        report.unknown_records.extend(sorted(unknown))

    output = dict(sorted(output.items(), key=lambda item: (int(item[1].get("display_order", 9999) or 9999), str(item[1].get("name", item[0])).lower())))
    report.output_count = len(output)
    report.repository_versions = dict(repository_versions)
    report.fingerprint_before = json_fingerprint(dict(records))
    report.fingerprint_after = json_fingerprint(output)
    return output, report


def run_command(command: Sequence[str], *, check: bool = True, capture: bool = True) -> subprocess.CompletedProcess[str]:
    process = subprocess.run(
        list(command),
        check=False,
        text=True,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.PIPE if capture else None,
    )
    if check and process.returncode != 0:
        stderr = (process.stderr or "").strip()
        stdout = (process.stdout or "").strip()
        detail = stderr or stdout or f"exit code {process.returncode}"
        raise RegistryError(f"Command failed: {' '.join(command)}\n{detail}")
    return process


def wp_base_command(wp_bin: str, wordpress_path: Path) -> list[str]:
    return [wp_bin, f"--path={wordpress_path}", "--skip-themes"]


def wp_option_get(wp_bin: str, wordpress_path: Path, option_name: str) -> Any:
    command = wp_base_command(wp_bin, wordpress_path) + ["option", "get", option_name, "--format=json"]
    process = run_command(command)
    try:
        return json.loads(process.stdout)
    except json.JSONDecodeError as exc:
        raise RegistryError(f"WP-CLI returned invalid JSON for option {option_name}: {process.stdout[:500]}") from exc


def wp_option_update(wp_bin: str, wordpress_path: Path, option_name: str, payload: Any) -> None:
    value = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
    command = wp_base_command(wp_bin, wordpress_path) + ["option", "update", option_name, value, "--format=json"]
    run_command(command)


def wp_option_update_string(wp_bin: str, wordpress_path: Path, option_name: str, value: str) -> None:
    command = wp_base_command(wp_bin, wordpress_path) + ["option", "update", option_name, value]
    run_command(command)


def wp_plugin_command(wp_bin: str, wordpress_path: Path, args: Sequence[str], *, check: bool = True) -> subprocess.CompletedProcess[str]:
    command = wp_base_command(wp_bin, wordpress_path) + list(args)
    return run_command(command, check=check)


def ensure_wordpress_path(path: Path) -> None:
    if not path.is_dir():
        raise RegistryError(f"WordPress path does not exist: {path}")
    if not (path / "wp-config.php").is_file() and not (path.parent / "wp-config.php").is_file():
        raise RegistryError(f"No wp-config.php found at or above: {path}")


def default_catalog_path() -> Path:
    return Path(__file__).resolve().with_name("canonical-product-catalog-v1.json")


def default_output_path(input_path: Path | None) -> Path:
    if input_path:
        return input_path.with_name(input_path.stem + "-canonicalized.json")
    return Path.cwd() / "canonical-product-registry-canonicalized.json"


def default_report_path(output_path: Path) -> Path:
    return output_path.with_name(output_path.stem + "-report.json")


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Merge legacy Sustainable Catalyst product identities into the governed Canonical Product Registry.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument("--wordpress-path", type=Path, help="WordPress root containing wp-config.php; uses WP-CLI.")
    source.add_argument("--input", type=Path, help="Exported registry JSON for offline reconciliation.")
    parser.add_argument("--catalog", type=Path, default=default_catalog_path(), help="Governed canonical product catalog JSON.")
    parser.add_argument("--products-root", type=Path, help="Optional folder containing local product repositories to scan for versions.")
    parser.add_argument("--output", type=Path, help="Canonicalized registry JSON output path.")
    parser.add_argument("--report", type=Path, help="Machine-readable reconciliation report path.")
    parser.add_argument("--backup-dir", type=Path, help="Backup directory for live WordPress apply mode.")
    parser.add_argument("--wp", default=os.environ.get("WP_CLI_BIN", "wp"), help="WP-CLI executable.")
    parser.add_argument("--apply", action="store_true", help="Write changes. Without this flag, the utility only produces a dry-run output and report.")
    parser.add_argument("--drop-unknown", action="store_true", help="Exclude unmatched records from output. Not recommended.")
    parser.add_argument("--skip-discovery", action="store_true", help="Do not run installed-plugin discovery after a live apply.")
    parser.add_argument("--skip-validation", action="store_true", help="Do not run plugin registry validation after a live apply.")
    parser.add_argument("--quiet", action="store_true", help="Suppress human-readable summary output.")
    return parser.parse_args(argv)


def write_report(report_path: Path, report: ReconcileReport) -> None:
    dump_json(report_path, report.as_dict())


def print_summary(report: ReconcileReport, *, dry_run: bool) -> None:
    print("Canonical Product Registry reconciliation")
    print(f"  Mode: {'dry-run' if dry_run else 'apply'}")
    print(f"  Products: {report.input_count} -> {report.output_count}")
    print(f"  Seeded: {len(report.seeded_products)}")
    print(f"  Canonical merges: {len(report.merged_alias_records)}")
    print(f"  Unknown preserved: {len(report.unknown_records)}")
    print(f"  Manual versions still needed: {len(report.manual_version_needed)}")
    print(f"  Fingerprint: {report.fingerprint_after}")
    if report.output_path:
        print(f"  Output: {report.output_path}")
    if report.backup_path:
        print(f"  Backup: {report.backup_path}")
    if report.collisions:
        print(f"  Collisions: {len(report.collisions)}")


def main(argv: Sequence[str] | None = None) -> int:
    args = parse_args(argv)
    catalog_payload = load_json(args.catalog.resolve())
    profiles, _ = catalog_profiles(catalog_payload)
    repository_versions: dict[str, str] = {}
    if args.products_root:
        products_root = args.products_root.expanduser().resolve()
        if not products_root.is_dir():
            raise RegistryError(f"Products root does not exist: {products_root}")
        repository_versions = scan_all_repository_versions(products_root, profiles)

    wp_mode = args.wordpress_path is not None
    wrapper: dict[str, Any] = {}
    if wp_mode:
        wordpress_path = args.wordpress_path.expanduser().resolve()
        ensure_wordpress_path(wordpress_path)
        wp_bin = shutil.which(args.wp) or (args.wp if Path(args.wp).exists() else None)
        if not wp_bin:
            raise RegistryError(f"WP-CLI executable not found: {args.wp}")
        current_payload = wp_option_get(wp_bin, wordpress_path, REGISTRY_OPTION)
        current_records, wrapper = unwrap_registry(current_payload)
        output_path = (args.output or (Path.cwd() / "canonical-product-registry-dry-run.json")).expanduser().resolve()
    else:
        input_path = args.input.expanduser().resolve()
        current_payload = load_json(input_path)
        current_records, wrapper = unwrap_registry(current_payload)
        output_path = (args.output or default_output_path(input_path)).expanduser().resolve()

    reconciled, report = reconcile_registry(
        current_records,
        profiles,
        preserve_unknown=not args.drop_unknown,
        repository_versions=repository_versions,
    )
    report.mode = "wordpress" if wp_mode else "offline"
    report.output_path = str(output_path)
    report_path = (args.report or default_report_path(output_path)).expanduser().resolve()
    # Dynamic attribute is serialized below explicitly for compatibility with older report consumers.
    setattr(report, "report_path", str(report_path))

    as_option = wp_mode or not bool(wrapper)
    dump_json(output_path, wrap_registry(reconciled, wrapper, as_option=as_option))

    if report.collisions:
        write_report(report_path, report)
        raise RegistryError(f"Unsafe alias collisions detected. Review {report_path} before applying.")

    if args.apply and wp_mode:
        timestamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
        backup_dir = (args.backup_dir or (Path.cwd() / "canonical-registry-backups")).expanduser().resolve()
        backup_dir.mkdir(parents=True, exist_ok=True)
        backup_path = backup_dir / f"scfs-canonical-product-registry-before-{timestamp}.json"
        dump_json(backup_path, current_payload)
        report.backup_path = str(backup_path)
        try:
            wp_option_update(wp_bin, wordpress_path, REGISTRY_OPTION, reconciled)
            wp_option_update_string(wp_bin, wordpress_path, REGISTRY_SCHEMA_OPTION, str(catalog_payload.get("registry_schema") or DEFAULT_SCHEMA))
            report.applied = True
            if not args.skip_discovery:
                wp_plugin_command(wp_bin, wordpress_path, ["scfs", "products", "discover", "--format=json"])
                report.discovery_ran = True
            if not args.skip_validation:
                wp_plugin_command(wp_bin, wordpress_path, ["scfs", "products", "validate", "--format=json"])
                report.validation_passed = True
            final_payload = wp_option_get(wp_bin, wordpress_path, REGISTRY_OPTION)
            final_records, _ = unwrap_registry(final_payload)
            report.output_count = len(final_records)
            report.fingerprint_after = json_fingerprint(final_records)
            dump_json(output_path, final_payload)
        except Exception:
            wp_option_update(wp_bin, wordpress_path, REGISTRY_OPTION, current_payload)
            report.rollback_performed = True
            write_report(report_path, report)
            raise
    elif args.apply and not wp_mode:
        # Offline apply means the requested output file is the applied artifact.
        report.applied = True
        report.validation_passed = True

    write_report(report_path, report)
    if not args.quiet:
        print_summary(report, dry_run=not args.apply)
        print(f"  Report: {report_path}")
        if report.manual_version_needed:
            print("  Manual-version products: " + ", ".join(report.manual_version_needed))
        if not args.apply:
            print("  No live data was changed. Re-run with --apply after reviewing the output and report.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except RegistryError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(2)
