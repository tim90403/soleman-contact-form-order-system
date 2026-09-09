<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds form field values while rendering an email template.
 */
class Submission_Context {

	/**
	 * @var array<string, string>
	 */
	private static $fields = array();

	/**
	 * @var int
	 */
	private static $submission_id = 0;

	/**
	 * @param array<string, string> $fields Field ID => value.
	 * @param int                   $submission_id Submission ID.
	 */
	public static function set( array $fields, $submission_id = 0 ) {
		self::$fields         = $fields;
		self::$submission_id  = (int) $submission_id;
	}

	public static function clear() {
		self::$fields        = array();
		self::$submission_id = 0;
	}

	/**
	 * @return bool
	 */
	public static function has() {
		return ! empty( self::$fields ) || self::$submission_id > 0;
	}

	/**
	 * @param string $key Field ID.
	 * @return string
	 */
	public static function get( $key ) {
		$key = (string) $key;

		if ( '' === $key ) {
			return '';
		}

		return isset( self::$fields[ $key ] ) ? (string) self::$fields[ $key ] : '';
	}

	/**
	 * @return array<string, string>
	 */
	public static function all() {
		return self::$fields;
	}

	/**
	 * @return int
	 */
	public static function get_submission_id() {
		return self::$submission_id;
	}
}
