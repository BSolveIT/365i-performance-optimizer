<?php
/**
 * Database Cleanup Tools.
 *
 * @package WP_Performance_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class I365_PO_Database {
	/**
	 * Initialize database cleanup hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// AJAX handlers for manual cleanup.
		add_action( 'wp_ajax_i365_po_db_analyze', array( __CLASS__, 'ajax_analyze' ) );
		add_action( 'wp_ajax_i365_po_db_cleanup', array( __CLASS__, 'ajax_cleanup' ) );

		// Scheduled cleanup.
		$settings = I365_PO_Plugin::get_settings();
		if ( ! empty( $settings['db_cleanup_enabled'] ) ) {
			add_action( 'i365_po_scheduled_cleanup', array( __CLASS__, 'run_scheduled_cleanup' ) );

			// Schedule event if not already scheduled.
			if ( ! wp_next_scheduled( 'i365_po_scheduled_cleanup' ) ) {
				$schedule = ! empty( $settings['db_cleanup_schedule'] ) ? $settings['db_cleanup_schedule'] : 'weekly';
				wp_schedule_event( time(), $schedule, 'i365_po_scheduled_cleanup' );
			}
		} else {
			// Clear scheduled event if disabled.
			$timestamp = wp_next_scheduled( 'i365_po_scheduled_cleanup' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'i365_po_scheduled_cleanup' );
			}
		}
	}

	/**
	 * Get cleanup statistics.
	 *
	 * @return array
	 */
	public static function get_cleanup_stats() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database cleanup requires fresh counts, caching not appropriate.

		$settings = I365_PO_Plugin::get_settings();
		$revisions_keep = isset( $settings['db_revisions_keep'] ) ? absint( $settings['db_revisions_keep'] ) : 5;

		$stats = array();

		// Post revisions (excess only).
		$stats['revisions'] = array(
			'label' => __( 'Post Revisions', '365i-performance-optimizer' ),
			'count' => self::count_excess_revisions( $revisions_keep ),
			'desc'  => sprintf(
				/* translators: %d: number of revisions to keep */
				__( 'Keeping %d per post', '365i-performance-optimizer' ),
				$revisions_keep
			),
		);

		// Auto-drafts older than 7 days.
		$stats['auto_drafts'] = array(
			'label' => __( 'Old Auto-Drafts', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_status = 'auto-draft'
				 AND post_date < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			) ),
			'desc'  => __( 'Older than 7 days', '365i-performance-optimizer' ),
		);

		// Trashed posts older than 30 days.
		$stats['trashed_posts'] = array(
			'label' => __( 'Old Trashed Posts', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_status = 'trash'
				 AND post_modified < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			) ),
			'desc'  => __( 'Older than 30 days', '365i-performance-optimizer' ),
		);

		// Spam comments.
		$stats['spam_comments'] = array(
			'label' => __( 'Spam Comments', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
			),
			'desc'  => '',
		);

		// Trashed comments.
		$stats['trashed_comments'] = array(
			'label' => __( 'Trashed Comments', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"
			),
			'desc'  => '',
		);

		// Orphaned post meta.
		$stats['orphaned_postmeta'] = array(
			'label' => __( 'Orphaned Post Meta', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				 WHERE p.ID IS NULL"
			),
			'desc'  => '',
		);

		// Orphaned comment meta.
		$stats['orphaned_commentmeta'] = array(
			'label' => __( 'Orphaned Comment Meta', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
				 LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
				 WHERE c.comment_ID IS NULL"
			),
			'desc'  => '',
		);

		// Expired transients.
		$stats['expired_transients'] = array(
			'label' => __( 'Expired Transients', '365i-performance-optimizer' ),
			'count' => $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			) ),
			'desc'  => '',
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $stats;
	}

	/**
	 * Count excess revisions across all posts.
	 *
	 * @param int $keep Number of revisions to keep per post.
	 *
	 * @return int
	 */
	private static function count_excess_revisions( $keep ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database cleanup requires fresh counts.

		if ( $keep <= 0 ) {
			// Count all revisions.
			return (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
			);
		}

		// Count excess revisions per post.
		$excess = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(GREATEST(0, revision_count - %d))
			 FROM (
				 SELECT post_parent, COUNT(*) as revision_count
				 FROM {$wpdb->posts}
				 WHERE post_type = 'revision'
				 GROUP BY post_parent
			 ) as rev_counts",
			$keep
		) );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $excess;
	}

	/**
	 * Run cleanup for specific items.
	 *
	 * @param array $items Items to clean up.
	 *
	 * @return array Results with counts.
	 */
	public static function run_cleanup( $items ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database cleanup operations require direct queries.

		$settings = I365_PO_Plugin::get_settings();
		$revisions_keep = isset( $settings['db_revisions_keep'] ) ? absint( $settings['db_revisions_keep'] ) : 5;
		$results = array();

		// Revisions.
		if ( in_array( 'revisions', $items, true ) ) {
			$results['revisions'] = self::cleanup_revisions( $revisions_keep );
		}

		// Auto-drafts.
		if ( in_array( 'auto_drafts', $items, true ) ) {
			$results['auto_drafts'] = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->posts}
				 WHERE post_status = 'auto-draft'
				 AND post_date < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			) );
		}

		// Trashed posts.
		if ( in_array( 'trashed_posts', $items, true ) ) {
			$results['trashed_posts'] = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->posts}
				 WHERE post_status = 'trash'
				 AND post_modified < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			) );
		}

		// Spam comments.
		if ( in_array( 'spam_comments', $items, true ) ) {
			$results['spam_comments'] = $wpdb->query(
				"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
			);
		}

		// Trashed comments.
		if ( in_array( 'trashed_comments', $items, true ) ) {
			$results['trashed_comments'] = $wpdb->query(
				"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'"
			);
		}

		// Orphaned post meta.
		if ( in_array( 'orphaned_postmeta', $items, true ) ) {
			$results['orphaned_postmeta'] = $wpdb->query(
				"DELETE pm FROM {$wpdb->postmeta} pm
				 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				 WHERE p.ID IS NULL"
			);
		}

		// Orphaned comment meta.
		if ( in_array( 'orphaned_commentmeta', $items, true ) ) {
			$results['orphaned_commentmeta'] = $wpdb->query(
				"DELETE cm FROM {$wpdb->commentmeta} cm
				 LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
				 WHERE c.comment_ID IS NULL"
			);
		}

		// Expired transients.
		if ( in_array( 'expired_transients', $items, true ) ) {
			$results['expired_transients'] = self::cleanup_expired_transients();
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results;
	}

	/**
	 * Cleanup excess revisions keeping specified number per post.
	 *
	 * @param int $keep Number to keep per post.
	 *
	 * @return int Number deleted.
	 */
	private static function cleanup_revisions( $keep ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database cleanup operations.

		$deleted = 0;

		if ( $keep <= 0 ) {
			// Delete all revisions.
			$deleted = $wpdb->query(
				"DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
			);
		} else {
			// Get posts with excess revisions.
			$posts_with_excess = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_parent, COUNT(*) as revision_count
				 FROM {$wpdb->posts}
				 WHERE post_type = 'revision'
				 GROUP BY post_parent
				 HAVING revision_count > %d",
				$keep
			) );

			foreach ( $posts_with_excess as $row ) {
				$parent_id = absint( $row->post_parent );
				$to_delete = absint( $row->revision_count ) - $keep;

				// Get oldest revisions to delete.
				$revision_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type = 'revision'
					 AND post_parent = %d
					 ORDER BY post_date ASC
					 LIMIT %d",
					$parent_id,
					$to_delete
				) );

				if ( ! empty( $revision_ids ) ) {
					$ids_string = implode( ',', array_map( 'absint', $revision_ids ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are sanitized with absint().
					$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids_string})" );
					$deleted += count( $revision_ids );
				}
			}
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $deleted;
	}

	/**
	 * Cleanup expired transients.
	 *
	 * @return int Number deleted.
	 */
	private static function cleanup_expired_transients() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup requires direct query.

		$deleted = 0;

		// Get expired transient timeouts.
		$expired = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			 AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			time()
		) );

		foreach ( $expired as $timeout_option ) {
			// Extract the transient name.
			$transient = str_replace( '_transient_timeout_', '', $timeout_option );

			// Delete both the timeout and value.
			delete_transient( $transient );
			$deleted++;
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $deleted;
	}

	/**
	 * Run scheduled cleanup.
	 *
	 * @return void
	 */
	public static function run_scheduled_cleanup() {
		$settings = I365_PO_Plugin::get_settings();

		// Only run if enabled.
		if ( empty( $settings['db_cleanup_enabled'] ) ) {
			return;
		}

		// Run all cleanup items.
		$items = array(
			'revisions',
			'auto_drafts',
			'trashed_posts',
			'spam_comments',
			'trashed_comments',
			'orphaned_postmeta',
			'orphaned_commentmeta',
			'expired_transients',
		);

		self::run_cleanup( $items );
	}

	/**
	 * AJAX: Get database analysis.
	 *
	 * @return void
	 */
	public static function ajax_analyze() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		$stats = self::get_cleanup_stats();

		wp_send_json_success( array(
			'stats' => $stats,
			'html'  => self::render_stats_html( $stats ),
		) );
	}

	/**
	 * AJAX: Run database cleanup.
	 *
	 * @return void
	 */
	public static function ajax_cleanup() {
		check_ajax_referer( 'i365-po-utilities', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', '365i-performance-optimizer' ) ) );
		}

		// Get items to clean.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization happens with array_map sanitize_text_field.
		$items = isset( $_POST['items'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['items'] ) ) : array();

		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'No items selected for cleanup.', '365i-performance-optimizer' ) ) );
		}

		// Create backup before cleanup.
		I365_PO_Plugin::backup_current_settings();

		// Run cleanup.
		$results = self::run_cleanup( $items );

		// Calculate total.
		$total = array_sum( array_map( 'absint', $results ) );

		// Get updated stats.
		$stats = self::get_cleanup_stats();

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of items cleaned */
				__( 'Cleanup complete. %d items removed.', '365i-performance-optimizer' ),
				$total
			),
			'results' => $results,
			'stats'   => $stats,
			'html'    => self::render_stats_html( $stats ),
		) );
	}

	/**
	 * Render stats HTML for AJAX response.
	 *
	 * @param array $stats Stats array.
	 *
	 * @return string HTML.
	 */
	public static function render_stats_html( $stats ) {
		$html = '<div class="i365-po-db-stats">';

		foreach ( $stats as $key => $stat ) {
			$count = absint( $stat['count'] );
			$class = $count > 0 ? 'has-items' : 'empty';

			$html .= '<div class="i365-po-db-stat ' . esc_attr( $class ) . '">';
			$html .= '<label class="i365-po-db-stat__label">';
			$html .= '<input type="checkbox" name="db_cleanup_items[]" value="' . esc_attr( $key ) . '" ' . ( $count > 0 ? '' : 'disabled' ) . ' />';
			$html .= '<span class="i365-po-db-stat__name">' . esc_html( $stat['label'] ) . '</span>';
			$html .= '<span class="i365-po-db-stat__count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
			if ( ! empty( $stat['desc'] ) ) {
				$html .= '<span class="i365-po-db-stat__desc">' . esc_html( $stat['desc'] ) . '</span>';
			}
			$html .= '</label>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}
}
