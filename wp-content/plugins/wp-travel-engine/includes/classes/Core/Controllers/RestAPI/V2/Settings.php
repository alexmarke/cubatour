<?php

/**
 * WP Travel Engine Settings API.
 *
 * @package WP Travel Engine
 * @since 6.0.0
 */

namespace WPTravelEngine\Core\Controllers\RestAPI\V2;

use WPTravelEngine\Core\Models\Settings\PluginSettings;

/**
 * Settings API class.
 */
class Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			'wptravelengine/v2',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function get_settings( $request ) {
		$option = new PluginSettings();
		$trip_facts = $option->get( 'trip_facts', array() );
		$new_array = [];

		foreach ( $trip_facts['field_id'] as $trip_fact ) {
			$new_array[] = str_replace(' ', '_', strtolower($trip_fact));
		}

		$fields = array();
		foreach( $new_array as $key => $index){
			$fields[$index] = $key + 1;
		}

		$data = [];
		foreach ($fields as $key => $index) {
			$data[$key] = [
				"fid" => $trip_facts['fid'][$index],
				"field_id" => $trip_facts['field_id'][$index],
				"field_icon" => $trip_facts['field_icon'][$index],
				"field_type" => $trip_facts['field_type'][$index],
				"input_placeholder" => $trip_facts['input_placeholder'][$index]
			];
		}

		return rest_ensure_response( $data );
	}
}
