# Public Ideas, Voting, and Participatory Prioritization

Version 2.8.0 adds a moderation-controlled public ideas directory.

## Shortcode

```text
[sc_public_ideas]
```

Optional attributes: `limit`, `state`, and `title`.

## Editorial workflow

Open a Feature Suggestion and use the **Public participation** panel to approve publication, choose a public roadmap state, publish an official response, add a roadmap or release URL, or merge a duplicate into a canonical idea.

## Voting boundary

Support votes are advisory. They do not publish ideas, alter workflow status, create roadmap commitments, or outweigh evidence, feasibility, public-interest value, privacy, risk, and strategic alignment.

## REST API

- `GET /wp-json/scfs/v1/public-ideas`
- `POST /wp-json/scfs/v1/public-ideas/{id}/support`

The support endpoint publishes a privacy-minimized `idea.supported` event for Site Intelligence.
