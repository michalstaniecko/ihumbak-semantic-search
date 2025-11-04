# Frontend Usage Guide

## Shortcode

### Basic Usage

Add the search form to any post or page using the shortcode:

```
[semantic_search]
```

### Shortcode Attributes

All attributes are optional:

| Attribute | Default | Description |
|-----------|---------|-------------|
| `placeholder` | "Enter your search query..." | Placeholder text for the search input |
| `button_text` | "Search" | Text displayed on the search button |
| `limit` | `10` | Maximum number of results to display |
| `post_type` | `post,page` | Comma-separated list of post types to search |
| `show_excerpt` | `yes` | Show post excerpts in results (`yes` or `no`) |
| `show_thumbnail` | `no` | Show featured images in results (`yes` or `no`) |
| `mode` | `hybrid` | Search mode: `hybrid`, `keyword`, or `semantic` |
| `class` | `` | Additional CSS classes for the wrapper |

### Examples

#### Basic search with custom text:
```
[semantic_search placeholder="What are you looking for?" button_text="Find"]
```

#### Search only posts with thumbnails:
```
[semantic_search post_type="post" show_thumbnail="yes" limit="5"]
```

#### Semantic-only search:
```
[semantic_search mode="semantic" limit="15"]
```

#### Advanced customization:
```
[semantic_search 
    placeholder="Search our knowledge base..." 
    button_text="Search Now"
    limit="20"
    post_type="post,page,product"
    show_excerpt="yes"
    show_thumbnail="yes"
    mode="hybrid"
    class="custom-search-form"
]
```

## Widget

### Adding the Widget

1. Go to **Appearance > Widgets** in WordPress admin
2. Find **Semantic Search** widget
3. Drag it to your desired sidebar or widget area
4. Configure the widget settings:
   - **Title**: Widget title (optional)
   - **Number of Results**: How many results to show (1-50)
   - **Post Types**: Comma-separated post types to search
   - **Search Mode**: Hybrid, Keyword Only, or Semantic Only

### Widget Settings

- **Title**: Optional title displayed above the search form
- **Number of Results**: Default is 10, maximum is 50
- **Post Types**: Default is "post,page"
- **Search Mode**: Default is "hybrid"

## Styling

### Custom CSS

You can override the default styles by adding custom CSS to your theme:

```css
/* Make search input larger */
.semantic-search-input {
    font-size: 18px;
    padding: 15px 20px;
}

/* Change button color */
.semantic-search-button {
    background-color: #ff5733;
}

.semantic-search-button:hover {
    background-color: #c44221;
}

/* Customize results */
.semantic-search-result {
    background-color: #f9f9f9;
    border-left: 3px solid #0073aa;
}

/* Hide score percentage */
.semantic-search-result-score {
    display: none;
}
```

### CSS Classes Reference

#### Wrapper & Structure
- `.semantic-search-wrapper` - Main container
- `.semantic-search-form` - Form element
- `.semantic-search-input-wrapper` - Input and button container
- `.semantic-search-input` - Search input field
- `.semantic-search-button` - Search button
- `.semantic-search-loading` - Loading indicator
- `.semantic-search-spinner` - Spinning loader icon

#### Results
- `.semantic-search-results` - Results container
- `.semantic-search-results-header` - Results header with count and clear button
- `.semantic-search-results-count` - Results count text
- `.semantic-search-clear` - Clear results button
- `.semantic-search-results-list` - List of results
- `.semantic-search-result` - Single result item
- `.semantic-search-result-thumbnail` - Featured image container
- `.semantic-search-result-content` - Result content wrapper
- `.semantic-search-result-title` - Result title
- `.semantic-search-result-excerpt` - Result excerpt
- `.semantic-search-result-meta` - Meta information (type, score)
- `.semantic-search-result-type` - Post type badge
- `.semantic-search-result-score` - Relevance score badge

#### States
- `.semantic-search-error` - Error message container

## JavaScript Events

The search form emits custom events that you can listen to:

```javascript
// Listen for search start
document.addEventListener('semantic-search:start', function(e) {
    console.log('Search started:', e.detail.query);
});

// Listen for search complete
document.addEventListener('semantic-search:complete', function(e) {
    console.log('Search completed:', e.detail.results);
});

// Listen for search error
document.addEventListener('semantic-search:error', function(e) {
    console.log('Search error:', e.detail.error);
});
```

## PHP Filters

Customize the shortcode output using filters:

### Filter shortcode attributes
```php
add_filter('semantic_search_shortcode_atts', function($atts) {
    // Force semantic mode for all searches
    $atts['mode'] = 'semantic';
    return $atts;
});
```

### Filter search results before display
```php
add_filter('semantic_search_results', function($results, $query) {
    // Add custom data to each result
    foreach ($results as &$result) {
        $result['custom_field'] = get_post_meta($result['post']['ID'], 'custom', true);
    }
    return $results;
}, 10, 2);
```

## Programmatic Usage

You can also use the search functionality in your PHP code:

```php
use Ihumbak\SemanticSearch\Search\HybridSearch;

// Initialize search
$search = new HybridSearch();

// Perform search
$results = $search->search('wordpress development', [
    'limit' => 10,
    'post_type' => ['post', 'page'],
    'mode' => 'hybrid'
]);

// Display results
foreach ($results as $result) {
    echo '<h3>' . esc_html($result['post']['post_title']) . '</h3>';
    echo '<p>' . esc_html($result['excerpt']) . '</p>';
    echo '<p>Score: ' . esc_html($result['score']) . '</p>';
}
```

## AJAX Integration

The frontend uses the REST API endpoint. You can make direct AJAX calls:

```javascript
fetch('/wp-json/semantic-search/v1/search?q=wordpress&limit=10&mode=hybrid')
    .then(response => response.json())
    .then(data => {
        console.log('Results:', data.results);
        console.log('Count:', data.count);
        console.log('Cached:', data.cached);
    });
```

## Accessibility

The search form is fully accessible:

- Semantic HTML structure
- ARIA labels on interactive elements
- Keyboard navigation support
- Screen reader friendly
- Focus management

## Performance Tips

1. **Enable Caching**: Make sure caching is enabled in settings for faster repeat searches
2. **Limit Results**: Use smaller `limit` values (5-10) for faster response times
3. **Choose Mode Wisely**:
   - Use `hybrid` for best accuracy (slight performance cost)
   - Use `keyword` for fastest searches
   - Use `semantic` for semantic understanding without keyword filtering
4. **Optimize Post Types**: Only search relevant post types to reduce processing

## Troubleshooting

### Search returns no results
- Check that posts are indexed (Settings > Semantic Search > Indexing)
- Verify OpenAI API key is configured
- Check that FULLTEXT index exists on wp_posts table

### Search is slow
- Enable caching in settings
- Reduce the number of results
- Check your OpenAI API rate limits
- Consider using `keyword` mode instead of `hybrid`

### Styling issues
- Check for CSS conflicts with your theme
- Add `!important` to your custom CSS if needed
- Use browser dev tools to inspect elements

### JavaScript errors
- Check browser console for errors
- Ensure jQuery is loaded
- Verify REST API is accessible

## Examples in the Wild

### Simple blog search
```
[semantic_search placeholder="Search articles..." limit="8" post_type="post"]
```

### Product search with images
```
[semantic_search 
    placeholder="Find products..." 
    button_text="Search Products"
    post_type="product"
    show_thumbnail="yes"
    limit="12"
]
```

### Documentation search
```
[semantic_search 
    placeholder="Search documentation..." 
    post_type="docs"
    mode="semantic"
    show_excerpt="yes"
    limit="15"
]
```

### Sidebar widget
Add the **Semantic Search** widget to your sidebar with:
- Title: "Find What You Need"
- Results: 5
- Post Types: post,page
- Mode: Hybrid
