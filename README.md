# Ihumbak Semantic Search

Semantic Hybrid Search for WordPress + ACF + OpenAI

## Description

This WordPress plugin enables semantic search capabilities by combining traditional SQL FULLTEXT search with OpenAI embeddings for intelligent content discovery. It supports both standard WordPress content and Advanced Custom Fields (ACF).

## Features

- **Hybrid Search**: Combines keyword-based and semantic search
- **OpenAI Integration**: Uses OpenAI embeddings for semantic understanding
- **ACF Support**: Searches through Advanced Custom Fields
- **REST API**: Provides endpoints for AJAX-based search
- **Admin Panel**: Easy configuration and reindexing tools

## Requirements

- WordPress >= 6.0
- PHP >= 8.0
- MySQL >= 5.7
- OpenAI API key

## Installation

1. Upload the plugin files to `/wp-content/plugins/ihumbak-semantic-search/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your OpenAI API key in Settings > Semantic Search
4. Run the initial indexing

## Usage

### Shortcode

```
[semantic_search]
```

### REST API

```
GET /wp-json/semantic-search/v1/search?q=your+query
```

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
