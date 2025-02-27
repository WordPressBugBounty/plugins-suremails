<?php
/**
 * NetcoreHandler.php
 *
 * Handles sending emails using Netcore.
 *
 * @package SureMails\Inc\Emails\Providers\Netcore
 */

namespace SureMails\Inc\Emails\Providers\Netcore;

use SureMails\Inc\Emails\Handler\ConnectionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NetcoreHandler
 *
 * Implements the ConnectionHandler to handle Netcore email sending and authentication.
 */
class NetcoreHandler implements ConnectionHandler {

	/**
	 * Netcore connection data.
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
	 * Authenticate the Netcore connection.
	 *
	 * Since Netcore does not provide a direct authentication endpoint, this function
	 * simply saves the connection data and returns a success message.
	 *
	 * @return array The result of the authentication attempt.
	 */
	public function authenticate() {
		return [
			'success'    => true,
			'message'    => __( 'Netcore connection saved successfully.', 'suremails' ),
			'error_code' => 200,
		];
	}

	/**
	 * Send email using Netcore.
	 *
	 * @param array $atts           The email attributes.
	 * @param int   $log_id         The log ID.
	 * @param array $connection      The connection details.
	 * @param array $processed_data The processed email data.
	 *
	 * @return array The result of the sending attempt.
	 */
	public function send( array $atts, $log_id, array $connection, $processed_data ) {
		return [
			'success' => false,
			'message' => __( 'Netcore sending not yet implemented.', 'suremails' ),
			'send'    => false,
		];
	}

	/**
	 * Get the options for the Netcore connection.
	 *
	 * @return array The options for the Netcore connection.
	 */
	public static function get_options() {
		return [
			'title'          => __( 'Netcore Connection', 'suremails' ),
			'description'    => __( 'Enter the details below to connect with your Netcore account.', 'suremails' ),
			'fields'         => self::get_specific_fields(),
			'icon'           => 'NetcoreIcon',
			'display_name'   => __( 'Netcore', 'suremails' ),
			'provider_type'  => 'soon',
			'field_sequence' => [ 'connection_title', 'api_key', 'from_email', 'force_from_email', 'from_name', 'force_from_name', 'priority' ],
		];
	}

	/**
	 * Get the specific fields for the Netcore connection.
	 *
	 * @return array The specific fields for the Netcore connection.
	 */
	public static function get_specific_fields() {
		return [
			'api_key' => [
				'required'    => true,
				'datatype'    => 'string',
				'label'       => __( 'API Key', 'suremails' ),
				'input_type'  => 'password',
				'placeholder' => __( 'Enter your Netcore API key', 'suremails' ),
				'encrypt'     => true,
			],
		];
	}
}
