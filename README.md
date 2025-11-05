# Ihumbak Semantic Search

Semantic Hybrid Search for WordPress + ACF + OpenAI

## Description

This WordPress plugin enables semantic search capabilities by combining traditional SQL FULLTEXT search with OpenAI embeddings for intelligent content discovery. It supports both standard WordPress content and Advanced Custom Fields (ACF).

## Features

- **Hybrid Search**: Combines keyword-based and semantic search
- **OpenAI Integration**: Uses OpenAI embeddings for semantic understanding
- **ACF Support**: Searches through Advanced Custom Fields
- **REST API**: Provides endpoints for AJAX-based search
- **Admin Panel**: Easy configuration and batch reindexing tools
- **Batch Processing**: AJAX-based reindexing prevents server timeouts with large post counts

## Requirements

- WordPress >= 6.0
- PHP >= 8.0
- MySQL >= 5.7
- OpenAI API key

## Installation

1. Upload the plugin files to `/wp-content/plugins/ihumbak-semantic-search/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your OpenAI API key in Settings > Semantic Search OR add it to wp-config.php:
   ```php
   define( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY', 'your-api-key-here' );
   ```
4. Run the initial indexing

## Configuration

### OpenAI API Key

You can configure the OpenAI API key in two ways:

1. **Via wp-config.php (Recommended for production):**
   ```php
   define( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY', 'sk-...' );
   ```

2. **Via Settings Page:**
   Go to Settings > Semantic Search and enter your API key in the settings form.

**Note:** If the constant is defined in wp-config.php, it will take precedence over the settings page option.

## Usage

### Shortcode

Add the search form to any post or page:

```
[semantic_search]
```

With custom attributes:

```
[semantic_search 
    placeholder="Search..." 
    limit="10" 
    post_type="post,page" 
    mode="hybrid"
    show_excerpt="yes"
    show_thumbnail="no"
]
```

See [FRONTEND_GUIDE.md](FRONTEND_GUIDE.md) for complete shortcode documentation.

### Widget

1. Go to **Appearance > Widgets**
2. Add the **Semantic Search** widget to your sidebar
3. Configure the widget settings

### REST API

```
GET /wp-json/semantic-search/v1/search?q=your+query
```

Parameters:
- `q` (required): Search query
- `limit` (optional): Number of results, default 10
- `post_type` (optional): Comma-separated post types, default "post,page"
- `mode` (optional): "hybrid", "keyword", or "semantic", default "hybrid"

### Reindexing Posts

The plugin provides an AJAX-based batch reindexing feature to handle large numbers of posts without server timeouts:

1. Go to **Settings > Semantic Search**
2. Click the **Reindex All Posts** button
3. The reindexing process will run in batches, showing a progress bar
4. You can cancel the process at any time

The reindexing processes posts in batches of 10 by default, with each batch completing before the next one starts. This prevents server timeouts and provides real-time progress updates.

## Development

### Setup

```bash
composer install
npm install
```

### Build

```bash
npm run build
```

### Testing

```bash
composer test
composer lint
composer stan
```

## License

GPL-2.0-or-later

## Author

Michal Staniecko - [ihumbak.com](https://ihumbak.com)
