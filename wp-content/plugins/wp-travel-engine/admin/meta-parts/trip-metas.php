<?php

/**
 * Trip Metas Tabs HTML
 *
 * @package WP_Travel_Engine
 */
global $post;
$trip = \WPTravelEngine\Core\Models\Post\Trip::make( $post );
//Sets pricing categories.
\WPTravelEngine\Packages\get_packages_pricing_categories();

$global_settings = wptravelengine_settings()->get();

$id                          = $trip->get_id();
$isPercentage                = "percent" === ( $global_settings[ 'partial_payment_option' ] ?? false );
$isFSDActive                 = class_exists( 'WTE_Fixed_Starting_Dates' );
$isPartialPaymentEnabled     = wptravelengine_toggled( $global_settings[ 'partial_payment_enable' ] ?? false );
$isPartialPaymentActive      = class_exists( 'Wte_Partial_Payment_Admin' );
$isExtraServicesActive       = class_exists( 'Extra_Services_Wp_Travel_Engine' );
$isAdvancedActive            = class_exists( 'WTE_Advanced_Itinerary' );
$isFileDownloadsActive       = class_exists( 'WTE_File_Downloads' );
$isGroupDisountActive        = class_exists( 'Wp_Travel_Engine_Group_Discount' );
$isItineraryDownloaderActive = class_exists( 'Wte_Itinerary_Downloader' );

$pricing_categories = array_map( fn ( $term ) => [
	'id'    => $term->term_id,
	'label' => $term->name,
], get_terms( [
	'taxonomy'   => 'trip-packages-categories',
	'hide_empty' => false,
] ) ?? []
);

$global_trip_facts = wptravelengine_get_trip_facts_options();
$trip_facts        = [ [ 'label' => __( 'Select Trip Fact', 'wp-travel-engine' ), 'value' => '' ] ];
foreach ( $global_trip_facts[ 'field_id' ] ?? [] as $key => $label ) {
	$trip_facts[] = [
		'id'          => (int) $key ?? 0,
		'label'       => (string) $label ?? '',
		'type'        => (string) $global_trip_facts[ 'field_type' ][ $key ] ?? '',
		'placeholder' => (string) $global_trip_facts[ 'input_placeholder' ][ $key ] ?? '',
		'options'     => (array) explode( ',', $global_trip_facts[ 'select_options' ][ $key ] ?? '' ),
	];
}

$global_files = (array) ( $global_settings[ 'file_downloads' ][ 'wte_files_downloadable' ] ?? [] );
$files        = [ [ 'label' => __( 'File Downloads', 'wp-travel-engine' ), 'value' => '' ] ];
foreach ( $global_files as $key => $value ) {
	$files[] = [
		'value'    => (int) ( $value[ 'id' ] ?? 0 ),
		'label'    => (string) ( $value[ 'title' ] ?? '' ),
		'dataType' => (string) get_post_mime_type( $value[ 'id' ] ?? '' ),
		'dataUrl'  => (string) ( $value[ 'url' ] ?? '' ),
	];
}

$extra_services = [
	[
		'label'  => __( 'Select Extra Service', 'wp-travel-engine' ),
		'id'     => '',
		'type'   => '',
		'option' => [],
	],
];
$services = get_posts(
	array(
		'post_type'      => 'wte-services',
		'post_status'    => 'publish',
		'posts_per_page' => - 1,
		'orderby'        => 'post__in',
	)
);
foreach ( $services ?? [] as $service ) {
	$service_data     = get_post_meta( $service->ID, 'wte_services', true );
	$extra_services[] = [
		'id'      => (int) $service->ID ?? 0,
		'label'   => (string) $service->post_title ?? '',
		'type'    => (string) $service_data[ 'service_type' ] ?? '',
		'options' => (array) $service_data[ 'options' ] ?? '',
	];
}

$sleepModeOptions = [ [ 'label' => __( 'Select Sleep Mode', 'wp-travel-engine' ), 'value' => '' ] ];
foreach ( $global_settings[ 'wte_advance_itinerary' ][ 'itinerary_sleep_mode_fields' ] ?? [] as $sleepModes ) {
	$sleepModeOptions[] = [
		'label' => $sleepModes[ 'field_text' ] ?? '',
		'value' => $sleepModes[ 'field_text' ] ?? '',
	];
}

$trip_meta_tabs = array(
	'wpte-general'         => array(
		'tab_label'         => esc_html__( 'General', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'General', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/general.php',
		'callback_function' => 'wpte_edit_trip_tab_general',
		'content_key'       => 'wpte-general',
		'current'           => true,
		'content_loaded'    => true,
		'priority'          => 10,
		'icon'              => 'info',
		'fields'            => array(
			array(
				'label'   => __( 'Trip Code', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'trip_code',
					'type'        => 'TEXT',
					'placeholder' => __( 'Enter Trip Code', 'wp-travel-engine' ),
				],
			),
			array(
				'description' => __( 'Enter the duration ( number ) for the trip and choose desired unit.', 'wp-travel-engine' ),
				'label'       => __( 'Duration', 'wp-travel-engine' ),
				'divider'     => true,
				'field'       => [
					'type' => 'GROUP',
				],
				'sub_fields'  => array(
					array(
						'field' => [
							'name'       => 'duration.period',
							'type'       => 'NUMBER',
							'attributes' => [
								'style' => [ 'width' => '64px' ],
							],
							'suffix'     => [
								'type'    => 'field',
								'variant' => 'solid',
								'field'   => [
									'type'    => 'SELECT',
									'name'    => 'duration.unit',
									'options' => [
										[ 'label' => __( 'Days', 'wp-travel-engine' ), 'value' => 'days' ],
										[ 'label' => __( 'Hours', 'wp-travel-engine' ), 'value' => 'hours' ],
									],
								],
							],
						],
					),
					array(
						'condition' => 'duration.unit == days',
						'field'     => [
							'name'       => 'duration.nights',
							'type'       => 'NUMBER',
							'min'        => 0,
							'attributes' => [
								'min'   => [
									'value'   => 0,
									'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
								],
								'style' => [ 'width' => '68px' ],
							],
							'suffix'     => [
								'type'    => 'field',
								'field'   => [
									'defaultValue' => __( 'Night(s)', 'wp-travel-engine' ),
									'type'         => 'TEXT',
									'readOnly'     => true,
								],
								'variant' => 'solid',
							],
						],
					),
				),
			),
			array(
				'condition'   => 'duration.unit == days',
				'label'       => __( 'Enable Cut-off Time', 'wp-travel-engine' ),
				'description' => __( 'The cut-off time will be the time before which bookings are allowed for the trip.', 'wp-travel-engine' ),
				'field'       => [
					'name' => 'cut_off_time.enable',
					'type' => 'SWITCH',
				],
			),
			array(
				'label'       => true,
				'description' => __( 'Enter trip cut-off value in number of days. If you set your cutoff time to 1 day, the product cannot be booked with a start date today. If 2 days, the product cannot be booked with a start date today and tomorrow etc.', 'wp-travel-engine' ),
				'condition'   => 'cut_off_time.enable == true && duration.unit == days',
				'field'       => [
					'name'        => 'cut_off_time.period',
					'type'        => 'NUMBER',
					'placeholder' => __( 'Enter Cut-off Time', 'wp-travel-engine' ),
					'min'         => 0,
					'attributes'  => [
						'style' => [ 'maxWidth' => '225px' ],
					],
					'suffix'      => [
						'type'    => 'field',
						'field'   => [
							'name'         => 'cut_off_time.unit',
							'defaultValue' => __( 'Days', 'wp-travel-engine' ),
							'type'         => 'TEXT',
							'readOnly'     => true,
						],
						'variant' => 'solid',
					],
				],
			),
			array(
				'condition' => 'cut_off_time.enable == true && duration.unit == days',
				'field'     => [
					'type' => 'DIVIDER',
				],
			),
			array(
				'label' => __( 'Set Minimum And Maximum Age', 'wp-travel-engine' ),
				'field' => [
					'name' => 'age_limit.enable',
					'type' => 'SWITCH',
				],
			),
			array(
				'condition'  => 'age_limit.enable == true',
				'label'      => true,
				'cols'       => 2,
				'field'      => [
					'type' => 'GROUP',
				],
				'sub_fields' => [
					array(
						'label' => 'Minimum Age',
						'field' => [
							'name'       => 'age_limit.min',
							'type'       => 'NUMBER',
							'min'        => 0,
							'attributes' => [
								'min' => [
									'value'   => 0,
									'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
								],
							],
						],
					),
					array(
						'label' => 'Maximum Age',
						'field' => [
							'name'       => 'age_limit.max',
							'type'       => 'NUMBER',
							'min'        => 0,
							'attributes' => [
								'min' => [
									'value'   => 0,
									'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
								],
							],
						],
					),
				],
			),
			array(
				'field' => [
					'type' => 'DIVIDER',
				],
			),
			array(
				'label' => __( 'Set Minimum And Maximum Participants(Optional)', 'wp-travel-engine' ),
				'field' => [
					'name' => 'participants.enable',
					'type' => 'SWITCH',
				],
			),
			array(
				'condition'   => 'participants.enable == true',
				'label'       => true,
				'description' => __( '', 'wp-travel-engine' ),
				'cols'        => 2,
				'field'       => [
					'type' => 'GROUP',
				],
				'sub_fields'  => [
					array(
						'label' => 'Minimum Participants',
						'field' => [
							'name'       => 'participants.min',
							'type'       => 'NUMBER',
							'min'        => 0,
							'attributes' => [
								'min' => [
									'value'   => 0,
									'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
								],
							],
						],
					),
					array(
						'label' => 'Maximum Participants',
						'field' => [
							'name'       => 'participants.max',
							'type'       => 'NUMBER',
							'min'        => 1,
							'attributes' => [
								'min' => [
									'value'   => 0,
									'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
								],
							],
						],
					),
				],
			),
		),
	),
	'wpte-pricing'         => array(
		'tab_label'         => esc_html__( 'Pricing', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Pricing', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/pricing.php',
		'callback_function' => 'wpte_edit_trip_tab_pricing',
		'content_key'       => 'wpte-pricing',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 20,
		'icon'              => 'calendarcheck',
		'fields'            => array(
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Packages', 'wp-travel-engine' ),
				],
			),
			array(
				'divider' => true,
				'field'   => [
					'type'                 => 'PACKAGES',
					'name'                 => 'packages',
					'currency_code'        => $global_settings[ 'currency_code' ] ?? '',
					'isGroupDisountActive' => $isGroupDisountActive,
					'isFSDActive'          => $isFSDActive,
					'pricingCategories'    => $pricing_categories,
				],
			),
			array(
				'field'      => [
					'type'  => 'TITLE',
					'title' => __( 'Date Settings', 'wp-travel-engine' ),
				],
				'visibility' => $isFSDActive,
			),
			array(
				'label'       => __( 'Section Title', 'wp-travel-engine' ),
				'description' => __( 'Enter title for the Availability section.', 'wp-travel-engine' ),
				'divider'     => true,
				'field'       => [
					'name'        => 'fsd.title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Enter here', 'wp-travel-engine' ),
				],
				'visibility'  => $isFSDActive,
			),
			array(
				'divider'     => true,
				'label'       => __( 'Hide Fixed Trip Starts Dates section', 'wp-travel-engine' ),
				'description' => __( 'Check this if you want to disable fixed trip starting dates section between featured image/slider and trip content sections.', 'wp-travel-engine' ),
				'field'       => [
					'name' => 'fsd.hide',
					'type' => 'SWITCH',
				],
				'visibility'  => $isFSDActive,
			),
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Partial Payment', 'wp-travel-engine' ),
				],
			),
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( 'Want to collect upfront or partial payment? Partial Payment extension allows you to set upfront payment in percentage or fixed amount which travellers can pay when booking a tour. <a href="https://wptravelengine.com/plugins/partial-payment/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro" target="_blank">Get Partial Payment extension now</a>', 'wp-travel-engine' ),
				],
				'visibility' => ! $isPartialPaymentActive,
			),
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( 'Partial Payment is disabled. Please enable Partial Payment via <strong>WP Travel Engine > Settings > Extensions > Partial Payment.</strong>', 'wp-travel-engine' ),
				],
				'visibility' => $isPartialPaymentActive && ! $isPartialPaymentEnabled,
			),
			array(
				'label'       => __( 'Enable Partial Payment', 'wp-travel-engine' ),
				'description' => __( 'Toggle the switch to enable partial payment for this trip on checkout. You can manually enter payout amount/percentage in the field below.', 'wp-travel-engine' ),
				'field'       => [
					'name' => 'partial_payment.enable',
					'type' => 'SWITCH',
				],
				'visibility'  => $isPartialPaymentActive && $isPartialPaymentEnabled,
			),
			array(
				'visibility'  => $isPartialPaymentActive && $isPartialPaymentEnabled,
				'condition'   => 'partial_payment.enable == true',
				'label'       => $isPercentage ? __( 'Partial Payment Percentage', 'wp-travel-engine' ) : __( 'Partial Payment Amount', 'wp-travel-engine' ),
				'description' => $isPercentage ? __( 'Please enter the desired partial percentage to be applied.', 'wp-travel-engine' ) : __( 'Please enter the desired partial amount to be applied.', 'wp-travel-engine' ),
				'field'       => [
					'name'       => $isPercentage ? 'partial_payment.percentage' : 'partial_payment.amount',
					'type'       => 'NUMBER',
					'min'        => 0,
					'attributes' => [
						'min'   => [
							'value'   => 0,
							'message' => __( 'Minimum value must be greater than 0', 'wp-travel-engine' ),
						],
						'style' => [
							'width'     => '100px',
							'textAlign' => 'center',
						],
					],
					'suffix'     => $isPercentage
						? [
							'type'    => 'field',
							'field'   => [
								'defaultValue' => __( '%', 'wp-travel-engine' ),
								'type'         => 'TEXT',
								'readOnly'     => true,
								'attributes'   => [
									'style' => [ 'width' => '45px' ],
								],
							],
							'variant' => 'solid',
						] : false,
					'prefix'     => ! $isPercentage
						? [
							'type'    => 'field',
							'field'   => [
								'defaultValue' => $global_settings[ 'currency_code' ] ?? '',
								'type'         => 'TEXT',
								'readOnly'     => true,
								'attributes'   => [
									'style' => [
										'width'     => '60px',
										'textAlign' => 'center',
									],
								],
							],
							'variant' => 'solid',
						] : false,
				],
			),
			array(
				'visibility'  => $isPartialPaymentActive && $isPartialPaymentEnabled,
				'condition'   => 'partial_payment.enable == true',
				'label'       => __( 'Enable Full Payment', 'wp-travel-engine' ),
				'description' => __( 'Toggle the switch to enable full payment for this trip on checkout.', 'wp-travel-engine' ),
				'field'       => [
					'name' => 'full_payment_enable',
					'type' => 'SWITCH',
				],
			),
		),
	),
	'wpte-overview'        => array(
		'tab_label'         => esc_html__( 'Overview', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Overview', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/overview.php',
		'callback_function' => 'wpte_edit_trip_tab_overview',
		'content_key'       => 'wpte-overview',
		'current'           => false,
		'content_loaded'    => true,
		'priority'          => 25,
		'icon'              => 'filesearch',
		'fields'            => array(
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Trip Desciption', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'overview_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Overview', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'Trip Description', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'overview',
					'type'        => 'EDITOR',
					'placeholder' => __( 'Overview', 'wp-travel-engine' ),
				],
			),
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Trip Highlights', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'highlights_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Highlights', 'wp-travel-engine' ),
				],
			),
			array(
				'field' => [
					'type' => 'HIGHLIGHTS',
					'name' => 'highlights',
				],
			),
		),
	),
	'wpte-itinerary'       => array(
		'tab_label'         => esc_html__( 'Itinerary', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Itinerary', 'wp-travel-engine' ),
		'content_path'      => apply_filters( 'wte_trip_itinerary_setting_path', WP_TRAVEL_ENGINE_BASE_PATH . '/admin/meta-parts/trip-tabs/itinerary.php' ),
		'callback_function' => 'wpte_edit_trip_tab_itinerary',
		'content_key'       => 'wpte-itinerary',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 30,
		'icon'              => 'route',
		'fields'            => array(
			array(
				'label'       => __( 'Section Title', 'wp-travel-engine' ),
				'description' => __( 'Enter title for the trip itinerary section tab content.', 'wp-travel-engine' ),
				'divider'     => true,
				'field'       => [
					'name'        => 'itinerary_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Itinerary', 'wp-travel-engine' ),
				],
			),
			array(
				'visibility' => ! $isAdvancedActive,
				'divider'    => true,
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<strong>NOTE:</strong> Need additional itinerary fields or require rich text editing for the itinerary? Advanced Itinerary Builder extension provides a rich text editor, sleeping mode, meals, ability to add photos to each day and more. <a href="https://wptravelengine.com/plugins/advanced-itinerary-builder/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro" target="_blank">Get Advanced Itinerary Builder extension now</a>', 'wp-travel-engine' ),
				],
			),
			array(
				'visibility' => $isAdvancedActive,
				'divider'    => true,
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<p>You can add, edit and delete sleep modes via <strong>WP Travel Engine > Settings > Extensions > Advanced Itinerary Builder.</strong></p>', 'wp-travel-engine' ),
				],
			),
			array(
				'divider' => true,
				'field'   => [
					'name'             => 'itineraries',
					'type'             => 'ITINERARIES',
					'isAdvancedActive' => $isAdvancedActive,
					'sleepModeOptions' => $sleepModeOptions,
				],
			),
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Itinerary Downloader', 'wp-travel-engine' ),
				],
			),
			array(
				'visibility' => ! $isItineraryDownloaderActive,
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<strong>NOTE:</strong> Want travellers to download the tour details in PDF format & read later? <a href="https://wptravelengine.com/plugins/itinerary-downloader/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro" target="_blank">Get Itinerary Downloader extension now</a>', 'wp-travel-engine' ),
				],
			),
			array(
				'visibility' => $isItineraryDownloaderActive,
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<strong>NOTE:</strong> Want travellers to download the tour details in PDF format and read later? You can configure Itinerary Downloader via <b>WP Travel Engine &gt; Settings &gt; Extensions &gt; Itinerary Downloader', 'wp-travel-engine' ),
				],
			),
		),
	),
	'wpte-include-exclude' => array(
		'tab_label'         => __( 'Includes/Excludes', 'wp-travel-engine' ),
		'tab_heading'       => __( 'Includes/Excludes', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/includes-excludes.php',
		'callback_function' => 'wpte_edit_trip_tab_include_exclude',
		'content_key'       => 'wpte-include-exclude',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 40,
		'icon'              => 'flag',
		'fields'            => array(
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'cost_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Includes/Excludes', 'wp-travel-engine' ),
				],
			),
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Cost Includes', 'wp-travel-engine' ),
				],
			),
			array(
				'label' => __( 'Cost Includes Title', 'wp-travel-engine' ),
				'field' => [
					'name'        => 'cost_includes_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Cost Includes', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'List Of Services', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'cost_includes',
					'type'        => 'TEXTAREA',
					'placeholder' => __( 'List of services that are included.', 'wp-travel-engine' ),
					'split'       => true,
					'rows'        => '5',
				],
			),
			array(
				'field' => [
					'type'  => 'TITLE',
					'title' => __( 'Cost Excludes', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'Cost Excludes Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'cost_excludes_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Cost Excludes', 'wp-travel-engine' ),
				],
			),
			array(
				'label' => __( 'List Of Services', 'wp-travel-engine' ),
				'field' => [
					'name'        => 'cost_excludes',
					'type'        => 'TEXTAREA',
					'placeholder' => __( 'List of services that are excluded.', 'wp-travel-engine' ),
					'split'       => true,
					'rows'        => '5',
				],
			),
		),
	),
	'wpte-facts'           => array(
		'tab_label'         => esc_html__( 'Trip Info', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Trip Info', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/trip-facts.php',
		'callback_function' => 'wpte_edit_trip_tab_facts',
		'content_key'       => 'wpte-facts',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 50,
		'icon'              => 'map',
		'fields'            => array(
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'trip_info_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Trip Info', 'wp-travel-engine' ),
				],
			),
			array(
				'label'       => __( 'Trip Info Selection', 'wp-travel-engine' ),
				'description' => __( 'Select the trip fact title and click on add fact button to enter trip fact data.', 'wp-travel-engine' ),
				'field'       => [
					'name'    => 'trip_info',
					'type'    => 'TRIP_FACTS',
					'options' => $trip_facts,
				],
			),
		),
	),
	'wpte-gallery'         => array(
		'tab_label'         => esc_html__( 'Gallery', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Gallery', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/gallery.php',
		'callback_function' => 'wpte_edit_trip_tab_gallery',
		'content_key'       => 'wpte-gallery',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 70,
		'icon'              => 'image',
		'fields'            => array(
			array(
				'label' => __( 'Enable Image Gallery', 'wp-travel-engine' ),
				'field' => [
					'name' => 'gallery_enable',
					'type' => 'SWITCH',
				],
			),
			array(
				'label'       => true,
				'description' => __( 'Max. file size 5MB Supports: JPG,PNG,WebP images', 'wp-travel-engine' ),
				'condition'   => 'gallery_enable == true',
				'field'       => [
					'name'       => 'gallery',
					'type'       => 'IMAGE_GALLERY',
					'isMultiple' => true,
					'fileTypes'  => [ 'image' ],
					'className'  => 'wpte-media-uploader-field',
				],
			),
			array(
				'field' => [
					'type' => 'DIVIDER',
				],
			),
			array(
				'label' => __( 'Enable Video Gallery', 'wp-travel-engine' ),
				'field' => [
					'name' => 'video_gallery_enable',
					'type' => 'SWITCH',
				],
			),
			array(
				'label'     => true,
				'condition' => 'video_gallery_enable == true',
				'field'     => [
					'name' => 'video_gallery',
					'type' => 'VIDEO_GALLERY',
				],
			),
		),
	),
	'wpte-map'             => array(
		'tab_label'         => esc_html__( 'Map', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Map', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/map.php',
		'callback_function' => 'wpte_edit_trip_tab_map',
		'content_key'       => 'wpte-map',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 80,
		'icon'              => 'marker',
		'fields'            => array(
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'map_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Enter map title', 'wp-travel-engine' ),
				],
			),
			array(
				'label'   => __( 'Map Image', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'      => 'trip_map.images[0]',
					'type'      => 'IMAGE_GALLERY',
					'icon'      => 'upload',
					'fileTypes' => [ 'image' ],
				],
			),
			array(
				'label'   => __( 'Map iframe Code', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name' => 'trip_map.iframe',
					'type' => 'TEXTAREA',
					'rows' => 5,
				],
			),
		),
	),
	'wpte-faqs'            => array(
		'tab_label'         => esc_html__( 'FAQs', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'FAQs', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/faqs.php',
		'callback_function' => 'wpte_edit_trip_tab_faqs',
		'content_key'       => 'wpte-faqs',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 90,
		'icon'              => 'message',
		'fields'            => array(
			array(
				'label'   => __( 'Section Title', 'wp-travel-engine' ),
				'divider' => true,
				'field'   => [
					'name'        => 'faqs_title',
					'type'        => 'TEXT',
					'placeholder' => __( 'Enter FAQs Title', 'wp-travel-engine' ),
				],
			),
			array(
				'field' => [
					'name' => 'faqs',
					'type' => 'FAQS',
				],
			),
		),
	),
	'wte_file_downloads'   => array(
		'tab_label'         => esc_html__( 'File Downloads', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'File Downloads', 'wp-travel-engine' ),
		'content_path'      => apply_filters( 'wte_trip_file_downloads_setting_path', WP_TRAVEL_ENGINE_BASE_PATH . '/admin/meta-parts/trip-tabs/file-downloads.php' ),
		'callback_function' => 'wpte_edit_trip_tab_wte_file_downloads',
		'content_key'       => 'wte_file_downloads',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 100,
		'icon'              => 'download',
		'fields'            => array(
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<p>Want to provide downloadable files such as brochures, guidebooks, offline maps, etc? File Downloads extension allows you to upload files in various formats that can be downloaded by travellers. <a href="https://wptravelengine.com/plugins/file-downloads/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro" target="_blank">Get File Downloads extension now</a></p>', 'wp-travel-engine' ),
				],
				'visibility' => ! $isFileDownloadsActive,
			),
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<p>You can add, edit and delete the global files via <strong>WP Travel Engine > Settings > Extensions > File Downloads.</strong> <a href="' . admin_url() . 'edit.php?post_type=booking&page=class-wp-travel-engine-admin.php" target="_blank">Go To Settings</a></p>', 'wp-travel-engine' ),
				],
				'visibility' => $isFileDownloadsActive,
			),
			array(
				'field'      => [
					'name'        => 'file_downloads',
					'type'        => 'FILE_DOWNLOADS',
					'globalFiles' => $files,
				],
				'visibility' => $isFileDownloadsActive,
			),
		),
	),
	'wte-exta-services'    => array(
		'tab_label'         => esc_html__( 'Extra Services', 'wp-travel-engine' ),
		'tab_heading'       => esc_html__( 'Extra Services', 'wp-travel-engine' ),
		'content_path'      => plugin_dir_path( __FILE__ ) . '/trip-tabs/extra-services.php',
		'callback_function' => 'wpte_edit_trip_tab_extra_services_upsell',
		'content_key'       => 'wte-exta-services',
		'current'           => false,
		'content_loaded'    => false,
		'priority'          => 110,
		'icon'              => 'grid',
		'fields'            => array(
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( '<p><strong>NOTE:</strong> Do you want to provide additional services such as supplementary room, hotel upgrade, airport pick and drop, etc? Extra Services extension allows you to create add-on services and sell more to your customer. <a href="https://wptravelengine.com/plugins/extra-services/?utm_source=free_plugin&utm_medium=pro_addon&utm_campaign=upgrade_to_pro" target="_blank">Get Extra Services extension now</a></p>', 'wp-travel-engine' ),
				],
				'visibility' => ! $isExtraServicesActive,
			),
			array(
				'field'      => [
					'type'    => 'ALERT',
					'content' => __( "<p><strong>NOTE:</strong> You can add, edit and delete the global extra services via <strong>WP Travel Engine > Extra Services</strong>.</p>", 'wp-travel-engine' ),
				],
				'visibility' => $isExtraServicesActive,
			),
			array(
				'label'       => __( 'Section Extra Service', 'wp-travel-engine' ),
				'description' => __( 'Choose and select the global Extra Service.', 'wp-travel-engine' ),
				'divider'     => true,
				'field'       => [
					'type'    => 'EXTRA_SERVICES',
					'name'    => 'trip_extra_services',
					'options' => $extra_services,
				],
				'visibility'  => $isExtraServicesActive,
			),
		),
	),
	'wte-trip-shortcodes'  => array(
		'tab_label'    => esc_html__( 'Shortcodes', 'wp-travel-engine' ),
		'tab_heading'  => esc_html__( 'Shortcodes', 'wp-travel-engine' ),
		'content_path' => plugin_dir_path( __FILE__ ) . '/trip-tabs/shortcodes.php',
		'content_key'  => 'wte-trip-shortcodes',
		'icon'         => 'code',
		'priority'     => 120,
		'fields'       => array(
			array(
				'field'      => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display fixed starting dates in page/post use the following <strong>Shortcode.</strong>', 'wp-travel-engine' ),
					'code'  => "[WTE_Fixed_Starting_Dates id='{$id}']",
				],
				'visibility' => $isFSDActive,
			),
			array(
				'divider'    => true,
				'field'      => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display fixed starting dates in theme/template, please use below <strong>PHP Funtion.</strong>', 'wp-travel-engine' ),
					'code'  => "<?php echo do_shortcode('[WTE_Fixed_Starting_Dates id={$id}]'); ?>",
				],
				'visibility' => $isFSDActive,
			),
			array(
				'visibility' => $isItineraryDownloaderActive,
				'divider'    => true,
				'field'      => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display Itinerary Downloader in current trip, please use below <strong>Shortcode.</strong>', 'wp-travel-engine' ),
					'code'  => "[wte_itinerary_downloader]",
				],
			),
			array(
				'field' => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display Trip Info of this trip in posts/pages/tabs, please use following <strong>Shortcode.</strong>', 'wp-travel-engine' ),
					'code'  => "[Trip_Info_Shortcode id='{$id}']",
				],
			),
			array(
				'divider' => true,
				'field'   => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display Trip Info in templates, please use below <strong>PHP Funtion.</strong>', 'wp-travel-engine' ),
					'code'  => "<?php echo do_shortcode('[Trip_Info_Shortcode id={$id}]'); ?>",
				],
			),
			array(
				'field' => [
					'type'  => 'SHORTCODE',
					'title' => __( '<p>To display Video Gallery of this trip in posts/pages/tabs/templates, please use following <strong>Shortcode.</strong> <br/>Additional attributes are: type=\'popup/slider\' title=\'\' label=\'\', where type displays either a popup or slider layout, defaults popup layout.</p>', 'wp-travel-engine' ),
					'code'  => "[wte_video_gallery trip_id='{$id}']",
				],
			),
			array(
				'divider' => true,
				'field'   => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display Tour Map of this tour in posts/pages/tabs/widgets use the following <strong>Shortcode.</strong>', 'wp-travel-engine' ),
					'code'  => "[wte_trip_map id='{$id}']",
				],
			),
			array(
				'divider' => false,
				'field'   => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display Tour Map of this tour in posts/pages/tabs/widgets, please use below <strong>PHP Funtion.</strong>', 'wp-travel-engine' ),
					'code'  => "<?php echo do_shortcode('[wte_trip_map id={$id}]'); ?>",
				],
			),
			array(
				'field'      => [
					'type'  => 'SHORTCODE',
					'title' => __( 'To display downloadable file list in posts/pages/tabs and widget, please use following <strong>Shortcode.</strong>', 'wp-travel-engine' ),
					'code'  => "[trip_file_downloads trip_id='{$id}']",
				],
				'visibility' => $isFileDownloadsActive,
			),
		),
	),
);
// Apply filter hooks.
$trip_meta_tabs = apply_filters( 'wp_travel_engine_admin_trip_meta_tabs', $trip_meta_tabs );

// Load Tabs.
wptravelengine_tabs_ui()
	->init( array(
		'id'          => 'wptravelengine-edit-trip',
		'class'       => 'wptravelengine-edit-trip',
		'content_key' => 'wpte_edit_trip_tabs',
	) )
	->single_trip_metabox_template( wp_travel_engine_sort_array_by_priority( $trip_meta_tabs ) );
