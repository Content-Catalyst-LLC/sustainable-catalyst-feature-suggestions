# GitHub synchronization continuity v7.6.0

Open **Support & Feedback → GitHub Connection** to manage the credential, test repository access, synchronize individual repositories, synchronize all repositories, and edit the two Release Console footer links.

## Version authority

The console uses the latest published GitHub Release when one exists. When a repository has no published release, the newest semantic tag such as `v2.1.0` becomes the governed console version. Tags that are not semantic versions are ignored.

A normal branch push refreshes the latest commit SHA and repository-updated timestamp. It does not invent a release version.

## Automatic updates

Signed GitHub webhooks continue to provide immediate refreshes. WordPress also performs an hourly fallback check. The schedule is recreated automatically if the WordPress cron event disappears.

## Footer links

The same GitHub Connection screen now edits the repository label, repository destination, support label, and support destination. Leaving the repository destination blank uses the canonical Product Support and Feedback GitHub repository.
