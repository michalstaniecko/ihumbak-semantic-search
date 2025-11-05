/**
 * Admin Reindex JavaScript
 *
 * Handles batch reindexing of posts via AJAX
 */
/* global semanticSearchAdmin, confirm */
/* eslint-disable no-alert, no-console */
( function () {
	'use strict';

	/**
	 * Reindex Manager Class
	 */
	class ReindexManager {
		constructor() {
			this.isRunning = false;
			this.totalIndexed = 0;
			this.totalFailed = 0;
			this.totalPosts = 0;
			this.currentOffset = 0;
			this.batchSize = 10;

			this.elements = {
				button: document.getElementById( 'reindex-button' ),
				progressContainer:
					document.getElementById( 'reindex-progress' ),
				progressBar: document.getElementById( 'reindex-progress-bar' ),
				progressText: document.getElementById(
					'reindex-progress-text'
				),
				statusText: document.getElementById( 'reindex-status' ),
				cancelButton: document.getElementById( 'reindex-cancel' ),
			};

			this.init();
		}

		/**
		 * Initialize event listeners
		 */
		init() {
			if ( ! this.elements.button ) {
				return;
			}

			this.elements.button.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				this.startReindex();
			} );

			if ( this.elements.cancelButton ) {
				this.elements.cancelButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					this.cancelReindex();
				} );
			}
		}

		/**
		 * Start the reindex process
		 */
		async startReindex() {
			if ( this.isRunning ) {
				return;
			}

			if ( ! confirm( semanticSearchAdmin.i18n.confirmReindex ) ) {
				return;
			}

			this.isRunning = true;
			this.totalIndexed = 0;
			this.totalFailed = 0;
			this.currentOffset = 0;

			// Show progress container
			this.elements.progressContainer.style.display = 'block';
			this.elements.button.disabled = true;
			this.elements.button.textContent =
				semanticSearchAdmin.i18n.reindexing;

			// Get initial status
			try {
				const status = await this.getStatus();
				this.totalPosts = status.total_posts;
				this.updateProgress( 0, this.totalPosts );

				// Start processing batches
				await this.processBatches();
			} catch ( error ) {
				this.handleError( error );
			}
		}

		/**
		 * Process batches sequentially
		 */
		async processBatches() {
			while ( this.isRunning ) {
				try {
					const result = await this.processBatch();

					this.totalIndexed += result.success;
					this.totalFailed += result.failed;

					const processed = this.totalIndexed + this.totalFailed;
					this.updateProgress( processed, this.totalPosts );

					if ( ! result.has_more ) {
						this.completeReindex();
						break;
					}

					this.currentOffset = result.next_offset;
				} catch ( error ) {
					this.handleError( error );
					break;
				}
			}
		}

		/**
		 * Process a single batch
		 */
		async processBatch() {
			const response = await fetch(
				semanticSearchAdmin.apiUrl + '/reindex/batch',
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': semanticSearchAdmin.nonce,
					},
					body: JSON.stringify( {
						offset: this.currentOffset,
						batch_size: this.batchSize,
						force_reindex: true,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Batch request failed' );
			}

			return await response.json();
		}

		/**
		 * Get reindex status
		 */
		async getStatus() {
			const response = await fetch(
				semanticSearchAdmin.apiUrl + '/reindex/status',
				{
					headers: {
						'X-WP-Nonce': semanticSearchAdmin.nonce,
					},
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Status request failed' );
			}

			return await response.json();
		}

		/**
		 * Update progress UI
		 *
		 * @param {number} processed Number of posts processed
		 * @param {number} total     Total number of posts
		 */
		updateProgress( processed, total ) {
			const percentage =
				total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;

			this.elements.progressBar.style.width = percentage + '%';
			this.elements.progressBar.setAttribute(
				'aria-valuenow',
				percentage
			);
			this.elements.progressBar.textContent = percentage + '%';

			const statusMessage = semanticSearchAdmin.i18n.processing
				.replace( '{processed}', processed )
				.replace( '{total}', total );

			this.elements.progressText.textContent = statusMessage;

			if ( this.totalFailed > 0 ) {
				const failedMessage =
					semanticSearchAdmin.i18n.failedCount.replace(
						'{failed}',
						this.totalFailed
					);
				this.elements.statusText.textContent = failedMessage;
				this.elements.statusText.className =
					'notice notice-warning inline';
			}
		}

		/**
		 * Complete reindex process
		 */
		completeReindex() {
			this.isRunning = false;
			this.elements.button.disabled = false;
			this.elements.button.textContent =
				semanticSearchAdmin.i18n.reindexButton;

			const successMessage = semanticSearchAdmin.i18n.completed
				.replace( '{indexed}', this.totalIndexed )
				.replace( '{failed}', this.totalFailed );

			this.elements.statusText.textContent = successMessage;
			this.elements.statusText.className = 'notice notice-success inline';
			this.elements.statusText.style.display = 'block';

			// Hide progress after a delay
			setTimeout( () => {
				this.elements.progressContainer.style.display = 'none';
			}, 3000 );
		}

		/**
		 * Cancel reindex process
		 */
		cancelReindex() {
			if ( ! this.isRunning ) {
				return;
			}

			this.isRunning = false;
			this.elements.button.disabled = false;
			this.elements.button.textContent =
				semanticSearchAdmin.i18n.reindexButton;

			this.elements.statusText.textContent =
				semanticSearchAdmin.i18n.cancelled;
			this.elements.statusText.className = 'notice notice-warning inline';
			this.elements.statusText.style.display = 'block';

			setTimeout( () => {
				this.elements.progressContainer.style.display = 'none';
				this.elements.statusText.style.display = 'none';
			}, 3000 );
		}

		/**
		 * Handle errors
		 *
		 * @param {Error} error Error object
		 */
		handleError( error ) {
			if ( window.console && window.console.error ) {
				window.console.error( 'Reindex error:', error );
			}

			this.isRunning = false;
			this.elements.button.disabled = false;
			this.elements.button.textContent =
				semanticSearchAdmin.i18n.reindexButton;

			this.elements.statusText.textContent =
				semanticSearchAdmin.i18n.error;
			this.elements.statusText.className = 'notice notice-error inline';
			this.elements.statusText.style.display = 'block';

			setTimeout( () => {
				this.elements.progressContainer.style.display = 'none';
			}, 5000 );
		}
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			new ReindexManager();
		} );
	} else {
		new ReindexManager();
	}
} )();
