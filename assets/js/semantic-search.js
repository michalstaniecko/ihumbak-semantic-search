/**
 * Semantic Search Frontend JavaScript
 */
(function($) {
	'use strict';

	class SemanticSearch {
		constructor(wrapper) {
			this.$wrapper = $(wrapper);
			this.$form = this.$wrapper.find('.semantic-search-form');
			this.$input = this.$wrapper.find('.semantic-search-input');
			this.$button = this.$wrapper.find('.semantic-search-button');
			this.$loading = this.$wrapper.find('.semantic-search-loading');
			this.$results = this.$wrapper.find('.semantic-search-results');
			this.$resultsList = this.$wrapper.find('.semantic-search-results-list');
			this.$resultsCount = this.$wrapper.find('.semantic-search-results-count');
			this.$clearButton = this.$wrapper.find('.semantic-search-clear');
			this.$error = this.$wrapper.find('.semantic-search-error');

			this.config = {
				limit: this.$wrapper.data('limit') || 10,
				postType: this.$wrapper.data('post-type') || 'post,page',
				showExcerpt: this.$wrapper.data('show-excerpt') === 'yes',
				showThumbnail: this.$wrapper.data('show-thumbnail') === 'yes',
				mode: this.$wrapper.data('mode') || 'hybrid'
			};

			this.init();
		}

		init() {
			this.$form.on('submit', (e) => this.handleSubmit(e));
			this.$clearButton.on('click', () => this.clearResults());
			this.$input.on('keyup', (e) => this.handleKeyup(e));
		}

		handleSubmit(e) {
			e.preventDefault();
			const query = this.$input.val().trim();

			if (!query) {
				return;
			}

			this.performSearch(query);
		}

		handleKeyup(e) {
			// Clear results on Escape
			if (e.keyCode === 27) {
				this.clearResults();
			}
		}

		async performSearch(query) {
			this.showLoading();
			this.hideError();

			const params = new URLSearchParams({
				q: query,
				limit: this.config.limit,
				post_type: this.config.postType,
				mode: this.config.mode
			});

			try {
				const response = await fetch(
					`${semanticSearchConfig.apiUrl}?${params}`,
					{
						headers: {
							'X-WP-Nonce': semanticSearchConfig.nonce
						}
					}
				);

				if (!response.ok) {
					throw new Error('Search request failed');
				}

				const data = await response.json();
				this.displayResults(data, query);
			} catch (error) {
				console.error('Search error:', error);
				this.showError(semanticSearchConfig.i18n.error);
			} finally {
				this.hideLoading();
			}
		}

		displayResults(data, query) {
			const { results, count, cached } = data;

			if (count === 0) {
				this.showError(semanticSearchConfig.i18n.noResults);
				return;
			}

			// Update count
			let countText = `${count} ${semanticSearchConfig.i18n.resultsFound}`;
			if (cached) {
				countText += ` ${semanticSearchConfig.i18n.cached}`;
			}
			this.$resultsCount.text(countText);

			// Clear previous results
			this.$resultsList.empty();

			// Render results
			results.forEach((result) => {
				this.$resultsList.append(this.renderResult(result));
			});

			// Show results
			this.$results.show();
		}

		renderResult(result) {
			const { post, score, permalink, excerpt } = result;
			const scorePercent = Math.round(score * 100);

			let html = '<div class="semantic-search-result">';

			// Thumbnail
			if (this.config.showThumbnail && post.featured_image) {
				html += `
					<div class="semantic-search-result-thumbnail">
						<img src="${post.featured_image}" alt="${this.escapeHtml(post.post_title)}" />
					</div>
				`;
			}

			html += '<div class="semantic-search-result-content">';

			// Title
			html += `
				<h3 class="semantic-search-result-title">
					<a href="${permalink}">${this.escapeHtml(post.post_title)}</a>
				</h3>
			`;

			// Excerpt
			if (this.config.showExcerpt && excerpt) {
				html += `
					<div class="semantic-search-result-excerpt">
						${this.escapeHtml(excerpt)}
					</div>
				`;
			}

			// Meta
			html += `
				<div class="semantic-search-result-meta">
					<span class="semantic-search-result-type">${post.post_type}</span>
					<span class="semantic-search-result-score" title="Relevance Score">${scorePercent}%</span>
				</div>
			`;

			html += '</div>'; // .semantic-search-result-content
			html += '</div>'; // .semantic-search-result

			return html;
		}

		showLoading() {
			this.$loading.show();
			this.$button.prop('disabled', true);
			this.$results.hide();
		}

		hideLoading() {
			this.$loading.hide();
			this.$button.prop('disabled', false);
		}

		showError(message) {
			this.$error.text(message).show();
			this.$results.hide();
		}

		hideError() {
			this.$error.hide();
		}

		clearResults() {
			this.$input.val('');
			this.$results.hide();
			this.$resultsList.empty();
			this.hideError();
		}

		escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	}

	// Initialize on document ready
	$(document).ready(function() {
		$('.semantic-search-wrapper').each(function() {
			new SemanticSearch(this);
		});
	});

})(jQuery);
