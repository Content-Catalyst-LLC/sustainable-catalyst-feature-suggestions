# GitHub Connection v7.5.4

Open **Support & Feedback → GitHub Connection**.

1. Paste a fine-grained GitHub token into **GitHub token**.
2. Click **Save GitHub Connection**.
3. Click **Test mapped repositories**.
4. Review the result for each canonical product.
5. Click **Sync all repositories now**.

The token should use `Content-Catalyst-LLC` as its resource owner, include the required private repositories, and grant **Contents: Read-only** permission.

Saved tokens are encrypted and never shown again. A token defined as `SCFS_GITHUB_TOKEN` in `wp-config.php`, or supplied through the server environment, takes priority over the WordPress-stored token.

A repository can synchronize successfully without a published GitHub Release. In that case, the registry records repository and commit evidence and reports that no release is available yet.
