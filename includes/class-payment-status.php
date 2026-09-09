<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payment_Status {

	const TABLE_SUFFIX = 'scfos_payment_status';

	public function hooks() {
		// Table creation also runs on activation; keep a soft check for upgrades.
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	public function maybe_upgrade() {
		$version = get_option( 'scfos_db_version', '' );
		if ( SCFOS_VERSION === $version ) {
			return;
		}

		self::create_table();
		update_option( 'scfos_db_version', SCFOS_VERSION );
	}

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function create_table() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			submission_id bigint(20) unsigned NOT NULL,
			is_confirmed tinyint(1) NOT NULL DEFAULT 0,
			confirmed_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (submission_id),
			KEY is_confirmed (is_confirmed)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return bool
	 */
	public function is_confirmed( $submission_id ) {
		global $wpdb;

		$submission_id = (int) $submission_id;
		$row           = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT is_confirmed FROM ' . self::table_name() . ' WHERE submission_id = %d',
				$submission_id
			)
		);

		return $row && (int) $row->is_confirmed === 1;
	}

	/**
	 * @param int[] $ids Submission IDs.
	 * @return array<int, bool>
	 */
	public function get_statuses( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$result = array();

		foreach ( $ids as $id ) {
			$result[ $id ] = false;
		}

		if ( empty( $ids ) ) {
			return $result;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT submission_id, is_confirmed FROM ' . self::table_name() . " WHERE submission_id IN ({$placeholders})",
				$ids
			)
		);

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$result[ (int) $row->submission_id ] = ( (int) $row->is_confirmed === 1 );
			}
		}

		return $result;
	}

	/**
	 * @param int  $submission_id Submission ID.
	 * @param bool $confirmed Confirmed state.
	 * @return bool
	 */
	public function set_confirmed( $submission_id, $confirmed ) {
		global $wpdb;

		$submission_id = (int) $submission_id;
		$confirmed     = (bool) $confirmed;
		$now           = current_time( 'mysql' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT submission_id FROM ' . self::table_name() . ' WHERE submission_id = %d',
				$submission_id
			)
		);

		$data = array(
			'is_confirmed' => $confirmed ? 1 : 0,
			'confirmed_at' => $confirmed ? $now : null,
			'updated_at'   => $now,
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				self::table_name(),
				$data,
				array( 'submission_id' => $submission_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
			return false !== $updated;
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array_merge( array( 'submission_id' => $submission_id ), $data ),
			array( '%d', '%d', '%s', '%s' )
		);

		return false !== $inserted;
	}
}
