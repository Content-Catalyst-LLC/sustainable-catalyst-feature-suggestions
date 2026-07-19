from app.support_discovery import DiscoveryArticle, DiscoverySearchRequest, normalize, score_article, search_support_articles, tokens


def sample_articles():
    return [
        DiscoveryArticle(article_id="a1", title="Export a Decision Studio briefing", summary="Create PDF and CSV reports.", content="Use the export panel.", product=["Decision Studio"], version=["2.0.1"], component=["Export"], article_type=["How-to"], updated_at="2026-07-18T00:00:00Z"),
        DiscoveryArticle(article_id="a2", title="Repair API endpoint errors", summary="Troubleshoot REST failures and authentication.", content="Inspect the endpoint and API key.", product=["Site Intelligence"], version=["3.0.0"], component=["REST API"], article_type=["Troubleshooting"], updated_at="2026-07-17T00:00:00Z"),
        DiscoveryArticle(article_id="a3", title="Configure mobile layouts", summary="Responsive phone and tablet support.", content="Test mobile navigation.", product=["Support Platform"], version=["5.4.0"], component=["Interface"], article_type=["Configuration"], updated_at="2026-07-19T00:00:00Z"),
    ]


def test_normalize_and_synonyms():
    assert normalize("  API! Error  ") == "api error"
    assert "endpoint" in tokens("api")
    assert "failure" in tokens("error")


def test_exact_title_outweighs_content_only_match():
    rows = sample_articles()
    title_match = score_article(rows[0], "export a decision studio")
    content_match = score_article(rows[1], "endpoint")
    assert title_match.score > content_match.score
    assert "exact title phrase" in title_match.reasons


def test_version_context_is_searchable():
    match = score_article(sample_articles()[2], "5.4.0")
    assert match.score > 0
    assert "exact product or version context" in match.reasons


def test_relevance_search_filters_nonmatches():
    result = search_support_articles(DiscoverySearchRequest(query="api error", articles=sample_articles()))
    assert result.count == 1
    assert result.results[0].article_id == "a2"


def test_recent_and_title_sort_are_deterministic():
    recent = search_support_articles(DiscoverySearchRequest(query="", articles=sample_articles(), sort="recent"))
    title = search_support_articles(DiscoverySearchRequest(query="", articles=sample_articles(), sort="title"))
    assert recent.results[0].article_id == "a3"
    assert title.results[0].article_id == "a3"
