<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Submission_Repository {

	/**
	 * @return string
	 */
	public function get_values_table() {
		global $wpdb;
		return $wpdb->prefix . 'e_submissions_values';
	}

	/**
	 * @return string
	 */
	public function get_submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'e_submissions';
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return array<string, string>
	 */
	public function get_fields( $submission_id ) {
		global $wpdb;

		$submission_id = (int) $submission_id;
		if ( $submission_id <= 0 ) {
			return array();
		}

		$table = $this->get_values_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `key`, `value` FROM {$table} WHERE submission_id = %d",
				$submission_id
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$fields[ (string) $row['key'] ] = (string) $row['value'];
		}

		return $fields;
	}

	/**
	 * @param int    $submission_id Submission ID.
	 * @param string $key Field ID.
	 * @return string
	 */
	public function get_field( $submission_id, $key ) {
		$fields = $this->get_fields( $submission_id );
		return isset( $fields[ $key ] ) ? $fields[ $key ] : '';
	}

	/**
	 * @param int[] $ids Submission IDs.
	 * @return array<int, array{id:int,bank_code:string,contact_mail:string}>
	 */
	public function get_extra_for_ids( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table        = $this->get_values_table();

		$sql = $wpdb->prepare(
			"SELECT submission_id, `key`, `value`
			FROM {$table}
			WHERE submission_id IN ({$placeholders})
			AND `key` IN (%s, %s)",
			array_merge(
				$ids,
				array( Plugin::FIELD_BANK_CODE, Plugin::FIELD_CONTACT_MAIL )
			)
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$data = array();

		foreach ( $ids as $id ) {
			$data[ $id ] = array(
				'id'           => $id,
				'bank_code'    => '',
				'contact_mail' => '',
			);
		}

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$id  = (int) $row['submission_id'];
				$key = (string) $row['key'];

				if ( ! isset( $data[ $id ] ) ) {
					continue;
				}

				if ( Plugin::FIELD_BANK_CODE === $key ) {
					$data[ $id ]['bank_code'] = (string) $row['value'];
				} elseif ( Plugin::FIELD_CONTACT_MAIL === $key ) {
					$data[ $id ]['contact_mail'] = (string) $row['value'];
				}
			}
		}

		return $data;
	}

	/**
	 * @param int $submission_id Submission ID.
	 * @return bool
	 */
	public function exists( $submission_id ) {
		global $wpdb;

		$submission_id = (int) $submission_id;
		if ( $submission_id <= 0 ) {
			return false;
		}

		$table = $this->get_submissions_table();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE id = %d LIMIT 1",
				$submission_id
			)
		);

		return ! empty( $found );
	}
}
