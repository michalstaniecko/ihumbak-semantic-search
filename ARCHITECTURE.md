# Hybrid Search Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        User Query                                │
│                   "WordPress tutorials"                          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    HybridSearch                                  │
│                                                                   │
│  ┌──────────────────┐              ┌──────────────────┐        │
│  │ KeywordSearch    │              │ SemanticSearch   │        │
│  │                  │              │                  │        │
│  │ • FULLTEXT       │              │ • Embeddings     │        │
│  │ • Top 50 posts   │              │ • Cosine Sim     │        │
│  │ • Relevance      │              │ • Reranking      │        │
│  └────────┬─────────┘              └────────┬─────────┘        │
│           │                                  │                   │
│           └────────────┬────────────────────┘                   │
│                        │                                         │
│                        ▼                                         │
│           ┌────────────────────────┐                            │
│           │  Score Combination     │                            │
│           │  70% Semantic          │                            │
│           │  30% Keyword           │                            │
│           └────────────┬───────────┘                            │
│                        │                                         │
│                        ▼                                         │
│           ┌────────────────────────┐                            │
│           │   CacheManager         │                            │
│           │   Store Results        │                            │
│           └────────────┬───────────┘                            │
└────────────────────────┼───────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Top 10 Results                                │
│  1. Post 5 (score: 0.89) - "Complete WP Tutorial"              │
│  2. Post 1 (score: 0.86) - "WordPress Basics"                  │
│  3. Post 12 (score: 0.82) - "Plugin Development"               │
│  ...                                                             │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
WordPress Database                     OpenAI API
─────────────────                      ──────────
       │                                    │
       │ FULLTEXT Search                   │ Generate
       │ (wp_posts)                        │ Embedding
       ▼                                    ▼
┌──────────────┐                    ┌──────────────┐
│ 50 Posts IDs │                    │ Query Vector │
│ + Relevance  │                    │ [0.1, 0.2,   │
│ Scores       │                    │  ..., 0.n]   │
└──────┬───────┘                    └──────┬───────┘
       │                                    │
       │                                    │
       ▼                                    ▼
┌──────────────────────────────────────────────────┐
│    wp_semantic_embeddings Table                  │
│    Load embeddings for 50 posts                  │
│    Calculate similarity with query               │
└──────────────────┬───────────────────────────────┘
                   │
                   ▼
        ┌──────────────────┐
        │ Combined Scores  │
        │ Normalized       │
        │ Weighted         │
        └──────┬───────────┘
               │
               ▼
        ┌──────────────────┐
        │ WordPress Cache  │
        │ (Object Cache)   │
        └──────────────────┘
```

## Search Modes Comparison

```
Mode: HYBRID (Recommended)
─────────────────────────
Query: "machine learning"
↓
1. FULLTEXT → 50 posts in 10ms
2. OpenAI → 1 embedding in 200ms
3. Similarity → 50 calculations in 5ms
4. Combine → top 10 in 1ms
─────────────────────────
Total: ~216ms (cached: ~5ms)
Accuracy: High
Recall: High


Mode: KEYWORD ONLY
──────────────────
Query: "machine learning"
↓
1. FULLTEXT → 10 posts in 10ms
─────────────────────────
Total: ~10ms
Accuracy: Medium
Recall: Medium (exact match bias)


Mode: SEMANTIC ONLY
───────────────────
Query: "machine learning"
↓
1. OpenAI → 1 embedding in 200ms
2. Load ALL embeddings from DB
3. Similarity → 1000+ calculations in 50ms
─────────────────────────
Total: ~250ms (cached: ~50ms)
Accuracy: High
Recall: High (semantic understanding)
```

## Score Calculation Example

```
Post ID: 42
Title: "Introduction to WordPress Plugin Development"

Step 1: Keyword Score (FULLTEXT)
────────────────────────────────
Query: "wordpress plugins"
MySQL MATCH() returns: 1.85
Normalized: 1.0 (highest among 50)

Step 2: Semantic Score
──────────────────────
Query Embedding:     [0.12, -0.34, 0.56, ...]
Post 42 Embedding:   [0.15, -0.31, 0.53, ...]

Cosine Similarity:
  dot_product = (0.12*0.15 + -0.34*-0.31 + ...)
  magnitude_q = sqrt(0.12² + -0.34² + ...)
  magnitude_p = sqrt(0.15² + -0.31² + ...)
  
  similarity = dot / (mag_q * mag_p)
             = 0.87

Normalized: 0.95 (second highest)

Step 3: Weighted Combination
─────────────────────────────
final_score = (0.95 * 0.7) + (1.0 * 0.3)
            = 0.665 + 0.30
            = 0.965

Rank: #1 (highest combined score)
```

## Cache Strategy

```
Request 1 (10:00:00)
────────────────────
Query: "wordpress"
Cache: MISS
→ Full search pipeline
→ Store in cache (TTL: 3600s)
Response time: 216ms

Request 2 (10:00:05)
────────────────────
Query: "wordpress"
Cache: HIT
→ Return cached results
Response time: 5ms

Request 3 (11:00:01)
────────────────────
Query: "wordpress"
Cache: EXPIRED (TTL passed)
→ Full search pipeline
→ Refresh cache
Response time: 216ms

Post Update Event (10:30:00)
────────────────────────────
→ Flush all caches
→ Next request: MISS
```

## REST API Flow

```
Client Request
──────────────
GET /wp-json/semantic-search/v1/search?q=tutorial&limit=5

↓

WordPress REST API
──────────────────
1. Route matching
2. Parameter validation
3. Permission check

↓

SearchEndpoint::handle_search()
───────────────────────────────
1. Check cache
2. If miss: HybridSearch::search()
3. Store in cache
4. Format response

↓

JSON Response
─────────────
{
  "results": [
    {
      "post": {WP_Post},
      "score": 0.92,
      "permalink": "https://...",
      "excerpt": "..."
    }
  ],
  "cached": false,
  "count": 5
}
```

## Database Schema Usage

```
Search Query: "best practices"
└─→ wp_posts
    └─→ FULLTEXT(post_title, post_content)
        └─→ Returns: [1, 5, 12, 23, ...]

For each post ID:
└─→ wp_semantic_embeddings
    └─→ WHERE post_id = ? AND field_type = 'post_content'
        └─→ Returns: embedding LONGTEXT (JSON array)

Cache Storage:
└─→ wp_options (or object cache)
    └─→ Key: ihumbak_semantic_search_search_{md5_hash}
        └─→ Value: serialized results array
```
