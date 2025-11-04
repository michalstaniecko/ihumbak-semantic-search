# Fuzzy Search - Wyszukiwanie z tolerancją na literówki

## Przegląd

Fuzzy Search to funkcja rozszerzająca możliwości pluginu Ihumbak Semantic Search o tolerancję na literówki i błędy ortograficzne. System automatycznie aktywuje się jako fallback, gdy standardowe wyszukiwanie (keyword + semantic) nie zwróci żadnych wyników.

## Jak działa

### Algorytmy

System wykorzystuje kombinację dwóch algorytmów do oceny podobieństwa tekstu:

1. **similar_text()** - mierzy procentowe podobieństwo dwóch ciągów znaków
2. **levenshtein()** - oblicza odległość edycyjną (minimalną liczbę operacji potrzebnych do przekształcenia jednego ciągu w drugi)

### Normalizacja tekstu

Przed porównaniem, tekst jest normalizowany:
- Konwersja do małych liter
- Usunięcie polskich znaków diakrytycznych:
  - ą → a
  - ć → c
  - ę → e
  - ł → l
  - ń → n
  - ó → o
  - ś → s
  - ź, ż → z

### Kalkulacja wyniku

Końcowy wynik podobieństwa jest obliczany jako:
- 60% similar_text
- 40% levenshtein
- Plus bonus za pokrywające się słowa (do 20%)

Wyniki poniżej minimalnego progu (domyślnie 0.35) są odrzucane.

## Przykłady użycia

### Domyślna konfiguracja

System działa automatycznie, gdy główne wyszukiwanie nie zwróci wyników:

```php
$search = new \Ihumbak\SemanticSearch\Search\HybridSearch();
$results = $search->search( 'wordpres' ); // Znajdzie "WordPress"
```

### Wyłączanie fuzzy search

Można wyłączyć fuzzy search za pomocą filtra:

```php
add_filter( 'ihumbak_semantic_search_enable_fuzzy', '__return_false' );
```

### Konfiguracja progów

```php
// Niższy próg = więcej wyników, ale mniejsza precyzja
$fuzzy = new \Ihumbak\SemanticSearch\Search\FuzzySearch( 0.25, 200 );

// Wyższy próg = mniej wyników, ale większa precyzja
$fuzzy = new \Ihumbak\SemanticSearch\Search\FuzzySearch( 0.50, 200 );
```

### Konfiguracja typów postów

```php
add_filter( 'ihumbak_fuzzy_search_post_types', function( $post_types ) {
    return array( 'post', 'page', 'product' );
} );
```

## Przykłady dopasowań

Fuzzy search znajdzie posty nawet przy następujących literówkach:

| Zapytanie | Znajdzie |
|-----------|----------|
| wordpres | WordPress |
| wrodpress | WordPress |
| semantic | semantyczny |
| semnatyczny | semantyczny |
| wyszukiwnie | wyszukiwanie |
| plguin | plugin |

## Wydajność i cache

### Mechanizm cache

- Wyniki są cache'owane za pomocą WordPress Transients
- Domyślny czas cache: 1 godzina
- Klucz cache zawiera: zapytanie + próg podobieństwa
- Automatyczne czyszczenie cache przy zapisie/usunięciu posta

### Optymalizacja

System jest zoptymalizowany dla małych i średnich serwisów:
- Domyślny limit kandydatów: 200 postów
- Filtrowanie przed pełną analizą
- Ograniczenie długości tekstu do 255 znaków dla levenshtein
- Wykorzystanie indeksów WordPress dla wstępnej selekcji

### Cache ręczne czyszczenie

```php
$fuzzy = new \Ihumbak\SemanticSearch\Search\FuzzySearch();
$fuzzy->invalidate_cache();
```

## Ograniczenia

1. **Długość tekstu**: Algorytm levenshtein jest ograniczony do 255 znaków
2. **Liczba kandydatów**: Domyślnie sprawdzanych jest maksymalnie 200 postów
3. **Wydajność**: Dla bardzo dużych serwisów (>10k postów) może być potrzebna dodatkowa optymalizacja
4. **Języki**: Normalizacja zoptymalizowana dla języka polskiego

## Integracja z API

Fuzzy search jest transparentnie zintegrowany z endpoint'em REST API:

```
GET /wp-json/ihumbak-semantic-search/v1/search?query=wordpres
```

Zwrócone wyniki fuzzy search będą miały:
- `score`: 0.5
- `type`: "fuzzy"

## Przyszłe ulepszenia

Planowane rozszerzenia funkcjonalności:
- [ ] Konfiguracja wag algorytmów przez WordPress filters
- [ ] Wsparcie dla więcej języków
- [ ] n-gram matching dla lepszej wydajności
- [ ] Phonetic matching (Soundex, Metaphone)
- [ ] Uczenie maszynowe do optymalizacji progów
- [ ] Dedykowany cache backend dla dużych serwisów

## Troubleshooting

### Zbyt wiele fałszywych wyników

Zwiększ minimalny próg:
```php
$fuzzy = new \Ihumbak\SemanticSearch\Search\FuzzySearch( 0.50 );
```

### Brak wyników mimo literówek

Zmniejsz próg lub zwiększ limit kandydatów:
```php
$fuzzy = new \Ihumbak\SemanticSearch\Search\FuzzySearch( 0.25, 500 );
```

### Problemy z wydajnością

1. Zmniejsz limit kandydatów
2. Zwiększ próg podobieństwa
3. Ogranicz typy postów przez filter
4. Rozważ wyłączenie na dużych serwisach

## Wsparcie

W razie problemów lub pytań, utwórz issue na GitHub:
https://github.com/ihumbak/semantic-search/issues
