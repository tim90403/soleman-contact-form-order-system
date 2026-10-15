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
	 * @return array<int, array{id:int,fields:array<string,string>,contact_mail:string}>
	 */
	public function get_extra_for_ids( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$display_keys = array_keys( Plugin::get_display_fields() );
		$keys         = array_merge( $display_keys, array( Plugin::FIELD_CONTACT_MAIL ) );

		$id_placeholders  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$key_placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$table            = $this->get_values_table();

		$sql = $wpdb->prepare(
			"SELECT submission_id, `key`, `value`
			FROM {$table}
			WHERE submission_id IN ({$id_placeholders})
			AND `key` IN ({$key_placeholders})",
			array_merge( $ids, $keys )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$data = array();

		foreach ( $ids as $id ) {
			$data[ $id ] = array(
				'id'           => $id,
				'fields'       => array_fill_keys( $display_keys, '' ),
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

				if ( Plugin::FIELD_CONTACT_MAIL === $key ) {
					$data[ $id ]['contact_mail'] = (string) $row['value'];
				} else {
					$data[ $id ]['fields'][ $key ] = (string) $row['value'];
				}
			}
		}

		return $data;
	}

	/**
	 * Labels the form snapshot assigned to each field, so the detail page can find the matching rows.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array<string, string> Field ID => original form label.
	 */
	public function get_form_field_labels( $submission_id ) {
		global $wpdb;

		$repository_class = '\ElementorPro\Modules\Forms\Submissions\Database\Repositories\Form_Snapshot_Repository';
		if ( ! class_exists( $repository_class ) ) {
			return array();
		}

		$table      = $this->get_submissions_table();
		$submission = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_id, element_id FROM {$table} WHERE id = %d",
				(int) $submission_id
			)
		);

		if ( ! $submission ) {
			return array();
		}

		$form = $repository_class::instance()->find( $submission->post_id, $submission->element_id );
		if ( ! $form || empty( $form->fields ) || ! is_array( $form->fields ) ) {
			return array();
		}

		$labels = array();
		foreach ( $form->fields as $field ) {
			if ( ! empty( $field['id'] ) && isset( $field['label'] ) && '' !== $field['label'] ) {
				$labels[ (string) $field['id'] ] = (string) $field['label'];
			}
		}

		return $labels;
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
