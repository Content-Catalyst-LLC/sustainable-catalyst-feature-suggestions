# Release Operations v7.6.0

Release Operations is the administrator control plane for the Canonical Product Registry, active WordPress plugin mappings, GitHub release intelligence, and the public Release Console.

## Location

Open **Support & Feedback → Release Operations**.

## Operational table

Every non-retired canonical product is shown with:

- canonical product identity
- active WordPress plugin mapping
- installed plugin version
- mapped GitHub repository
- latest governed GitHub version and authority
- public console state
- last successful or failed synchronization time
- direct Sync now action

## Health states

- **Current:** synchronized within two hours.
- **Aging:** synchronized more than two hours ago but within 24 hours.
- **Stale:** not synchronized within 24 hours.
- **Update available:** the active plugin version is behind the governed GitHub release or semantic tag.
- **Synchronization error:** GitHub returned an error.
- **Repository not connected:** no GitHub repository is mapped.

## Bulk actions

Administrators can synchronize selected products, synchronize all connected products, or clear selected stale error messages before retrying.

## Integrity audit

The audit detects duplicate repository mappings, duplicate plugin implementations, enabled synchronization without a repository, stale or missing synchronization, active-plugin gaps, and current GitHub errors.

## Export

The operational JSON export excludes GitHub tokens, webhook secrets, private credential material, and raw option storage. It contains only product, plugin, repository, release, console, and health metadata.

## Compatibility

All legacy shortcodes, registry aliases, active-plugin mappings, GitHub webhook verification, scheduled polling, Release Console behavior, and the historical `sustainable-catalyst-feature-suggestions` plugin folder remain unchanged.
