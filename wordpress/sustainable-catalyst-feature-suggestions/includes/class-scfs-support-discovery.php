<?php
/**
 * Support discovery, navigation, and search quality services.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Support_Discovery {
    const VERSION = '5.2.9';
    const SCHEMA = 'scfs-support-discovery/1.0';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function synonyms() {
        return apply_filters('scfs_support_discovery_synonyms', array(
            'broken' => array('error', 'failed', 'failure', 'not working', 'issue'),
            'error' => array('broken', 'failure', 'failed', 'exception', 'problem'),
            'install' => array('installation', 'setup', 'configure', 'configuration'),
            'login' => array('sign in', 'authentication', 'access'),
            'export' => array('download', 'report', 'csv', 'pdf'),
            'import' => array('upload', 'ingest', 'migration'),
            'api' => array('endpoint', 'rest', 'integration', 'connector'),
            'mobile' => array('responsive', 'phone', 'tablet'),
            'slow' => array('performance', 'timeout', 'latency'),
            'version' => array('release', 'upgrade', 'compatibility'),
            'article' => array('guide', 'documentation', 'support article'),
            'search' => array('find', 'discovery', 'lookup'),
        ));
    }

    public function normalize_query($query) {
        $query = strtolower(wp_strip_all_tags((string) $query));
        $query = preg_replace('/[^a-z0-9._+\-\s]+/u', ' ', $query);
        return trim(preg_replace('/\s+/', ' ', $query));
    }

    public function query_tokens($query, $expand = true) {
        $normalized = $this->normalize_query($query);
        if ($normalized === '') {
            return array();
        }
        $tokens = preg_split('/\s+/', $normalized);
        $tokens = array_values(array_unique(array_filter($tokens, static function($token) {
            return strlen($token) > 1;
        })));
        if (!$expand) {
            return $tokens;
        }
        $synonyms = $this->synonyms();
        $expanded = $tokens;
        foreach ($tokens as $token) {
            if (isset($synonyms[$token])) {
                foreach ((array) $synonyms[$token] as $synonym) {
                    $expanded[] = $this->normalize_query($synonym);
                }
            }
        }
        return array_values(array_unique(array_filter($expanded)));
    }

    private function term_text($post_id, $taxonomy) {
        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
        return is_wp_error($terms) ? '' : implode(' ', $terms);
    }

    public function article_profile($post) {
        $post = get_post($post);
        if (!($post instanceof WP_Post)) {
            return array();
        }
        $summary = (string) get_post_meta($post->ID, '_scfs_kb_summary', true);
        $verified = (string) get_post_meta($post->ID, '_scfs_last_verified_version', true);
        $version = (string) get_post_meta($post->ID, '_scfs_product_version', true);
        $taxonomies = array(
            SCFS_Product_Integration::PRODUCT_TAXONOMY,
            SCFS_Product_Integration::COMPONENT_TAXONOMY,
            SCFS_Product_Integration::VERSION_TAXONOMY,
            SCFS_Knowledge_Base_Foundation::ARTICLE_TYPE_TAXONOMY,
            SCFS_Knowledge_Base_Foundation::COLLECTION_TAXONOMY,
        );
        $term_text = '';
        foreach ($taxonomies as $taxonomy) {
            $term_text .= ' ' . $this->term_text($post->ID, $taxonomy);
        }
        return array(
            'id' => (int) $post->ID,
            'title' => $this->normalize_query(get_the_title($post)),
            'summary' => $this->normalize_query($summary . ' ' . $post->post_excerpt),
            'content' => $this->normalize_query(wp_strip_all_tags($post->post_content)),
            'terms' => $this->normalize_query($term_text),
            'version' => $this->normalize_query($version . ' ' . $verified),
            'modified' => strtotime($post->post_modified_gmt ?: $post->post_modified) ?: 0,
            'menu_order' => (int) $post->menu_order,
        );
    }

    public function score_article($post, $query) {
        $profile = $this->article_profile($post);
        if (!$profile) {
            return array('score' => 0, 'reasons' => array(), 'matched_tokens' => array());
        }
        $query = $this->normalize_query($query);
        if ($query === '') {
            return array('score' => 1, 'reasons' => array('catalog'), 'matched_tokens' => array());
        }
        $original = $this->query_tokens($query, false);
        $expanded = $this->query_tokens($query, true);
        $score = 0;
        $reasons = array();
        $matched = array();
        if (strpos($profile['title'], $query) !== false) {
            $score += 140;
            $reasons[] = 'exact title phrase';
        }
        if (strpos($profile['summary'], $query) !== false) {
            $score += 90;
            $reasons[] = 'exact summary phrase';
        }
        if (strpos($profile['terms'], $query) !== false || strpos($profile['version'], $query) !== false) {
            $score += 70;
            $reasons[] = 'exact product or version context';
        }
        foreach ($expanded as $token) {
            $token_score = 0;
            if (strpos($profile['title'], $token) !== false) $token_score += 34;
            if (strpos($profile['summary'], $token) !== false) $token_score += 22;
            if (strpos($profile['terms'], $token) !== false) $token_score += 26;
            if (strpos($profile['version'], $token) !== false) $token_score += 30;
            if (strpos($profile['content'], $token) !== false) $token_score += 7;
            if ($token_score > 0) {
                $score += $token_score;
                $matched[] = $token;
            }
        }
        $original_matches = 0;
        foreach ($original as $token) {
            if (in_array($token, $matched, true)) $original_matches++;
        }
        $minimum = max(1, (int) ceil(count($original) * 0.5));
        if ($original_matches < $minimum) {
            return array('score' => 0, 'reasons' => array(), 'matched_tokens' => array_values(array_unique($matched)));
        }
        if ($original_matches === count($original) && count($original) > 1) {
            $score += 45;
            $reasons[] = 'all query terms';
        }
        if ($profile['modified'] > strtotime('-180 days')) {
            $score += 4;
        }
        return array(
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'matched_tokens' => array_values(array_unique($matched)),
        );
    }

    public function rank_articles($posts, $query = '', $sort = 'relevance') {
        $rows = array();
        foreach ((array) $posts as $post) {
            $post = get_post($post);
            if (!($post instanceof WP_Post)) continue;
            $match = $this->score_article($post, $query);
            if ($query !== '' && $match['score'] < 1) continue;
            $rows[] = array('post' => $post, 'match' => $match, 'profile' => $this->article_profile($post));
        }
        usort($rows, static function($a, $b) use ($sort, $query) {
            if ($sort === 'title') {
                return strnatcasecmp($a['post']->post_title, $b['post']->post_title);
            }
            if ($sort === 'recent') {
                return $b['profile']['modified'] <=> $a['profile']['modified'];
            }
            if ($query !== '' && $a['match']['score'] !== $b['match']['score']) {
                return $b['match']['score'] <=> $a['match']['score'];
            }
            if ($a['profile']['menu_order'] !== $b['profile']['menu_order']) {
                return $a['profile']['menu_order'] <=> $b['profile']['menu_order'];
            }
            return $b['profile']['modified'] <=> $a['profile']['modified'];
        });
        return $rows;
    }

    public function suggest_queries($query, $posts, $limit = 6) {
        $query = $this->normalize_query($query);
        $suggestions = array();
        foreach ($this->rank_articles($posts, $query, 'relevance') as $row) {
            $title = get_the_title($row['post']);
            if ($title !== '') $suggestions[] = $title;
            if (count($suggestions) >= $limit) break;
        }
        if (!$suggestions && $query !== '') {
            foreach ($this->query_tokens($query, true) as $token) {
                if ($token !== $query) $suggestions[] = $token;
                if (count($suggestions) >= $limit) break;
            }
        }
        return array_values(array_unique($suggestions));
    }

    public function serialize_result($row) {
        $post = $row['post'];
        return array(
            'id' => (int) $post->ID,
            'article_id' => (string) $post->ID,
            'title' => get_the_title($post),
            'url' => get_permalink($post),
            'summary' => get_post_meta($post->ID, '_scfs_kb_summary', true) ?: get_the_excerpt($post),
            'modified' => get_the_modified_date('c', $post),
            'score' => (int) $row['match']['score'],
            'reasons' => $row['match']['reasons'],
            'matched_tokens' => $row['match']['matched_tokens'],
        );
    }

    public function register_rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/knowledge-base/discovery/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/knowledge-base/discovery/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array('sanitize_callback' => 'sanitize_text_field'),
                'sort' => array('sanitize_callback' => 'sanitize_key'),
                'limit' => array('sanitize_callback' => 'absint'),
            ),
        ));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE, '/knowledge-base/discovery/suggest', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_suggest'),
            'permission_callback' => '__return_true',
        ));
    }

    private function published_articles() {
        return get_posts(array(
            'post_type' => SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => array('menu_order' => 'ASC', 'modified' => 'DESC'),
            'suppress_filters' => false,
        ));
    }

    public function rest_schema() {
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'sorts' => array('relevance', 'recent', 'title'),
            'query_parameters' => array('q', 'sort', 'limit'),
            'public' => true,
            'personal_data_stored' => false,
        ));
    }

    public function rest_search($request) {
        $query = sanitize_text_field((string) $request->get_param('q'));
        $sort = sanitize_key((string) $request->get_param('sort'));
        if (!in_array($sort, array('relevance', 'recent', 'title'), true)) $sort = 'relevance';
        $limit = min(50, max(1, absint($request->get_param('limit') ?: 20)));
        $rows = array_slice($this->rank_articles($this->published_articles(), $query, $sort), 0, $limit);
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'query' => $this->normalize_query($query),
            'sort' => $sort,
            'count' => count($rows),
            'results' => array_map(array($this, 'serialize_result'), $rows),
            'personal_data_stored' => false,
        ));
    }

    public function rest_suggest($request) {
        $query = sanitize_text_field((string) $request->get_param('q'));
        return rest_ensure_response(array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'query' => $this->normalize_query($query),
            'suggestions' => $this->suggest_queries($query, $this->published_articles(), 8),
            'personal_data_stored' => false,
        ));
    }
}
