# Przykłady kodu i gotowe rozwiązania dla Semantic Hybrid Search

## Faza 0: Przygotowanie środowiska
### .github/workflows/ci.yml
```yaml
name: WordPress CI
on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping --silent" --health-interval=10s --health-timeout=5s --health-retries=3
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-php@v3
        with:
          php-version: '8.2'
      - run: composer install
      - run: npm install
      - name: Lint PHP
        run: vendor/bin/phpcs --standard=phpcs.xml .
      - name: Static Analysis
        run: vendor/bin/phpstan analyse
      - name: PHPUnit
        run: vendor/bin/phpunit --testdox
```

### .github/workflows/release.yml
```yaml
name: Release
on:
  push:
    tags:
      - 'v*.*.*'
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Composer install
        run: composer install --no-dev --optimize-autoloader
      - name: Npm build
        run: npm run build
      - name: Make zip
        run: zip -r semantic-acf-search.zip . -x@.distignore
      - uses: softprops/action-gh-release@v2
        with:
          files: semantic-acf-search.zip
```

### Przykładowe pliki konfiguracyjne
**composer.json**:
```json
{
  "name": "michalstaniecko/semantic-acf-search",
  "require": {
    "php": ">=8.0",
    "phpunit/phpunit": "^10.0",
    "wp-coding-standards/wpcs": "^3.0",
    "phpstan/phpstan": "^1.10"
  },
  "scripts": {
    "lint": "phpcs .",
    "stan": "phpstan analyse"
  }
}
```

**package.json**:
```json
{
  "name": "semantic-acf-search",
  "scripts": {
    "build": "wp-scripts build",
    "test": "jest"
  },
  "devDependencies": {
    "@wordpress/scripts": "^25.0.0",
    "eslint": "^8.0.0",
    "jest": "^29.0.0"
  }
}
```

## Faza 1: Schemat DB
### Tworzenie tabeli na embeddingi w MySQL
```sql
CREATE TABLE wp_semantic_embeddings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  content_hash VARCHAR(64) NOT NULL,
  embedding LONGTEXT NOT NULL,
  field_type VARCHAR(50),
  indexed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_post_id (post_id),
  INDEX idx_content_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Dodanie FULLTEXT index na wp_posts
```sql
ALTER TABLE wp_posts ADD FULLTEXT INDEX fulltext_title_content (post_title, post_content);
```

## Faza 2: Embeddings (PHP)
### Przykładowa klasa obsługi embeddings (fragment)
```php
class Embeddings {
    private $api_key;
    public function __construct() {
        $this->api_key = get_option('semantic_search_openai_key');
    }
    public function generate_embedding($text) {
        $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'input' => $text,
                'model' => 'text-embedding-3-small',
            ]),
        ]);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'][0]['embedding'] ?? [];
    }
    public function cosine_similarity($vec1, $vec2) {
        $dot = 0; $mag1 = 0; $mag2 = 0;
        foreach ($vec1 as $i => $v) {
            $dot += $v * $vec2[$i];
            $mag1 += $v * $v;
            $mag2 += $vec2[$i] * $vec2[$i];
        }
        return ($mag1 && $mag2) ? $dot / (sqrt($mag1) * sqrt($mag2)) : 0;
    }
}
```

## Faza 3: Hybrid Search (PHP)
### Example: hybrydowe wyszukiwanie
```php
// Keyword Search
$posts = $wpdb->get_results($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts}
      WHERE MATCH(post_title,post_content) AGAINST (%s IN NATURAL LANGUAGE MODE)
      AND post_status = 'publish' LIMIT 50",
    $query
));
// Semantic rerank
$embed = $embeddings->generate_embedding($query);
$scored = [];
foreach ($posts as $post) {
  $post_emb = get_post_embedding($post->ID); // Funkcja pomocnicza
  $score = $embeddings->cosine_similarity($embed, $post_emb);
  $scored[] = ['post_id' => $post->ID, 'similarity' => $score];
}
usort($scored, fn($a,$b) => $b['similarity'] <=> $a['similarity']);
$top = array_slice($scored, 0, 10);
```

## Faza 4: Frontend (shortcode)
### Shortcode do formularza + wyświetlania wyników
```php
function semantic_search_shortcode() {
  ob_start();
  ?>
  <form id="semantic-search-form"><input type="text" name="q" id="semantic-search-q" placeholder="Wpisz zapytanie"><button type="submit">Szukaj</button></form>
  <div id="semantic-search-results"></div>
  <script>
    document.getElementById('semantic-search-form').addEventListener('submit',async function(e){
      e.preventDefault();
      let val=document.getElementById('semantic-search-q').value;
      let res=await fetch('/wp-json/semantic-search/v1/search?q='+encodeURIComponent(val));
      let data=await res.json();
      let html='';
      data.results.forEach(function(r){
        html+='<div><a href="'+r.permalink+'">'+r.post.post_title+'</a></div>';
      });
      document.getElementById('semantic-search-results').innerHTML=html;
    });
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('semantic_search', 'semantic_search_shortcode');
```

## Faza 5: REST API Endpoint
```php
add_action('rest_api_init', function() {
  register_rest_route('semantic-search/v1', '/search', [
    'methods' => 'GET',
    'callback' => function($request) {
      $query = $request->get_param('q');
      $search = new HybridSearch();
      $results = $search->search($query);
      return rest_ensure_response(['results' => $results]);
    },
  ]);
});
```

## Faza 6: Panel admina - fragment UI
```php
function render_semantic_admin_settings() {
  ?>
  <form method="post">
    <?php if ( ! defined( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY' ) ) : ?>
    <label>OpenAI API Key: <input type="password" name="openai_key" value="<?php echo esc_attr(get_option('semantic_search_openai_key')); ?>" /></label>
    <?php else : ?>
    <p>OpenAI API Key skonfigurowany w wp-config.php</p>
    <?php endif; ?>
    <input type="submit" value="Zapisz">
  </form>
  <form method="post"><button name="reindex_all" value="1">Reindeksuj wszystkie posty</button></form>
  <?php if (isset($_POST['reindex_all'])) {
    $n = Indexer::reindex_all_posts();
    echo '<p>Zaindeksowano '.$n.' postów.</p>';
  }
}
```

## Konfiguracja API Key
Plugin wspiera dwa sposoby konfiguracji klucza OpenAI:

1. **Przez wp-config.php (Zalecane)**:
```php
define( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY', 'sk-your-api-key' );
```

2. **Przez panel administracyjny**:
Settings > Semantic Search > OpenAI API Key

Stała w wp-config.php ma pierwszeństwo przed opcją z bazy danych.

## Faza 7: Test jednostkowy
```php
public function test_cosine_similarity() {
  $e = new Embeddings();
  $a = [1,2,3];
  $b = [1,2,3];
  $this->assertEquals(1, $e->cosine_similarity($a,$b));
  $c = [0,0,1];
  $this->assertLessThan(0.8, $e->cosine_similarity($a,$c));
}
```

## Faza 8: Dodanie release/tagu
- Tworzysz nowy tag git: `git tag v1.0.0 && git push origin v1.0.0`
- Akcja GitHub automatycznie buduje i publikuje paczkę na Releases

