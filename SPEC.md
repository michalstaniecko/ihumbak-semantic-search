# 1. Przegląd projektu
- **Nazwa:** Semantic Hybrid Search for WordPress + ACF + OpenAI
- **Cel:** Umożliwienie wyszukiwania semantycznego w treściach WP oraz polach ACF, przez połączenie klasycznego wyszukiwania SQL FULLTEXT i porównania embeddingów z OpenAI.
- **Technologie:** PHP, MySQL, WordPress, OpenAI API

# 2. FAZA 0: Przygotowanie środowiska
- Konfiguracja repozytorium na GitHub
- Skonfigurowanie GitHub Actions:
  - Workflow CI (testy PHP, lint, composer validate)
  - Workflow Release (release automatyczny po tagu)
  - Workflow Dependency Update (cotygodniowe sprawdzanie zależności)
- Przygotowanie plików:
  - `.github/workflows/ci.yml` – testy, lint
  - `.github/workflows/release.yml` – release
  - `.github/workflows/dependency-updates.yml` – aktualizacje
  - `.distignore`, `composer.json`, `package.json` – zależności i build
- Ustalenie strategii branżowania (main/develop/feature/release)
- Przygotowanie plików: README, CONTRIBUTING.md, Issue/PR Templates

# 3. Architektura techniczna (wysoki poziom)
- Indeksowanie: Posty i ACF → embeddingi (OpenAI) → zapis w wp_semantic_embeddings
- Wyszukiwanie: FULLTEXT na wp_posts → reranking dla top X przez cosine similarity embeddingów
- Panel admina: ustawienia, reindeksacja, monitorowanie stanu indeksu
- Frontend: Shortcode, AJAX, REST endpoint

# 4. Fazy prac
- **Faza 0:** Setup repo, workflow, release automation
- **Faza 1:** Schemat bazy, migrator
- **Faza 2:** Backend (indeksowanie, obsługa ACF, integracja OpenAI)
- **Faza 3:** Hybrid Search (keyword + semantic rerank), cache
- **Faza 4:** Frontend (form, shortcode)
- **Faza 5:** Optymalizacje, cache, background jobs
- **Faza 6:** Testy, dokumentacja, release 1.0.0

# 5. Przykładowe rozwiązania i kod
- SQL tworzenia tabeli embeddingów
- Przykład wywołania OpenAI API
- Funkcja cosine similarity w PHP
- Przykład workflow CI (PHPUnit, lint)

# 6. Release & CI/CD
- Release automatyczny na GitHub po utworzeniu taga vX.Y.Z
- Testy, lint, budowa paczki WordPress (z .distignore)
- Codzienne dependency update
- DRY-run release dla wersji beta

# 7. System Requirements
- WordPress >= 6.0
- PHP >= 8.0
- MySQL >= 5.7
- Konto i klucz OpenAI

# 8. Configuration
- OpenAI API key można skonfigurować na dwa sposoby:
  1. Przez stałą w wp-config.php: `define('IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY', 'sk-...');`
  2. Przez panel administracyjny w Settings > Semantic Search
- Stała w wp-config.php ma wyższy priorytet niż opcja z bazy danych
- Zalecane jest używanie stałej w środowisku produkcyjnym dla bezpieczeństwa

# 8. Ryzyka
- Ograniczenia API (rate limit OpenAI)
- Koszt indeksowania dużych baz danych
- Wydajność cache
- Utrzymanie zgodności workflow z WP

# 9. Plan sprintów (po jednym tygodniu na fazę + 0):
 - Sprint 0: setup środowiska
 - Sprint 1: baza
 - Sprint 2: backend
 - Sprint 3: wyszukiwanie
 - Sprint 4: frontend
 - Sprint 5: optymalizacje
 - Sprint 6: testy+release

## Przykłady kodu i plików workflow znajdują się w następnych zadaniach/fazach.
