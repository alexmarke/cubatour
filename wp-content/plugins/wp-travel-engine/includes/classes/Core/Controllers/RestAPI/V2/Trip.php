<?php
/**
 * WP Travel Engine Trip Post Controller class.
 *
 * @package WPTravelEngine
 * @since 6.0.0
 */

namespace WPTravelEngine\Core\Controllers\RestAPI\V2;

use WP_Error;
use WP_HTTP_Response;
use WP_Post;
use WP_REST_Posts_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPTravelEngine\Core\Models\Post;
use WPTravelEngine\Core\Models\Settings\Options;
use WPTravelEngine\Helpers\PackageDateParser;
use WPTravelEngine\Utilities\ArrayUtility;

/**
 * REST API: Trip Post Controller class
 *
 * @since 6.0.0
 * @see WP_REST_Posts_Controller
 */
class Trip extends WP_REST_Posts_Controller {

	/**
	 * Trip Schema.
	 *
	 * @access protected
	 * @var array
	 */
	protected $schema;

	/**
	 * Request object.
	 *
	 * @access protected
	 * @var WP_REST_Request
	 */
	protected $request;

	/**
	 * Error object.
	 *
	 * @access protected
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * Post type.
	 *
	 * @access protected
	 * @var string
	 */
	protected $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 *
	 * @since 4.7.0
	 *
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->post_type = $post_type;
		$this->rest_base = 'trips';
		$this->namespace = 'wptravelengine/v2';
	}

	/**
	 * Registers the routes for posts.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/schema",
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_item_schema' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		);

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/(?P<id>[\d]+)/services",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_services' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/(?P<id>[\d]+)/packages",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_packages' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/(?P<id>[\d]+)/dates",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dates' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			"/$this->rest_base/(?P<id>[\d]+)/packages/(?P<package_id>[\d]+)",
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_package' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Retrieves the item's schema for display / public consumption purposes.
	 *
	 * @return array Public item schema data.
	 */
	public function get_public_item_schema() {

		$schema = $this->get_item_schema();

		if ( ! empty( $schema[ 'properties' ] ) ) {
			foreach ( $schema[ 'properties' ] as &$property ) {
				unset( $property[ 'arg_options' ] );
			}
		}

		return $schema;
	}

	/**
	 * Verifies if the parameter is present in both the request and the schema.
	 * If it exists, then performs the validation as specified by the callback.
	 *
	 * @param string $param Request parameter to be validated.
	 * @param callable|null $validation_callback Callback function to validate the request parameter.
	 * @param string $err_msg Error message to be displayed.
	 * @param string $err_code Error code.
	 * @param WP_REST_Response $request Request object.
	 *
	 * @return bool
	 */
	protected function is_valid_request_param( string $param, ?callable $validation_callback = null, string $err_msg = '', string $err_code = 'invalid_param', ?WP_REST_Response $request = null ): bool {

		if ( ! isset( $this->request ) && is_null( $request ) ) {
			return false;
		}

		$request = $request ?? $this->request;
		$schema  = $this->schema ?? $this->get_item_schema();
		$params  = explode( '.', $param );

		foreach ( $params as $_param ) {
			if ( empty( $schema[ 'properties' ][ $_param ] ) || ! isset( $request[ $_param ] ) ) {
				return false;
			}
			$schema  = $schema[ 'properties' ][ $_param ];
			$request = $request[ $_param ];
		}

		if ( empty( $schema ) || is_null( $request ) ) {
			return false;
		}

		if ( $validation_callback && $validation_callback( $request ) ) {
			$this->set_bad_request( $err_code, $err_msg, implode( '_', $params ) );

			return false;
		}

		return true;

	}

	/**
	 * Adds Bad Request '400 status' error to the error object.
	 *
	 * @param string $error_code Error code.
	 * @param string $error_message Error message to be displayed.
	 * @param string $error_param Error parameter.
	 *
	 * @return void
	 */
	public function set_bad_request( string $error_code = '', string $error_message = '', string $error_param = '' ): void {
		if ( ! isset( $this->errors ) ) {
			$this->errors = new WP_Error();
		}
		$this->errors->add(
			$error_code,
			$error_message ?? 'Bad Request.',
			[
				'status'  => 400,
				'param'   => $error_param,
				'details' => strip_tags( $error_message ),
			]
		);
	}

	/**
	 * Deletes a package.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return mixed
	 */
	public function delete_package( WP_REST_Request $request ) {
		try {
			if ( wp_delete_post( $request[ 'package_id' ], true ) ) {
				$trip = new Post\Trip( $request->get_param( 'id' ) );
				if ( (int) $trip->get_meta( 'primary_package' ) === (int) $request[ 'package_id' ] ) {
					$trip->delete_meta( 'primary_package' );
				}
				$default_package = $trip->default_package();
				if( $default_package ){
					$trip->update_meta( '_s_price', $trip->has_sale() ? $trip->get_sale_price() : $trip->get_price() );
				}else{
					$trip->delete_meta( '_s_price' );
				}
				$previous           = $this->prepare_item_for_response( $trip->post, $request );
				$remaining_packages = array_diff( (array) $trip->get_meta( 'packages_ids' ), [ $request[ 'package_id' ] ] );
				$trip->set_meta( 'packages_ids', empty( $remaining_packages ) ? '' : $remaining_packages )->save();

				return new WP_REST_Response( [
						'deleted'  => true,
						'previous' => $previous->get_data(),
					]
				);
			} else {
				throw new \Exception();
			}

		} catch ( \Exception $e ) {
			// error_log( $e->getMessage() );
			return new WP_Error(
				'delete_package_failed',
				sprintf( __( 'Failed to delete the package. %s', 'wp-travel-engine' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}


	/**
	 * Update Item.
	 *
	 * @param $request WP_REST_Request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {

		$trip = new Post\Trip( $request->get_param( 'id' ) );

		$this->request = $request;
		$this->schema  = $this->get_item_schema();
		$trip_settings = ArrayUtility::make( $trip->{'settings'} );

		if ( empty( $trip->get_setting( 'trip_code' ) ) || $this->is_valid_request_param( 'trip_code', fn ( $req ) => $req === '', sprintf( __( '%sTrip Code%s is required.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'trip_code', $request[ 'trip_code' ] ?? $trip->get_trip_code() );
		}

		if ( empty( $trip->get_setting( 'trip_duration' ) ) || $this->is_valid_request_param( 'duration.period', fn ( $req ) => $req <= 0, sprintf( __( '%sTrip Duration%s must be greater than 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$duration = $request[ 'duration' ][ 'period' ] ?? 1;
			$trip_settings->set( 'trip_duration', $request[ 'duration' ][ 'period' ] ?? 1 );

			$duration = ( 'days' === ( $request[ 'duration' ][ 'unit' ] ?? 'days' ) ) ? $duration * 24 : $duration;
			$trip->set_meta( 'wp_travel_engine_setting_trip_duration', $duration );
			$trip->set_meta( '_s_duration', $duration );
		}

		if ( $this->is_valid_request_param( 'duration.unit', fn ( $req ) => ! in_array( $req, [
			'days',
			'hours',
		] ), sprintf( __( '%sTrip Duration Unit%s must be either \'days\' or \'hours\'', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'trip_duration_unit', $request[ 'duration' ][ 'unit' ] );
		}

		if ( $this->is_valid_request_param( 'duration.nights', fn ( $req ) => $req < 0, sprintf( __( '%sTrip Duration Night%s must be greater than or equal to 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'trip_duration_nights', $request[ 'duration' ][ 'nights' ] );
		}

		if ( $this->is_valid_request_param( 'cut_off_time.enable' ) ) {
			$trip_settings->set( 'trip_cutoff_enable', $request[ 'cut_off_time' ][ 'enable' ] );
		}

		if ( $this->is_valid_request_param( 'cut_off_time.period', fn ( $req ) => $req < 0, sprintf( __( '%sCut-off Time Period%s must be greater than or equal to 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'trip_cut_off_time', $request[ 'cut_off_time' ][ 'period' ] );
		}

		if ( $this->is_valid_request_param( 'cut_off_time.unit', fn ( $req ) => $req !== 'days', sprintf( __( '%sCut-off Time Unit%s must be \'days\'.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'trip_cut_off_unit', $request[ 'cut_off_time' ][ 'unit' ] );
		}

		if ( $this->is_valid_request_param( 'age_limit.enable' ) ) {
			$trip_settings->set( 'min_max_age_enable', (string) ( $request[ 'age_limit' ][ 'enable' ] ? 'true' : 'false' ) );
		}

		if ( $this->is_valid_request_param( 'age_limit.min', fn ( $req ) => $req < 0, sprintf( __( '%sMinimum Age Limit%s must be greater than or equal to 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip->set_meta( 'wp_travel_engine_trip_min_age', $request[ 'age_limit' ][ 'min' ] );
		}

		if ( $this->is_valid_request_param( 'age_limit.max', fn ( $req ) => $req <= 0, sprintf( __( '%sMaximum Age Limit%s must be greater than 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip->set_meta( 'wp_travel_engine_trip_max_age', $request[ 'age_limit' ][ 'max' ] );
		}

		if ( $this->is_valid_request_param( 'participants.enable' ) ) {
			$trip_settings->set( 'minmax_pax_enable', (string) ( $request[ 'participants' ][ 'enable' ] ? 'true' : 'false' ) );
		}

		if ( $this->is_valid_request_param( 'participants.min', fn ( $req ) => $req < 0, sprintf( __( '%sMinimum Participants%s must be greater than or equal to 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip->set_meta( '_s_min_pax', $request[ 'participants' ][ 'min' ] );
			$trip_settings->set( 'trip_minimum_pax', $request[ 'participants' ][ 'min' ] );
		}

		if ( $this->is_valid_request_param( 'participants.max', fn ( $req ) => $req <= 0, sprintf( __( '%sMaximum Participants%s must be greater than 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip->set_meta( '_s_max_pax', $request[ 'participants' ][ 'max' ] );
			$trip_settings->set( 'trip_maximum_pax', $request[ 'participants' ][ 'max' ] );
		}

		if ( $this->is_valid_request_param( 'fsd.title' ) ) {
			$availability_title = $request[ 'fsd' ][ 'title' ];
		} else {
			$availability_title = $trip->get_meta( 'WTE_Fixed_Starting_Dates_setting' )[ 'availability_title' ] ?? '';
		}

		if ( $this->is_valid_request_param( 'fsd.hide' ) ) {
			$hide_departure_dates = $request[ 'fsd' ][ 'hide' ];
		} else {
			$hide_departure_dates = wptravelengine_toggled( $trip->get_meta( 'WTE_Fixed_Starting_Dates_setting' )[ 'departure_dates' ][ 'section' ] ?? false );
		}

		$trip->set_meta( 'WTE_Fixed_Starting_Dates_setting', [
			'availability_title' => $availability_title,
			'departure_dates'    => $hide_departure_dates ? [ 'section' => '1' ] : [],
		] );

		if ( $this->is_valid_request_param( 'partial_payment.enable' ) ) {
			$trip_settings->set( 'partial_payment_enable', $request[ 'partial_payment' ][ 'enable' ] );
		}

		if ( $this->is_valid_request_param( 'partial_payment.amount', fn ( $req ) => $req < 0, sprintf( __( '%sPartial Payment Amount%s must be greater than 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'partial_payment_amount', $request[ 'partial_payment' ][ 'amount' ] );
		}

		if ( $this->is_valid_request_param( 'partial_payment.percentage', fn ( $req ) => $req < 0, sprintf( __( '%sPartial Payment Percentage%s must be greater than 0.', 'wp-travel-engine' ), '<strong>', '</strong>' ) ) ) {
			$trip_settings->set( 'partial_payment_percent', $request[ 'partial_payment' ][ 'percentage' ] );
		}

		if ( $this->is_valid_request_param( 'full_payment_enable' ) ) {
			$trip_settings->set( 'trip_full_payment_enabled', $request[ 'full_payment_enable' ] ? "yes" : "no" );
		}

		if ( $this->is_valid_request_param( 'overview_title' ) ) {
			$trip_settings->set( 'overview_section_title', $request[ 'overview_title' ] );
		}

		if ( $this->is_valid_request_param( 'overview' ) ) {
			$trip_settings->set( 'tab_content.1_wpeditor', $request[ 'overview' ] );
		}

		if ( $this->is_valid_request_param( 'highlights_title' ) ) {
			$trip_settings->set( 'trip_highlights_title', $request[ 'highlights_title' ] );
		}

		if ( $this->is_valid_request_param( 'highlights' ) && is_array( $request[ 'highlights' ] ) ) {
			$trip_highlights = [];
			foreach ( $request[ 'highlights' ] as $highlight ) {
				$trip_highlights[][ 'highlight_text' ] = $highlight;
			}
			$trip_settings->set( 'trip_highlights', $trip_highlights );
		}

		if ( $this->is_valid_request_param( 'itinerary_title' ) ) {
			$trip_settings->set( 'trip_itinerary_title', $request[ 'itinerary_title' ] );
		}

		if ( $this->is_valid_request_param( 'itineraries' ) ) {
			$itineraries = $request[ 'itineraries' ];
			if ( empty( $itineraries ) ) {
				$trip_settings->set( 'itinerary', [] );
				$trip->set_meta( 'wte_advanced_itinerary', [] );
				$trip->set_meta( 'trip_itinerary_chart_data', '' );
			} else {
				$itinerary_arr_range = range( 1, count( $itineraries ) );
				$basic_itinerary     = [
					'itinerary_title'      => array_combine( $itinerary_arr_range, array_column( $itineraries, 'title' ) ),
					'itinerary_days_label' => array_combine( $itinerary_arr_range, array_column( $itineraries, 'label' ) ),
					'itinerary_content'    => array_combine( $itinerary_arr_range, array_column( $itineraries, 'content' ) ),
				];
				$trip_settings->set( 'itinerary', $basic_itinerary );

				if ( defined( 'WTEAD_FILE_PATH' ) ) {
					$imgs               = array_column( $itineraries, 'images' );
					$sleep_modes        = array_column( $itineraries, 'sleep_mode' );
					$overnights         = array_column( $itineraries, 'overnights' );
					$advanced_itinerary = [
						'itinerary_duration'               => array_combine( $itinerary_arr_range, array_column( $itineraries, 'period' ) ),
						'itinerary_duration_type'          => array_combine( $itinerary_arr_range, array_column( $itineraries, 'unit' ) ),
						'sleep_modes'                      => array_combine( $itinerary_arr_range, array_column( $sleep_modes, 'field_id' ) ),
						'itinerary_sleep_mode_description' => array_combine( $itinerary_arr_range, array_column( $sleep_modes, 'description' ) ),
						'meals_included'                   => array_filter( array_combine( $itinerary_arr_range, array_column( $itineraries, 'meals_included' ) ) ),
						'itinerary_image_max_count'        => array_combine( $itinerary_arr_range, array_map( 'count', $imgs ) ),
					];

					foreach ( $itinerary_arr_range as $key => $val ) {
						$advanced_itinerary[ 'itinerary_image' ][ $val ] = array_column( $imgs[ $key ] ?? [], 'id' );
						$advanced_itinerary[ 'overnight' ][ $val ]       = [
							'at'       => $overnights[ $key ][ 0 ][ 'location' ] ?? '',
							'altitude' => $overnights[ $key ][ 0 ][ 'altitude' ] ?? 0,
						];
						if ( ! empty( $advanced_itinerary[ 'overnight' ][ $val ][ 'at' ] ?? '' ) ) {
							$chart_data[ $val ] = $advanced_itinerary[ 'overnight' ][ $val ];
						}
					}

					$trip->set_meta( 'wte_advanced_itinerary', [ 'advanced_itinerary' => $advanced_itinerary ] );

					if ( isset( $chart_data ) ) {
						$trip->set_meta( 'trip_itinerary_chart_data', wp_unslash( wp_json_encode( $chart_data ) ) );
					}

					unset( $itineraries, $itinerary_arr_range, $basic_itinerary, $advanced_itinerary, $imgs, $sleep_modes, $overnights, $chart_data );
				}
			}
		}

		if ( $this->is_valid_request_param( 'cost_title' ) ) {
			$trip_settings->set( 'cost_tab_sec_title', $request[ 'cost_title' ] );
		}

		if ( $this->is_valid_request_param( 'cost_includes_title' ) ) {
			$trip_settings->set( 'cost.includes_title', $request[ 'cost_includes_title' ] );
		}

		if ( $this->is_valid_request_param( 'cost_includes' ) ) {
			$trip_settings->set( 'cost.cost_includes', implode( "\n", $request[ 'cost_includes' ] ) );
		}

		if ( $this->is_valid_request_param( 'cost_excludes_title' ) ) {
			$trip_settings->set( 'cost.excludes_title', $request[ 'cost_excludes_title' ] );
		}

		if ( $this->is_valid_request_param( 'cost_excludes' ) ) {
			$trip_settings->set( 'cost.cost_excludes', implode( "\n", $request[ 'cost_excludes' ] ) );
		}

		if ( $this->is_valid_request_param( 'trip_info_title' ) ) {
			$trip_settings->set( 'trip_facts_title', $request[ 'trip_info_title' ] );
		}

		if ( $this->is_valid_request_param( 'trip_info' ) ) {
			$trip_facts = [];
			foreach ( $request[ 'trip_info' ] as $info ) {
				if ( isset( $trip_facts[ $info[ 'id' ] ] ) ) {
					continue;
				}
				$trip_facts[ 'field_id' ][ $info[ 'id' ] ]   = $info[ 'label' ];
				$trip_facts[ 'field_type' ][ $info[ 'id' ] ] = $info[ 'type' ];
				$trip_facts[ $info[ 'id' ] ]                 = [ $info[ 'id' ] => $info[ 'content' ] ];
			}
			$trip_settings->set( 'trip_facts', $trip_facts );
		}

		if ( $this->is_valid_request_param( 'gallery' ) || $this->is_valid_request_param( 'gallery_enable' ) ) {
			$gallery        = $trip->get_meta( 'wpte_gallery_id' );
			$gallery        = empty( $gallery ) ? [] : $gallery;
			$enable_gallery = $gallery ? array_shift( $gallery ) : false;
			$trip->set_meta( 'wpte_gallery_id', array_merge(
				[ 'enable' => isset( $request[ 'gallery_enable' ] ) ? ( $request[ 'gallery_enable' ] ? '1' : null ) : $enable_gallery ],
				isset( $request[ 'gallery' ] ) ? array_unique( array_column( $request[ 'gallery' ], 'id' ) ) : $gallery
			) );
		}

		if ( $this->is_valid_request_param( 'video_gallery_enable' ) ) {
			$trip_settings->set( 'enable_video_gallery', $request[ 'video_gallery_enable' ] );
		}

		if ( $this->is_valid_request_param( 'video_gallery' ) ) {
			$video_gallery = [];
			foreach ( $request[ 'video_gallery' ] as $video ) {
				$pattern = '/(http:\/\/|https:\/\/|)(player.|www.)?(vimeo\.com|youtu(be\.com|\.be|be\.googleapis\.com))\/(video\/|embed\/|watch\?v=|v\/)?([A-Za-z0-9._%-]*)(\&\S+)?/';
				preg_match( $pattern, $video[ 'url' ] ?? '', $matches );
				$video_gallery[] = [
					'id'    => $matches[ 6 ] ?? '',
					'type'  => ( strpos( $matches[ 3 ], "youtu" ) !== false ) ? "youtube" : ( ( strpos( $matches[ 3 ], "vimeo" ) !== false ) ? "vimeo" : null ),
					'thumb' => $video[ 'thumbnail' ] ?? '',
				];
			}
			$trip->set_meta( 'wpte_vid_gallery', $video_gallery );
		}

		if ( $this->is_valid_request_param( 'map_title' ) ) {
			$trip_settings->set( 'map_section_title', $request[ 'map_title' ] );
		}

		if ( $this->is_valid_request_param( 'trip_map.images' ) || $this->is_valid_request_param( 'trip_map.iframe' ) ) {
			$map        = $trip->get_nested_setting( 'map', '' );
			$map_url    = isset( $request[ 'trip_map' ][ 'images' ] ) ? array_column( $request[ 'trip_map' ][ 'images' ], 'id' ) : (array) ( $map[ 'image_url' ] ?? [ '' ] );
			$map_iframe = $request[ 'trip_map' ][ 'iframe' ] ?? $map[ 'iframe' ] ?? '';

			$trip_settings->set( 'map', [
				'image_url' => $map_url[ 0 ] ?? '',
				'iframe'    => $map_iframe,
			] );
		}

		if ( $this->is_valid_request_param( 'faqs_title' ) ) {
			$trip_settings->set( 'faq_section_title', $request[ 'faqs_title' ] );
		}

		if ( $this->is_valid_request_param( 'faqs' ) ) {
			$trip_settings->set( 'faq', array(
				'faq_title'   => array_column( $request[ 'faqs' ], 'question' ),
				'faq_content' => array_column( $request[ 'faqs' ], 'answer' ),
			) );
		}

		if ( $this->is_valid_request_param( 'file_downloads' ) ) {
			$file_downloads = [];
			foreach ( $request[ 'file_downloads' ] ?? [] as $key => $value ) {
				if ( in_array( $value[ 'id' ] ?? '', array_column( $file_downloads, 'id' ) ) ) {
					continue;
				}
				unset( $value[ 'type' ] );
				$file_downloads[ ++ $key ] = $value;
			}
			$trip->set_meta( 'wte_file_downloads', array(
				'file_downloads' => array(
					'file_downloads_meta_max_count' => count( $request[ 'file_downloads' ] ),
					'wte_files_downloadable'        => $file_downloads,
				),
			) );
		}

		if ( $this->is_valid_request_param( 'trip_extra_services' ) ) {
			$trip_settings->set( 'wte_services_ids', implode( ',', array_column( $request[ 'trip_extra_services' ], 'id' ) ) );
		}


		if ( $this->is_valid_request_param( 'packages' ) ) {

			$trip_package_ids = array_column( $request[ 'packages' ], 'id' );

			foreach ( $request[ 'packages' ] ?? [] as $key => $package ) {

				if ( ! ( $package[ '_changed' ] ?? false ) && ! is_null( $package[ 'id' ] ?? null ) ) {
					continue;
				}

				$package[ 'name' ] = empty( $package[ 'name' ] ?? '' ) ? get_num_suffix( ++ $key ) : $package[ 'name' ];

				if ( is_null( $package[ 'id' ] ?? null ) ) {
					$package[ 'id' ] = wp_insert_post( [
						'post_title'   => $package[ 'name' ],
						'post_content' => $package[ 'description' ] ?? '',
						'post_type'    => 'trip-packages',
						'post_status'  => 'publish',
						'post_author'  => get_current_user_id(),
					] );
				}

				$group_pricing = null;
				foreach ( array_column( $package[ 'traveler_categories' ], 'group_pricing', 'id' ) ?? [] as $id => $group_princings ) {
					$group_pricing[ $id ] = array_map( fn ( $gp ) => [
						'from'  => $gp[ 'from' ],
						'to'    => $gp[ 'to' ],
						'price' => $gp[ 'rate' ],
					], $group_princings );
				}

				$weekdays_map = array_combine( range( 1, 7 ), [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ] );
				$stored_dates = get_post_meta( $package[ 'id' ], 'package-dates' );
				foreach ( $package[ 'dates' ] ?? [] as $dates ) {
					$package_dates [ str_replace( '-', '', $dates[ 'start_date' ] ) ] = array_filter( [
						'dtstart'            => $dates[ 'start_date' ],
						'times'              => $dates[ 'times' ],
						'is_recurring'       => $dates[ 'enable_repeat' ] ? '1' : null,
						'rrule'              => array_filter( [
							'r_frequency' => $dates[ 'repeat' ][ 'frequency' ],
							'r_weekdays'  => $dates[ 'repeat' ][ 'frequency' ] === 'WEEKLY'
								? array_intersect( $weekdays_map, $dates[ 'repeat' ][ 'weekdays' ] )
								: ( $stored_dates[ 'rrule' ][ 'r_weekdays' ] ?? null ),
							'r_months'    => $dates[ 'repeat' ][ 'frequency' ] === 'MONTHLY'
								? array_combine( $dates[ 'repeat' ][ 'months' ], $dates[ 'repeat' ][ 'months' ] )
								: ( $stored_dates[ 'rrule' ][ 'r_months' ] ?? null ),
							'r_until'     => empty( $dates[ 'repeat' ][ 'until' ] ) ? date( 'Y-m-d', strtotime( '+1 year' ) ) : $dates[ 'repeat' ][ 'until' ],
							'r_count'     => empty( $dates[ 'repeat' ][ 'limit' ] ) ? 10 : $dates[ 'repeat' ][ 'limit' ],
						] ),
						'seats'              => $dates[ 'total_seats' ] ?? 0,
						'availability_label' => $dates[ 'availability_label' ],
					], fn ( $v ) => $v !== null );
				}

				$package_ids   = array_column( $package[ 'traveler_categories' ], 'id', 'id' );
				$meta_inputs[] = [
					'package_id'               => $package[ 'id' ],
					'package_name'             => $package[ 'name' ],
					'package_description'      => $package[ 'description' ] ?? '',
					'trip_ID'                  => $trip->ID,
					'enable_weekly_time_slots' => isset( $package[ 'time_slots_enable' ] ) ? ( $package[ 'time_slots_enable' ] ? 'yes' : 'no' ) : 'no',
					'weekly_time_slots'        => empty( $package[ 'time_slots' ] ) ? null : array_filter( array_combine( range( 1, 7 ), $package[ 'time_slots' ] ) ),
					'enable_week_days'         => $package[ 'enable_week_days' ] ?? array_combine( array_values( $weekdays_map ), array_fill( 0, 7, false ) ),
					'package-categories'       => array_filter( [
						'c_ids'                  => $package_ids,
						'labels'                 => array_column( $package[ 'traveler_categories' ], 'label', 'id' ),
						'prices'                 => array_column( $package[ 'traveler_categories' ], 'price', 'id' ),
						'pricing_types'          => array_combine( $package_ids, array_column( array_column( $package[ 'traveler_categories' ], 'pricing_type' ), 'value' ) ),
						'enabled_sale'           => array_filter( array_combine( $package_ids, array_map( fn ( $val ) => $val ? '1' : null, array_column( $package[ 'traveler_categories' ], 'has_sale' ) ) ) ),
						'sale_prices'            => array_column( $package[ 'traveler_categories' ], 'sale_price', 'id' ),
						'min_paxes'              => array_column( $package[ 'traveler_categories' ], 'min_pax', 'id' ),
						'max_paxes'              => array_column( $package[ 'traveler_categories' ], 'max_pax', 'id' ),
						'enabled_group_discount' => array_filter( array_combine( $package_ids, array_map( fn ( $val ) => $val ? '1' : null, array_column( $package[ 'traveler_categories' ], 'has_group_pricing' ) ) ) ),
					], fn ( $v ) => $v !== null && ! empty( $v ) ),
					'group-pricing'            => $group_pricing,
					'package-dates'            => $package_dates ?? [],
				];

				$last_meta_input   = end( $meta_inputs );
				$common_plain_text = sprintf( __( 'must be greater than or equal to 0 in \'%s Package\'.', 'wp-travel-engine' ), $package[ 'name' ] );

				$regularVsSalePrices = ! ! array_filter( $last_meta_input[ 'package-categories' ][ 'prices' ] ?? [], fn ( $val, $key ) => '' !== $val ? ( $val > 0 && ( $val <= $last_meta_input[ 'package-categories' ][ 'sale_prices' ][ $key ] ?? 0 ) ) : false, ARRAY_FILTER_USE_BOTH );
				if ( $regularVsSalePrices ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sSale price%s must be less than %sRegular price%s in \'%s Package\'.', 'wp-travel-engine' ), '<strong>', '</strong>', '<strong>', '</strong>', $package[ 'name' ] ), "{$package['name']}_sale_price" );
				}

				$areCountsValid = ! ! array_filter( array_column( $last_meta_input[ 'package-dates' ][ 'rrule' ] ?? [], 'r_count' ), fn ( $val ) => $val < 0 );
				if ( $areCountsValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sRepeat Limit%s %s', 'wp-travel-engine' ), '<strong>', '</strong>', $common_plain_text ), "{$package['name']}_repeat_limit" );
				}

				$areSeatsValid = ! ! array_filter( array_column( $last_meta_input[ 'package-dates' ] ?? [], 'seats' ), fn ( $val ) => ( $val < 0 && '' !== $val ) );
				if ( $areSeatsValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sTotal Seats%s %s', 'wp-travel-engine' ), '<strong>', '</strong>', $common_plain_text ), "{$package['name']}_total_seats" );
				}

				$arePricesValid = ! ! array_filter( $last_meta_input[ 'package-categories' ][ 'prices' ] ?? [], fn ( $val ) => '' !== $val ? $val < 0 : false );
				if ( $arePricesValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sPrice%s %s', 'wp-travel-engine' ), '<strong>', '</strong>', $common_plain_text ), "{$package['name']}_price" );
				}

				$areSalePricesValid = ! ! array_filter( $last_meta_input[ 'package-categories' ][ 'sale_prices' ] ?? [], fn ( $val ) => '' !== $val ? $val < 0 : false );
				if ( $areSalePricesValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sSale Price%s %s', 'wp-travel-engine' ), '<strong>', '</strong>', $common_plain_text ), "{$package['name']}_sale_price" );
				}

				$areMinPaxesValid = ! ! array_filter( $last_meta_input[ 'package-categories' ][ 'min_paxes' ] ?? [], fn ( $val ) => $val < 0 );
				if ( $areMinPaxesValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sMinimum Pax%s %s', 'wp-travel-engine' ), '<strong>', '</strong>', $common_plain_text ), "{$package['name']}_min_pax" );
				}

				$areMaxPaxesValid = ! ! array_filter( $last_meta_input[ 'package-categories' ][ 'max_paxes' ] ?? [], fn ( $val ) => ( $val <= 0 && '' !== $val ) );
				if ( $areMaxPaxesValid ) {
					$this->set_bad_request( 'invalid_param', sprintf( __( '%sMaximum Pax%s %s must be greater than 0 in \'%s Package\'.', 'wp-travel-engine' ), '<strong>', '</strong>', $package[ 'name' ] ), "{$package['name']}_max_pax" );
				}

				$trip_package_ids[ $key ] = $package[ 'id' ];

				unset( $group_pricing, $package_dates, $package_ids, $last_meta_input, $common_plain_text, $regularVsSalePrices, $areCountsValid, $areSeatsValid, $arePricesValid, $areSalePricesValid, $areMinPaxesValid, $areMaxPaxesValid );

			}

			$primaryPackageId = array_search( true, array_combine( array_filter( $trip_package_ids ), array_column( $request[ 'packages' ], 'is_primary' ) ) );
			$primaryPackageId = $primaryPackageId !== false ? $primaryPackageId : ( $trip_package_ids[ 0 ] ?? '' );

			if ( ! empty( $primaryPackageId ) ) {
				$trip->set_meta( 'primary_package', $primaryPackageId );
			}

			if ( ! isset( $this->errors ) ) {
				$trip->set_meta( 'packages_ids', $trip_package_ids );
				foreach ( $meta_inputs ?? [] as $meta_input ) {
					$available_months = array_map( function( $string ) {
						return substr( $string,  2, 4 );
					}, array_keys( $meta_input[ 'package-dates' ] ) );
					wp_update_post( [
						'ID'           => array_shift( $meta_input ),
						'post_title'   => array_shift( $meta_input ),
						'post_content' => array_shift( $meta_input ),
						'meta_input'   => array_filter( $meta_input, fn ( $v ) => $v !== null ),
					] );
				}

				if ( isset( $available_months ) ) {
					$trip->set_meta( 'trip_available_months', implode( ',', array_unique( $available_months ) ) );
				}

				$trip->save();
				$updated_trip = new Post\Trip( $trip->ID );
				$price = $updated_trip->has_sale() ? $updated_trip->get_sale_price() : $updated_trip->get_price();
				$trip->set_meta( 'wp_travel_engine_setting_trip_actual_price', $updated_trip->get_price() );
				$trip->set_meta( 'wp_travel_engine_setting_trip_price', $price );
				$trip->set_meta( '_s_price', $price );
				$trip->set_meta( '_s_has_sale', $updated_trip->has_sale() ? 'yes' : 'no' );
			}

		}

		if ( $this->is_valid_request_param( 'custom_tabs' ) ) {
			$tab_content = $trip_settings->get( 'tab_content', [] );
			$settings    = Options::get( 'wp_travel_engine_settings', [] );
			foreach ( $settings[ 'trip_tabs' ][ 'id' ] ?? [] as $key => $i ) {
				$i = (int) $i;
				if ( isset( $request[ 'custom_tabs' ][ 'tab_' . $i ][ 'title' ] ) ) {
					$trip_settings->set( 'tab_' . $i . '_title', $request[ 'custom_tabs' ][ 'tab_' . $i ][ 'title' ] );
				}
				if ( isset( $request[ 'custom_tabs' ][ 'tab_' . $i ][ 'content' ] ) ) {
					$tab_content[ $i . '_wpeditor' ] = $request[ 'custom_tabs' ][ 'tab_' . $i ][ 'content' ];
				}
			}
			$trip_settings->set( 'tab_content', $tab_content );
		}


		if ( isset( $this->errors ) ) {
			return $this->errors;
		}

		$trip->set_meta( 'wp_travel_engine_setting', $trip_settings->value() );

		$trip->save();

		return $this->prepare_item_for_response( $trip->post, $request );
	}

	/**
	 * @return void
	 */
	protected function set_trip_images( $trip, $images ) {

		if ( is_array( $images ) ) {
			$gallery = $trip->get_meta( 'wpte_gallery_id' ) ?? array(
				'enable' => 'yes',
			);

			foreach ( $images as $image ) {
				$attachment_id = isset( $image[ 'id' ] ) ? absint( $image[ 'id' ] ) : 0;

				if ( 0 === $attachment_id && isset( $image[ 'src' ] ) ) {
					$attachment_id = $this->upload_image( $image[ 'src' ] );
				}

				if ( wp_attachment_is_image( $attachment_id ) ) {
					$gallery[] = $attachment_id;
				}
			}

			$trip->set_meta( 'wpte_gallery_id', $gallery );
		}

		return $trip;
	}

	/**
	 * Get single trip data.
	 *
	 * @param WP_Post $item Post object as ID is valid.
	 * @param WP_REST_Request $request Full details about the request.
	 * TODO: Create methods in trip modal to get these values, like $trip->get_code, $trip->get_duration, etc.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {

		remove_filter( "rest_prepare_{$this->post_type}", array(
			\WPTravelEngine\Core\REST_API::class,
			'filter_rest_data_trip',
		), 10 );

		$data = parent::prepare_item_for_response( $item, $request )->get_data();

		$trip = new Post\Trip( $item->ID );

		$fsd = (array) $trip->get_meta( 'WTE_Fixed_Starting_Dates_setting' ) ?? [];

		$data[ 'fsd' ] = [
			'title' => (string) ( $fsd[ 'availability_title' ] ?? '' ),
			'hide'  => wptravelengine_toggled( $fsd[ 'departure_dates' ][ 'section' ] ?? false ),
		];

		$data[ 'trip_code' ] = (string) $trip->get_trip_code();

		$data[ 'overview_title' ] = (string) $trip->get_setting( 'overview_section_title', '' );
		$data[ 'overview' ]       = (string) $trip->get_setting( 'tab_content.1_wpeditor', '' );

		$data[ 'cost_title' ] = (string) $trip->get_setting( 'cost_tab_sec_title', '' );

		$data[ 'cost_includes_title' ] = (string) $trip->get_setting( 'cost.includes_title', '' );
		$data[ 'cost_includes' ]       = array_filter( explode( "\n", $trip->get_setting( 'cost.cost_includes', '' ) ), fn ( $val ) => ! empty( $val ) );

		$data[ 'cost_excludes_title' ] = (string) $trip->get_setting( 'cost.excludes_title', '' );
		$data[ 'cost_excludes' ]       = array_filter( explode( "\n", $trip->get_setting( 'cost.cost_excludes', '' ) ), fn ( $val ) => ! empty( $val ) );

		$data[ 'full_payment_enable' ] = wptravelengine_toggled( $trip->get_setting( 'trip_full_payment_enabled', false ) );

		$data[ 'partial_payment' ] = [
			'enable'     => wptravelengine_toggled( $trip->get_setting( 'partial_payment_enable', false ) ),
			'amount'     => (float) ( $trip->get_setting( 'partial_payment_amount', 0 ) ),
			'percentage' => (float) ( $trip->get_setting( 'partial_payment_percent', 0 ) ),
		];

		$data[ 'duration' ] = array(
			'period' => (int) $trip->get_setting( 'trip_duration', 1 ),
			'unit'   => (string) $trip->get_setting( 'trip_duration_unit', 'days' ),
			'nights' => (int) ( 'hours' === $trip->get_setting( 'trip_duration_unit', 'days' ) ? 0 : $trip->get_setting( 'trip_duration_nights', 0 ) ),
		);

		$data[ 'age_limit' ] = [
			'enable' => wptravelengine_toggled( $trip->get_setting( 'min_max_age_enable', false ) ),
			'min'    => (int) $trip->get_meta( 'wp_travel_engine_trip_min_age' ) ?? 0,
			'max'    => (int) $trip->get_meta( 'wp_travel_engine_trip_max_age' ) ?? 0,
		];

		$data[ 'cut_off_time' ] = [
			'enable' => wptravelengine_toggled( $trip->get_setting( 'trip_cutoff_enable', false ) ),
			'period' => (int) $trip->get_setting( 'trip_cut_off_time', 0 ),
			'unit'   => (string) $trip->get_setting( 'trip_cut_off_unit', 'days' ),
		];

		$data[ 'participants' ] = [
			'enable' => wptravelengine_toggled( $trip->get_setting( 'minmax_pax_enable', false ) ),
			'min'    => (int) $trip->get_setting( 'trip_minimum_pax', 0 ),
			'max'    => (int) $trip->get_setting( 'trip_maximum_pax', 0 ),
		];

		$data[ 'map_title' ] = (string) $trip->get_setting( 'map_section_title', '' );
		$data[ 'trip_map' ]  = [
			'images' => array_map(
				function ( $id ) {

					foreach ( [ 'thumbnail', 'medium', 'full' ] as $size ) {
						$image = wp_get_attachment_image_src( $id, $size );
						if ( $image ) {
							list( $url, $width, $height ) = $image;
							$orientation = ( $width >= $height ) ? 'landscape' : 'portrait';
							$$size       = compact( 'url', 'width', 'height', 'orientation' );
						} else {
							$$size = null;
						}
					}
					$alt  = get_post_meta( $id, '_wp_attachment_image_alt', true );
					$type = explode( '/', get_post_mime_type( $id ) )[ 0 ];

					return $image ? compact( 'id', 'alt', 'type', 'thumbnail', 'medium', 'full' ) : '';
				},
				(array) $trip->get_setting( 'map.image_url', array() )
			),
			'iframe' => (string) $trip->get_setting( 'map.iframe', '' ),
		];

		$data[ 'highlights_title' ] = (string) $trip->get_setting( 'trip_highlights_title', '' );
		$highlights                 = (array) ( $trip->get_setting( 'trip_highlights', array() ) );
		$data[ 'highlights' ]       = array();
		foreach ( $highlights as $highlight ) {
			$data[ 'highlights' ][] = (string) $highlight[ 'highlight_text' ];
		}

		$data[ 'faqs' ]       = array();
		$data[ 'faqs_title' ] = (string) $trip->get_setting( 'faq_section_title', '' );
		$faqs                 = (array) $trip->get_setting( 'faq.faq_title', array() );
		foreach ( $faqs as $key => $faq ) {
			$answer = (string) $trip->get_setting( 'faq.faq_content.' . $key, '' );

			$data[ 'faqs' ][] = [
				'question' => (string) $faq,
				'answer'   => (string) $answer,
			];
		}

		$data[ 'gallery_enable' ] = wptravelengine_toggled( $trip->search_in_meta( 'wpte_gallery_id.enable', false ) );
		$data[ 'gallery' ]        = array();
		$i                        = 0;
		$gallery                  = empty( $trip->get_meta( 'wpte_gallery_id' ) ) ? [] : $trip->get_meta( 'wpte_gallery_id' );
		foreach ( (array) $gallery as $index => $image_id ) {
			if ( 'enable' === $index ) {
				continue;
			}
			foreach ( [ 'thumbnail', 'medium', 'full' ] as $size ) {
				$image = wp_get_attachment_image_src( $image_id, $size );
				if ( $image ) {
					list( $url, $width, $height ) = $image;
					$orientation = ( $width >= $height ) ? 'landscape' : 'portrait';
					$$size       = compact( 'url', 'width', 'height', 'orientation' );
				} else {
					$$size = null;
				}
			}
			$id                         = (int) $image_id;
			$alt                        = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			$type                       = explode( '/', get_post_mime_type( $image_id ) )[ 0 ];
			$data[ 'gallery' ][ $i ++ ] = compact( 'id', 'alt', 'type', 'thumbnail', 'medium', 'full' );
		}

		$data[ 'video_gallery_enable' ] = wptravelengine_toggled( $trip->get_setting( 'enable_video_gallery', false ) );
		$data[ 'video_gallery' ]        = array();
		$video_gallery                  = empty( $trip->get_meta( 'wpte_vid_gallery' ) ) ? [] : $trip->get_meta( 'wpte_vid_gallery' );
		foreach ( (array) $video_gallery as $key => $value ) {
			$id        = (string) ( $value[ 'id' ] ?? '' );
			$type      = (string) ( $value[ 'type' ] ?? '' );
			$thumbnail = (string) ( $value[ 'thumb' ] ?? '' );
			$url       = ( 'youtube' === $type ) ? '//www.youtube.com/watch?v=' . $id : '//vimeo.com/' . $id;

			$data[ 'video_gallery' ][] = compact( 'url', 'thumbnail' );
		}

		$itinerary_title           = (array) $trip->get_setting( 'itinerary.itinerary_title', array() );
		$data[ 'itinerary_title' ] = (string) $trip->get_setting( 'trip_itinerary_title', '' );
		$data[ 'itineraries' ]     = array();
		foreach ( $itinerary_title as $key => $itinerary ) {
			$label   = (string) ( $trip->get_setting( 'itinerary.itinerary_days_label.' . $key, '' ) );
			$content = (string) ( $trip->get_setting( 'itinerary.itinerary_content.' . $key, '' ) );

			if ( empty( $itinerary ) && empty( $label ) && empty( $content ) ) {
				continue;
			}

			$data[ 'itineraries' ][] = [
				'title'          => (string) $itinerary,
				'label'          => (string) $label,
				'content'        => (string) $content,
				'period'         => 0,
				'unit'           => 'hour',
				'sleep_mode'     => [
					'field_id'    => '',
					'description' => '',
				],
				'meals_included' => [],
				'images'         => [],
				'overnights'     => [
					[ 'location' => '', 'altitude' => 0 ],
				],
			];
		}
		// Advance Itinerary
		if ( defined( 'WTEAD_FILE_PATH' ) ) {
			$advanced_itinerary = (array) $trip->search_in_meta( 'wte_advanced_itinerary.advanced_itinerary', array() );
			$all_overnights     = array();
			foreach ( $advanced_itinerary[ 'overnight' ] ?? [] as $key => $overnight ) {
				$all_overnights[ $key ] = [
					'location' => (string) ( $overnight[ 'at' ] ?? '' ),
					'altitude' => (float) ( $overnight[ 'altitude' ] ?? '' ),
				];
			}
			$img = $trip->search_in_meta( 'wte_advanced_itinerary.advanced_itinerary.itinerary_image', array() );
			foreach ( $advanced_itinerary[ 'itinerary_duration' ] ?? [] as $key => $value ) {
				if ( ! isset( $data[ 'itineraries' ][ $key - 1 ] ) ) {
					continue;
				}
				$temp_img = empty( $img[ $key ] ) ? [] : array_unique( $img[ $key ] );
				$images   = array();
				$i        = 0;
				foreach ( $temp_img as $id ) {
					$id = (int) $id;
					foreach ( [ 'thumbnail', 'medium', 'full' ] as $size ) {
						$attach = wp_get_attachment_image_src( $id, $size );
						if ( $attach ) {
							list( $url, $width, $height ) = $attach;
							$orientation = ( $width >= $height ) ? 'landscape' : 'portrait';
							$$size       = compact( 'url', 'width', 'height', 'orientation' );
						} else {
							$$size = null;
						}
					}
					$alt             = get_post_meta( $id, '_wp_attachment_image_alt', true );
					$type            = explode( '/', get_post_mime_type( $id ) )[ 0 ];
					$images[ $i ++ ] = compact( 'id', 'alt', 'type', 'thumbnail', 'medium', 'full' );
				}
				$period         = (float) ( $advanced_itinerary[ 'itinerary_duration' ][ $key ] ?? 0 );
				$meals_included = (array) ( $advanced_itinerary[ 'meals_included' ][ $key ] ?? [] );
				$unit           = (string) ( $advanced_itinerary[ 'itinerary_duration_type' ][ $key ] ?? 'hour' );
				$sleep_mode     = array(
					'field_id'    => (string) ( $advanced_itinerary[ 'sleep_modes' ][ $key ] ?? '' ),
					'description' => (string) ( $advanced_itinerary[ 'itinerary_sleep_mode_description' ][ $key ] ?? '' ),
				);

				$overnights = [ (array) $all_overnights[ $key ] ?? [] ];

				$data[ 'itineraries' ][ $key - 1 ] = array_merge( $data[ 'itineraries' ][ $key - 1 ] ?? array(), compact( 'period', 'unit', 'sleep_mode', 'meals_included', 'images', 'overnights' ) );
			}
		}

		$trip_facts                = $trip->get_setting( 'trip_facts', array() );
		$data[ 'trip_info_title' ] = (string) $trip->get_setting( 'trip_facts_title', '' );
		$data[ 'trip_info' ]       = array();
		$global_trip_facts         = wptravelengine_get_trip_facts_options();
		foreach ( $trip_facts[ 'field_id' ] ?? [] as $key => $field_name ) {
			if ( isset( $trip_facts[ $key ][ $key ] ) && ( $global_trip_facts[ 'field_id' ][ $key ] ?? '' ) === $field_name ) {
				$data[ 'trip_info' ][] = [
					'id'      => (int) $key,
					'label'   => (string) $field_name,
					'content' => (string) $trip->get_setting( 'trip_facts.' . $key . '.' . $key, '' ),
					'type'    => (string) $trip->get_setting( 'trip_facts.field_type.' . $key, '' ),
					'options' => (array) ( isset( $global_trip_facts[ 'select_options' ] ) ? explode( ',', $global_trip_facts[ 'select_options' ][ $key ] ) : [] ),
				];
			}
		}

		$data[ 'file_downloads' ] = array();
		if ( defined( 'WTEFD_FILE_PATH' ) ) {
			$downloadable = (array) $trip->search_in_meta( 'wte_file_downloads.file_downloads.wte_files_downloadable', array() );
			foreach ( $downloadable as $file_info ) {
				$data[ 'file_downloads' ][] = [
					'id'    => (int) ( $file_info[ 'id' ] ?? 0 ),
					'type'  => (string) get_post_mime_type( $file_info[ 'id' ] ?? '' ),
					'title' => (string) ( $file_info[ 'title' ] ?? '' ),
					'url'   => (string) ( $file_info[ 'url' ] ?? '' ),
				];
			}
		}

		$data[ 'trip_extra_services' ] = [];
		if ( class_exists( 'Extra_Services_Wp_Travel_Engine' ) ) {
			$extra_services = $trip->get_setting( 'wte_services_ids' );
			if ( ! is_null( $extra_services ) && ! empty( $extra_services ) ) {
				$services = get_posts(
					array(
						'post_type'      => 'wte-services',
						'post_status'    => 'publish',
						'post__in'       => empty( $extra_services ) ? '' : explode( ',', $extra_services ),
						'posts_per_page' => - 1,
						'orderby'        => 'post__in',
					)
				);
				foreach ( $services ?? [] as $service ) {
					$service_data                    = get_post_meta( $service->ID, 'wte_services', true );
					$service_data[ 'options' ]       = array_filter( $service_data[ 'options' ] ?? [], fn ( $val ) => ! empty( $val ) );
					$data[ 'trip_extra_services' ][] = [
						'id'      => (int) $service->ID,
						'label'   => (string) $service->post_title,
						'type'    => (string) $service_data[ 'service_type' ],
						'options' => (array) $service_data[ 'options' ],
					];
				}
			}
		}

		$trip_packages      = new Post\TripPackages( $trip );
		$primary_package    = (int) $trip->get_meta( 'primary_package' );
		$default_package    = $trip->default_package();
		$default_package_id = is_null( $default_package ) ? $primary_package : $default_package->ID;
		$data[ 'packages' ] = [];
		foreach ( $trip_packages as $key => $trip_package ) {
			/* @var Post\TripPackage $trip_package */
			$data[ 'packages' ][ $key ]                 = $this->prepare_package( $trip_package );
			$data[ 'packages' ][ $key ][ 'is_primary' ] = $default_package_id === $trip_package->ID;
		}
		$settings = Options::get( 'wp_travel_engine_settings', [] );
		foreach ( $settings[ 'trip_tabs' ][ 'id' ] ?? [] as $key => $i ) {
			$i = (int) $i;
			if ( $i < 7 || ( ( $settings[ 'trip_tabs' ][ 'field' ][ $i ] ?? '' ) === 'review' ) ) {
				continue;
			}
			$data[ 'custom_tabs' ][ 'tab_' . $i ] = [
				'title'   => (string) $trip->get_setting( 'tab_' . $i . '_title', '' ),
				'content' => (string) $trip->get_setting( 'tab_content.' . $i . '_wpeditor', '' ),
			];
		}

		return rest_ensure_response( $this->filter_by_context( $data ) );
	}

	/**
	 * Prepares the data for package.
	 *
	 * @param POST\TripPackage $trip_package
	 *
	 * @return array
	 */
	public function prepare_package( $trip_package ) {
		$return_data = [
			'time_slots_enable' => false,
			'time_slots'        => array_combine( array(
				'MO',
				'TU',
				'WE',
				'TH',
				'FR',
				'SA',
				'SU',
			), array_fill( 0, 7, [] ) ),
			'enable_week_days'  => array_combine( array(
				'MO',
				'TU',
				'WE',
				'TH',
				'FR',
				'SA',
				'SU',
			), array_fill( 0, 7, false ) ),
			'dates'             => array(),
		];
		if ( class_exists( 'WTE_Fixed_Starting_Dates' ) ) {
			foreach ( $trip_package->get_meta( 'package-dates' ) ?? [] as $date_data ) {
				$times = [];
				foreach ( $date_data[ 'times' ] ?? [] as $time ) {
					$times[] = [
						'from' => (string) $time[ 'from' ],
						'to'   => (string) $time[ 'to' ],
					];
				}
				$repeat = [];
				if ( isset( $date_data[ 'rrule' ] ) ) {
					$repeat = [
						'frequency' => (string) ( $date_data[ 'rrule' ][ 'r_frequency' ] ?? '' ),
						'months'    => (array) array_values( $date_data[ 'rrule' ][ 'r_months' ] ?? [] ),
						'weekdays'  => (array) array_values( $date_data[ 'rrule' ][ 'r_weekdays' ] ?? [] ),
						'until'     => (string) $date_data[ 'rrule' ][ 'r_until' ] ?? $date_data[ 'dtstart' ] ?? date( 'Y-m-d' ),
						'limit'     => (int) $date_data[ 'rrule' ][ 'r_count' ] ?? 1,
					];
				}
				$return_data[ 'dates' ][] = [
					'start_date'         => (string) ( $date_data[ 'dtstart' ] ?? date( 'Y-m-d' ) ),
					'times'              => (array) $times,
					'enable_repeat'      => (bool) wptravelengine_toggled( $date_data[ 'is_recurring' ] ?? false ),
					'repeat'             => (array) $repeat,
					'total_seats'        => $date_data[ 'seats' ] ?? '',
					'availability_label' => (string) $date_data[ 'availability_label' ] ?? 'guaranteed',
				];
			}
		} else if ( 'hours' === $trip_package->get_trip()->get_setting( 'trip_duration_unit' ) ) {
			$return_data[ 'time_slots_enable' ] = wptravelengine_toggled( $trip_package->get_meta( 'enable_weekly_time_slots' ) );
			$return_data[ 'enable_week_days' ]  = ! empty( $trip_package->get_meta( 'enable_week_days' ) ) ? $trip_package->get_meta( 'enable_week_days' ) : $return_data[ 'enable_week_days' ];
			$weekly_time_slots                  = $trip_package->get_meta( 'weekly_time_slots' );
			foreach ( array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ) as $index => $day ) {
				$return_data[ 'time_slots' ][ $day ] = $weekly_time_slots[ ++ $index ] ?? [];
			}
		}

		return array_merge( $this->prepare_package_data( $trip_package ), $return_data );
	}

	/**
	 * Get Trip Services.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_services( \WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		try {
			$trip = new Post\Trip( $id );

			$data = $trip->get_services();
		} catch ( \Exception $e ) {
			$data = array();
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get Trip Dates.
	 *
	 * @param WP_REST_Request $request Request Class.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_dates( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		/* @var Post\Trip $trip */
		$trip          = Post\Trip::make( $id );
		$trip_packages = new Post\TripPackages( $trip );

		$from = $request->get_param( 'from' ) ?? date( 'Y-m-d' );
		$to   = $request->get_param( 'to' ) ?? date( 'Y-m-d', strtotime( '+1 year' ) );

		$dates = array();
		foreach ( $trip_packages as $trip_package ) {
			/* @var Post\TripPackage $trip_package */
			$data = array(
				'package' => $this->prepare_package_data( $trip_package ),
				'dates'   => $trip_package->get_package_dates( compact( 'from', 'to' ) ),
			);

			$dates[] = $data;

		}

		return rest_ensure_response( $dates );
	}

	/**
	 * Prepare package data.
	 *
	 * @param Post\TripPackage $trip_package Trip package object.
	 *
	 * @return array
	 */
	public static function prepare_package_data( Post\TripPackage $trip_package ): array {
		$data = array();
		/**
		 * @var Post\TripPackage $trip_package
		 * @var Post\Trip $trip
		 */
		$data[ 'id' ]          = $trip_package->get_id();
		$data[ 'name' ]        = $trip_package->get_title();
		$data[ 'description' ] = $trip_package->get_content();

		$package_categories = $trip_package->get_traveler_categories();

		/** @var Post\TravelerCategory $category */
		foreach ( $package_categories as $category ) {
			$min_pax = $category->get( 'min_pax', '' );
			$min_pax = is_numeric( $min_pax ) ? (int) $min_pax : 0;
			$max_pax = $category->get( 'max_pax', '' );
			$max_pax = is_numeric( $max_pax ) && ( + $max_pax >= $min_pax ) ? (int) $max_pax : '';

			$group_pricing = $category->get( 'group_pricing', array() );

			$group_pricing = array_map( function ( $gp ) {
				return array(
					'from' => is_numeric( $gp[ 'from' ] ) ? (int) $gp[ 'from' ] : 0,
					'to'   => is_numeric( $gp[ 'to' ] ) ? (int) $gp[ 'to' ] : '',
					'rate' => is_numeric( $gp[ 'price' ] ) ? (float) $gp[ 'price' ] : 0,
				);
			}, $group_pricing );

			$pricing_label                   = apply_filters( 'wptravelengine-packages-labels', [
				'per-person' => __( 'Person', 'wp-travel-engine' ),
				'per-group'  => __( 'Group', 'wp-travel-engine' ),
			] );
			$get_pricing_type                = $category->get( 'pricing_type', 'per-person' );
			$price                           = $category->get( 'price', '' );
			$sale_price                      = $category->get( 'sale_price', '' );
			$data[ 'traveler_categories' ][] = array(
				'id'                => (int) $category->get( 'id', 0 ),
				'label'             => $category->get( 'label', '' ),
				'price'             => is_numeric( $price ) ? (float) $price : '',
				'age_group'         => $category->get( 'age_group', '' ),
				'pricing_type'      => array(
					'value' => $get_pricing_type,
					'label' => $pricing_label[ $get_pricing_type ],
				),
				'sale_price'        => is_numeric( $sale_price ) ? (float) $sale_price : '',
				'has_sale'          => wptravelengine_toggled( $category->get( 'has_sale', false ) ),
				'has_group_pricing' => wptravelengine_toggled( $category->get( 'enabled_group_discount', false ) && ! empty( $group_pricing ) ),
				'group_pricing'     => $group_pricing,
				'min_pax'           => $min_pax,
				'max_pax'           => $max_pax,
			);
		}

		return $data;
	}

	/**
	 * Get Trip packages.
	 *
	 * @param WP_REST_Request $request Request Class.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_packages( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$packages = array();

		/* @var Post\Trip $trip */
		$trip          = Post\Trip::make( $id );
		$trip_packages = new Post\TripPackages( $trip );

		foreach ( $trip_packages as $trip_package ) {
			$packages[] = $this->prepare_package_data( $trip_package );
		}

		return rest_ensure_response( $packages );
	}

	/**
	 * Get Trip Schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {

		$schema = parent::get_item_schema();

		$properties = $schema[ 'properties' ] ?? array();

		$item_properties = array(
			// Trip specific properties.
			'trip_code'            => array(
				'description' => __( 'Trip code.', 'wp-travel-engine' ),
				'type'        => 'string',
			),
			'duration'             => array(
				'description' => __( 'Trip duration.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'period' => array(
						'description' => __( 'Duration period.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
					'unit'   => array(
						'description' => __( 'Duration unit.', 'wp-travel-engine' ),
						'type'        => 'string',
						'enum'        => array( 'days', 'hours' ),
					),
					'nights' => array(
						'description' => __( 'Duration nights.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
				),
			),
			'cut_off_time'         => array(
				'description' => __( 'Trip cut off time.', 'wp-travel-engine' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'properties'  => array(
					'enable' => array(
						'description' => __( 'Cut off enabled.', 'wp-travel-engine' ),
						'type'        => 'boolean',
						'enum'        => array( true, false ),
						'context'     => array( 'edit' ),
					),
					'period' => array(
						'description' => __( 'Cut off period.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
					'unit'   => array(
						'description' => __( 'Cut off unit.', 'wp-travel-engine' ),
						'type'        => 'string',
						'enum'        => array( 'days' ),
					),
				),
			),
			'age_limit'            => array(
				'description' => __( 'Trip age.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'enable' => array(
						'description' => __( 'Age enabled.', 'wp-travel-engine' ),
						'type'        => 'boolean',
						'enum'        => array( true, false ),
						'context'     => array( 'edit' ),
					),
					'min'    => array(
						'description' => __( 'Minimum age.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
					'max'    => array(
						'description' => __( 'Maximum age.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
				),
			),
			'participants'         => array(
				'description' => __( 'Minimum and maximum participants for booking this trip.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'enable' => array(
						'description' => __( 'Pax enabled.', 'wp-travel-engine' ),
						'type'        => 'boolean',
						'enum'        => array( true, false ),
						'context'     => array( 'edit' ),
					),
					'min'    => array(
						'description' => __( 'Minimum pax.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
					'max'    => array(
						'description' => __( 'Maximum pax.', 'wp-travel-engine' ),
						'type'        => array( 'integer', 'string' ),
					),
				),
			),
			'fsd'                  => array(
				'description' => __( 'Fixed starting dates.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'title' => array(
						'description' => __( 'Fixed starting dates title.', 'wp-travel-engine' ),
						'type'        => 'string',
						'context'     => array( 'edit' ),
					),
					'hide'  => array(
						'description' => __( 'Fixed starting dates hide.', 'wp-travel-engine' ),
						'type'        => 'boolean',
						'enum'        => array( true, false ),
					),
				),
			),
			'overview_title'       => array(
				'description' => __( 'Trip overview title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'overview'             => array(
				'description' => __( 'Trip overview.', 'wp-travel-engine' ),
				'type'        => 'string',
			),
			'highlights_title'     => array(
				'description' => __( 'Trip highlights title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'highlights'           => array(
				'description' => __( 'Trip highlights.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
			),
			'itinerary_title'      => array(
				'description' => __( 'Trip itinerary title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'itineraries'          => array(
				'description' => __( 'Trip itineraries.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'          => array(
							'description' => __( 'Itinerary title.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'label'          => array(
							'description' => __( 'Itinerary label.', 'wp-travel-engine' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'content'        => array(
							'description' => __( 'Itinerary content.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'period'         => array(
							'description' => __( 'Itinerary period.', 'wp-travel-engine' ),
							'type'        => 'float',
						),
						'unit'           => array(
							'description' => __( 'Itinerary period unit.', 'wp-travel-engine' ),
							'type'        => 'string',
							'enum'        => array( 'hour', 'minute' ),
						),
						'sleep_mode'     => array(
							'description' => __( 'Itinerary sleep mode.', 'wp-travel-engine' ),
							'type'        => 'object',
							'properties'  => array(
								'field_id'    => array(
									'description' => __( 'Sleep mode field.', 'wp-travel-engine' ),
									'type'        => 'string',
								),
								'description' => array(
									'description' => __( 'Sleep mode description.', 'wp-travel-engine' ),
									'type'        => 'string',
								),
							),
						),
						'meals_included' => array(
							'description' => __( 'Itinerary meals included.', 'wp-travel-engine' ),
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
						'images'         => array(
							'description' => __( 'Itinerary images.', 'wp-travel-engine' ),
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'        => array(
										'description' => __( 'Image ID.', 'wp-travel-engine' ),
										'type'        => 'integer',
									),
									'alt'       => array(
										'description' => __( 'Image alt.', 'wp-travel-engine' ),
										'type'        => 'string',
									),
									'thumbnail' => [
										'description' => __( 'Thumbnail URL.', 'wp-travel-engine' ),
										'type'        => array( 'object', 'null' ),
										'properties'  => [
											'url'         => [
												'description' => __( 'Thumbnail image URL.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
											'width'       => [
												'description' => __( 'Thumbnail image width.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'height'      => [
												'description' => __( 'Thumbnail image height.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'orientation' => [
												'description' => __( 'Thumbnail image orientation.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
										],
									],
									'medium'    => [
										'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
										'type'        => array( 'object', 'null' ),
										'properties'  => [
											'url'         => [
												'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
											'width'       => [
												'description' => __( 'Medium image width.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'height'      => [
												'description' => __( 'Medium image height.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'orientation' => [
												'description' => __( 'Medium image orientation.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
										],
									],
									'full'      => [
										'description' => __( 'Full image URL.', 'wp-travel-engine' ),
										'type'        => array( 'object', 'null' ),
										'properties'  => [
											'url'         => [
												'description' => __( 'Full image URL.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
											'width'       => [
												'description' => __( 'Full image width.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'height'      => [
												'description' => __( 'Full image height.', 'wp-travel-engine' ),
												'type'        => 'integer',
											],
											'orientation' => [
												'description' => __( 'Full image orientation.', 'wp-travel-engine' ),
												'type'        => 'string',
											],
										],
									],
								),
							),
						),
						'overnights'     => array(
							'description' => __( 'Itinerary overnights.', 'wp-travel-engine' ),
							'type'        => 'object',
							'properties'  => array(
								'location' => array(
									'description' => __( 'Overnight location.', 'wp-travel-engine' ),
									'type'        => 'string',
								),
								'altitude' => array(
									'description' => __( 'Overnight altitude.', 'wp-travel-engine' ),
									'type'        => 'float',
								),
							),
						),
					),
				),
			),
			'cost_title'           => array(
				'description' => __( 'Trip cost title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'cost_includes_title'  => array(
				'description' => __( 'Trip cost includes title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'cost_includes'        => array(
				'description' => __( 'Trip cost includes.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
			),
			'cost_excludes_title'  => array(
				'description' => __( 'Trip cost excludes title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'cost_excludes'        => array(
				'description' => __( 'Trip cost excludes.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
			),
			'trip_info_title'      => array(
				'description' => __( 'Trip info title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'trip_info'            => array(
				'description' => __( 'Trip facts.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'description' => __( 'Facts ID.', 'wp-travel-engine' ),
							'type'        => 'integer',
						),
						'label'   => array(
							'description' => __( 'Facts label.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'content' => array(
							'description' => __( 'Facts content.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'type'    => array(
							'description' => __( 'Facts type.', 'wp-travel-engine' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'options' => array(
							'description' => __( 'Facts options.', 'wp-travel-engine' ),
							'context'     => array( 'edit' ),
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
				),
			),
			'gallery_enable'       => array(
				'description' => __( 'Trip gallery enabled.', 'wp-travel-engine' ),
				'type'        => 'boolean',
				'enum'        => array( true, false ),
				'context'     => array( 'edit' ),
			),
			'gallery'              => array(
				'description' => __( 'Trip image gallery.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => [
						'id'        => [
							'description' => __( 'Attachment ID.', 'wp-travel-engine' ),
							'type'        => 'integer',
						],
						'alt'       => [
							'description' => __( 'Image alt text.', 'wp-travel-engine' ),
							'type'        => 'string',
						],
						'thumbnail' => [
							'description' => __( 'Thumbnail URL.', 'wp-travel-engine' ),
							'type'        => array( 'object', 'null' ),
							'properties'  => [
								'url'         => [
									'description' => __( 'Thumbnail image URL.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
								'width'       => [
									'description' => __( 'Thumbnail image width.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'height'      => [
									'description' => __( 'Thumbnail image height.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'orientation' => [
									'description' => __( 'Thumbnail image orientation.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
							],
						],
						'medium'    => [
							'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
							'type'        => array( 'object', 'null' ),
							'properties'  => [
								'url'         => [
									'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
								'width'       => [
									'description' => __( 'Medium image width.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'height'      => [
									'description' => __( 'Medium image height.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'orientation' => [
									'description' => __( 'Medium image orientation.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
							],
						],
						'full'      => [
							'description' => __( 'Full image URL.', 'wp-travel-engine' ),
							'type'        => array( 'object', 'null' ),
							'properties'  => [
								'url'         => [
									'description' => __( 'Full image URL.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
								'width'       => [
									'description' => __( 'Full image width.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'height'      => [
									'description' => __( 'Full image height.', 'wp-travel-engine' ),
									'type'        => 'integer',
								],
								'orientation' => [
									'description' => __( 'Full image orientation.', 'wp-travel-engine' ),
									'type'        => 'string',
								],
							],
						],
					],
					'context'    => [ 'view' ],
				),
			),
			'video_gallery_enable' => array(
				'description' => __( 'Trip video gallery enabled.', 'wp-travel-engine' ),
				'type'        => 'boolean',
				'enum'        => array( true, false ),
				'context'     => array( 'edit' ),
			),
			'video_gallery'        => array(
				'description' => __( 'Trip video gallery.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'url'       => array(
							'description' => __( 'Video URL.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'thumbnail' => array(
							'description' => __( 'Video thumbnail.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
					),
				),
			),
			'map_title'            => array(
				'description' => __( 'Trip map title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'trip_map'             => array(
				'description' => __( 'Trip map.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'images' => array(
						'description' => __( 'Map images.', 'wp-travel-engine' ),
						'type'        => 'array',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'        => array(
									'description' => __( 'Attachment ID.', 'wp-travel-engine' ),
									'type'        => 'integer',
								),
								'alt'       => array(
									'description' => __( 'Image alt text.', 'wp-travel-engine' ),
									'type'        => 'string',
								),
								'thumbnail' => [
									'description' => __( 'Thumbnail URL.', 'wp-travel-engine' ),
									'type'        => array( 'object', 'null' ),
									'properties'  => [
										'url'         => [
											'description' => __( 'Thumbnail image URL.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
										'width'       => [
											'description' => __( 'Thumbnail image width.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'height'      => [
											'description' => __( 'Thumbnail image height.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'orientation' => [
											'description' => __( 'Thumbnail image orientation.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
									],
								],
								'medium'    => [
									'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
									'type'        => array( 'object', 'null' ),
									'properties'  => [
										'url'         => [
											'description' => __( 'Medium image URL.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
										'width'       => [
											'description' => __( 'Medium image width.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'height'      => [
											'description' => __( 'Medium image height.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'orientation' => [
											'description' => __( 'Medium image orientation.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
									],
								],
								'full'      => [
									'description' => __( 'Full image URL.', 'wp-travel-engine' ),
									'type'        => array( 'object', 'null' ),
									'properties'  => [
										'url'         => [
											'description' => __( 'Full image URL.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
										'width'       => [
											'description' => __( 'Full image width.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'height'      => [
											'description' => __( 'Full image height.', 'wp-travel-engine' ),
											'type'        => 'integer',
										],
										'orientation' => [
											'description' => __( 'Full image orientation.', 'wp-travel-engine' ),
											'type'        => 'string',
										],
									],
								],
							),
						),
					),
					'iframe' => array(
						'description' => __( 'Map iframe.', 'wp-travel-engine' ),
						'type'        => 'string',
					),
				),
			),
			'faqs_title'           => array(
				'description' => __( 'Trip FAQs title.', 'wp-travel-engine' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
			),
			'faqs'                 => array(
				'description' => __( 'Trip FAQs.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'question' => array(
							'description' => __( 'Question.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'answer'   => array(
							'description' => __( 'Answer.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
					),
				),
			),
			'full_payment_enable'  => array(
				'description' => __( 'Trip full payment enabled.', 'wp-travel-engine' ),
				'type'        => 'boolean',
				'enum'        => array( true, false ),
			),
			'partial_payment'      => array(
				'description' => __( 'Trip partial payment.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'enable'     => array(
						'description' => __( 'Partial payment enabled.', 'wp-travel-engine' ),
						'type'        => 'boolean',
						'enum'        => array( true, false ),
					),
					'amount'     => array(
						'description' => __( 'Partial payment amount.', 'wp-travel-engine' ),
						'type'        => 'float',
					),
					'percentage' => array(
						'description' => __( 'Partial payment percentage.', 'wp-travel-engine' ),
						'type'        => 'float',
					),
				),
			),
			'file_downloads'       => array(
				'description' => __( 'Trip file downloads.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'description' => __( 'File downloads id.', 'wp-travel-engine' ),
							'type'        => 'integer',
							'context'     => array( 'edit' ),
						),
						'type'  => array(
							'description' => __( 'File downloads type.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'title' => array(
							'description' => __( 'File downloads title.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'url'   => array(
							'description' => __( 'File downloads url.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
					),
				),
			),
			'trip_extra_services'  => array(
				'description' => __( 'Trip extra services.', 'wp-travel-engine' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'description' => __( 'Extra service ID.', 'wp-travel-engine' ),
							'type'        => 'integer',
						),
						'label'   => array(
							'description' => __( 'Extra service label.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'type'    => array(
							'description' => __( 'Extra service type.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'options' => array(
							'description' => __( 'Extra service options.', 'wp-travel-engine' ),
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
				),
			),
			'packages'             => array(
				'description' => __( 'Trip packages.', 'wp-travel-engine' ),
				'type'        => 'array',
				'context'     => array( 'edit' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'                  => array(
							'description' => __( 'Package ID.', 'wp-travel-engine' ),
							'type'        => array( 'integer', 'null' ),
						),
						'name'                => array(
							'description' => __( 'Package name.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'description'         => array(
							'description' => __( 'Package description.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'is_primary'          => array(
							'description' => __( 'Is package primary.', 'wp-travel-engine' ),
							'type'        => 'boolean',
							'enum'        => array( true, false ),
						),
						'_changed'            => array(
							'description' => __( 'Package changed.', 'wp-travel-engine' ),
							'type'        => 'boolean',
							'enum'        => array( true, false ),
						),
						'traveler_categories' => array(
							'description' => __( 'Package traveler categories.', 'wp-travel-engine' ),
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'                => array(
										'description' => __( 'Traveler category ID.', 'wp-travel-engine' ),
										'type'        => 'integer',
									),
									'label'             => array(
										'description' => __( 'Traveler category label.', 'wp-travel-engine' ),
										'type'        => 'string',
									),
									'price'             => array(
										'description' => __( 'Traveler category price.', 'wp-travel-engine' ),
										'type'        => array( 'float', 'string' ),
									),
									'pricing_type'      => array(
										'description' => __( 'Traveler category pricing type.', 'wp-travel-engine' ),
										'type'        => 'object',
										'properties'  => array(
											'value' => array(
												'description' => __( 'Pricing type value.', 'wp-travel-engine' ),
												'type'        => 'string',
											),
											'label' => array(
												'description' => __( 'Pricing type label.', 'wp-travel-engine' ),
												'type'        => 'string',
											),
										),
									),
									'sale_price'        => array(
										'description' => __( 'Traveler category sale price.', 'wp-travel-engine' ),
										'type'        => array( 'float', 'string' ),
									),
									'has_sale'          => array(
										'description' => __( 'Traveler category has sale.', 'wp-travel-engine' ),
										'type'        => 'boolean',
										'enum'        => array( true, false ),
									),
									'has_group_pricing' => array(
										'description' => __( 'Traveler category has group pricing.', 'wp-travel-engine' ),
										'type'        => 'boolean',
										'enum'        => array( true, false ),
									),
									'group_pricing'     => array(
										'description' => __( 'Traveler category group pricing.', 'wp-travel-engine' ),
										'type'        => 'array',
										'items'       => array(
											'type'       => 'object',
											'properties' => array(
												'form' => array(
													'description' => __( 'Group pricing form.', 'wp-travel-engine' ),
													'type'        => 'integer',
												),
												'to'   => array(
													'description' => __( 'Group pricing to.', 'wp-travel-engine' ),
													'type'        => array( 'integer', 'string' ),
												),
												'rate' => array(
													'description' => __( 'Group pricing rate.', 'wp-travel-engine' ),
													'type'        => 'float',
												),
											),
										),
									),
									'min_pax'           => array(
										'description' => __( 'Traveler category minimum pax.', 'wp-travel-engine' ),
										'type'        => array( 'integer', 'string' ),
									),
									'max_pax'           => array(
										'description' => __( 'Traveler category maximum pax.', 'wp-travel-engine' ),
										'type'        => array( 'integer', 'string' ),
									),
								),
							),
						),
						'time_slots_enable'   => array(
							'description' => __( 'Package time slots enabled.', 'wp-travel-engine' ),
							'type'        => 'boolean',
							'enum'        => array( true, false ),
						),
						'time_slots'          => array(
							'description' => __( 'Package time slots.', 'wp-travel-engine' ),
							'type'        => 'object',
							'properties'  => array(
								'MO' => array(
									'description' => __( 'Monday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'TU' => array(
									'description' => __( 'Tuesday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'WE' => array(
									'description' => __( 'Wednesday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'TH' => array(
									'description' => __( 'Thursday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'FR' => array(
									'description' => __( 'Friday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'SA' => array(
									'description' => __( 'Saturday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
								'SU' => array(
									'description' => __( 'Sunday time slots.', 'wp-travel-engine' ),
									'type'        => array( 'array' ),
								),
							),
						),
						'enable_week_days'    => array(
							'description' => __( 'Package weekly time enabled.', 'wp-travel-engine' ),
							'type'        => 'object',
							'context'     => array( 'edit' ),
							'properties'  => array(
								'MO' => array(
									'description' => __( 'Monday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'TU' => array(
									'description' => __( 'Tuesday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'WE' => array(
									'description' => __( 'Wednesday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'TH' => array(
									'description' => __( 'Thursday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'FR' => array(
									'description' => __( 'Friday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'SA' => array(
									'description' => __( 'Saturday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
								'SU' => array(
									'description' => __( 'Sunday time enabled.', 'wp-travel-engine' ),
									'type'        => 'boolean',
									'enum'        => array( true, false ),
								),
							),
						),
						'dates'               => array(
							'description' => __( 'Package dates.', 'wp-travel-engine' ),
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'start_date'         => array(
										'description' => __( 'Package start date.', 'wp-travel-engine' ),
										'type'        => 'string',
									),
									'times'              => array(
										'description' => __( 'Package times.', 'wp-travel-engine' ),
										'type'        => 'array',
										'items'       => array(
											'type'       => 'object',
											'properties' => array(
												'from' => array(
													'description' => __( 'Package time from.', 'wp-travel-engine' ),
													'type'        => 'string',
												),
												'to'   => array(
													'description' => __( 'Package time to.', 'wp-travel-engine' ),
													'type'        => 'string',
												),
											),
										),
									),
									'enable_repeat'      => array(
										'description' => __( 'Package enable repeat.', 'wp-travel-engine' ),
										'type'        => 'boolean',
										'enum'        => array( true, false ),
									),
									'repeat'             => array(
										'description' => __( 'Package repeat.', 'wp-travel-engine' ),
										'type'        => 'object',
										'properties'  => array(
											'frequency' => array(
												'description' => __( 'Package repeated frequency.', 'wp-travel-engine' ),
												'type'        => 'string',
											),
											'weekdays'  => array(
												'description' => __( 'Package repeated weekdays.', 'wp-travel-engine' ),
												'type'        => 'array',
												'items'       => array(
													'type' => 'string',
												),
											),
											'months'    => array(
												'description' => __( 'Package repeated months.', 'wp-travel-engine' ),
												'type'        => 'array',
												'items'       => array(
													'type' => 'integer',
												),
											),
											'until'     => array(
												'description' => __( 'Package repeated until.', 'wp-travel-engine' ),
												'type'        => 'string',
											),
											'limit'     => array(
												'description' => __( 'Package repeated limit.', 'wp-travel-engine' ),
												'type'        => array( 'integer', 'string' ),
											),
										),
									),
									'total_seats'        => array(
										'description' => __( 'Package total seats.', 'wp-travel-engine' ),
										'type'        => array( 'integer', 'string' ),
									),
									'availability_label' => array(
										'description' => __( 'Package availability label.', 'wp-travel-engine' ),
										'type'        => 'string',
									),
								),
							),
						),
					),
				),
			),
			'custom_tabs'          => array(
				'description' => __( 'Trip custom tabs.', 'wp-travel-engine' ),
				'type'        => 'object',
				'properties'  => array(
					'type'  => 'array',
					'items' => array(
						'title'   => array(
							'description' => __( 'Custom tab title.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
						'content' => array(
							'description' => __( 'Custom tab content.', 'wp-travel-engine' ),
							'type'        => 'string',
						),
					),
				),
			),
		);

		$schema[ 'properties' ] = array_merge( $properties, $item_properties );

		return $schema;

	}

	/**
	 * Upload image from URL.
	 *
	 * @param string $src Image URL.
	 *
	 * @return int
	 */
	protected function upload_image( string $src ): int {
		$upload_dir = wp_upload_dir();
		$filename   = basename( $src );
		$filename   = sanitize_file_name( $filename );
		$filename   = wp_unique_filename( $upload_dir[ 'path' ], $filename );

		$contents = wp_remote_retrieve_body( wp_remote_get( $src ) );

		if ( ! $contents ) {
			return 0;
		}

		$upload = wp_upload_bits( $filename, null, $contents );

		if ( ! empty( $upload[ 'error' ] ) ) {
			return 0;
		}

		$allowed_mime_types = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'bmp'          => 'image/bmp',
			'tiff|tif'     => 'image/tiff',
			'webp'         => 'image/webp',
		);

		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => wp_check_filetype( $upload[ 'file' ], $allowed_mime_types )[ 'type' ],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		), $upload[ 'file' ] );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * This method is used to filter response data
	 * based on the context and current user.
	 *
	 * @param array $response_data Response data.
	 *
	 * @return array
	 */
	public function filter_by_context( $response_data ) {

		if ( ! current_user_can( 'edit_posts', $response_data[ 'id' ] ?? null ) ) {

			$filter_recursive = function ( &$data, $schema ) use ( &$filter_recursive ) {

				foreach ( $data as $key => &$value ) {

					$current_context = $schema[ $key ][ 'context' ] ?? $schema[ 'context' ] ?? array();

					if ( in_array( 'edit', array_merge_recursive( $current_context ), true ) && ! in_array( 'view', $current_context, true ) ) {
						unset( $data[ $key ] );
						continue;
					}

					if ( is_array( $value ) ) {
						$current_items = $schema[ $key ][ 'items' ] ?? $schema[ 'items' ] ?? array();
						$current_props = $schema[ $key ][ 'properties' ] ?? $schema[ 'properties' ] ?? null;
						$filter_recursive( $value, $current_props ?? $current_items );
					}

				}

			};

			$filter_recursive( $response_data, $this->get_item_schema()[ 'properties' ] );

		}

		return $response_data;

	}
}
