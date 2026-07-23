# Plugin Discovery status repair — v7.5.1

Plugin Discovery normalizes the private candidate queue before reporting counts. Rows without a valid relative plugin file or a recognized review state are ignored as stale data.

Unmatched and malformed candidates appear under **Pending private review**. Deterministic duplicate candidates appear separately under **Duplicate mapping review**. Each row includes a reason, suggested Product Registry match, and direct action.

When no actionable candidates remain, the administrator screen displays **No plugins awaiting review** and omits the Pending private review heading. A rescan replaces the cached snapshot and queue, clears stale discovery evidence for products no longer found, and returns the current pending queue through the authenticated REST response.
