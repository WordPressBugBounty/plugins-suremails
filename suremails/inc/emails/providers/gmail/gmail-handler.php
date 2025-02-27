<?php
/**
 * GmailHandler.php
 *
 * Handles sending emails using Gmail.
 *
 * @package SureMails\Inc\Emails\Providers\Gmail
 */

namespace SureMails\Inc\Emails\Providers\GMAIL;

use SureMails\Inc\Emails\Handler\ConnectionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GmailHandler
 *
 * Implements the ConnectionHandler to handle Gmail email sending and authentication.
 */
class GmailHandler implements ConnectionHandler {

	/**
	 * Gmail connection data.
	 *
	 * @var array
	 */
	protected $connection_data;

	/**
	 * Constructor.
	 *
	 * Initializes connection data.
	 *
	 * @param array $connection_data The connection details.
	 */
	public function __construct( array $connection_data ) {
		$this->connection_data = $connection_data;
	}

	/**
	 * Authenticate the Gmail connection.
	 *
	 * Since Gmail does not provide a direct authentication endpoint, this function
	 * simply saves the connection data and returns a success message.
	 *
	 * @return array The result of the authentication attempt.
	 */
	public function authenticate() {
		return [
			'success'    => true,
			'message'    => __( 'Gmail connection saved successfully.', 'suremails' ),
			'error_code' => 200,
		];
	}

	/**
	 * Send email using Gmail.
	 *
	 * @param array $atts Email attributes.
	 * @param int   $log_id Log ID.
	 * @param array $connection Connection data.
	 * @param array $processed_data Processed email data.
	 *
	 * @return array The result of the sending attempt.
	 */
	public function send( array $atts, $log_id, array $connection, $processed_data ) {
		return [
			'success' => false,
			'message' => __( 'Gmail sending not yet implemented.', 'suremails' ),
			'send'    => false,
		];
	}

	/**
	 * Get the Gmail connection options.
	 *
	 * @return array The Gmail connection options.
	 */
	public static function get_options() {
		return [
			'title'          => __( 'Gmail Connection', 'suremails' ),
			'description'    => __( 'Enter the details below to connect with your Gmail account.', 'suremails' ),
			'fields'         => self::get_specific_fields(),
			'icon'           => 'GmailIcon',
			'display_name'   => __( 'Gmail', 'suremails' ),
			'provider_type'  => 'soon',
			'field_sequence' => [ 'connection_title', 'client_id', 'client_secret', 'refresh_token', 'from_email', 'force_from_email', 'from_name', 'force_from_name', 'priority' ],
		];
	}

	/**
	 * Get the Gmail specific fields.
	 *
	 * @return array The Gmail specific fields.
	 */
	public static function get_specific_fields() {
		return [
			'client_id'     => [
				'required'    => true,
				'datatype'    => 'string',
				'label'       => __( 'Client ID', 'suremails' ),
				'input_type'  => 'text',
				'placeholder' => __( 'Enter your Gmail Client ID', 'suremails' ),
			],
			'client_secret' => [
				'required'    => true,
				'datatype'    => 'string',
				'label'       => __( 'Client Secret', 'suremails' ),
				'input_type'  => 'password',
				'placeholder' => __( 'Enter your Gmail Client Secret', 'suremails' ),
				'encrypt'     => true,
			],
			'refresh_token' => [
				'required'    => true,
				'datatype'    => 'string',
				'label'       => __( 'Refresh Token', 'suremails' ),
				'input_type'  => 'text',
				'placeholder' => __( 'Enter your Gmail Refresh Token', 'suremails' ),
				'encrypt'     => true,
			],
		];
	}
}
