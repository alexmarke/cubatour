<?php
/**
 * Plugin Settings Model.
 *
 * @package WPTravelEngine/Core/Models
 * @since 6.0.0
 */

namespace WPTravelEngine\Core\Models\Settings;

use WP_Term;
use WPTravelEngine\Traits\Factory;

/**
 * Class PluginSettings.
 *
 * @since 6.0.0
 */
class PluginSettings extends BaseSetting {

	use Factory;

	/**
	 * Constructor to set the option name and optional default settings.
	 */
	public function __construct() {
		parent::__construct( 'wp_travel_engine_settings', array() );
	}

	/**
	 * Insert default traveler categories.F
	 *
	 * @return array
	 */
	protected function insert_default_traveler_categories(): array {
		$pricing_taxonomy = 'trip-packages-categories';
		$terms            = array();

		$terms[] = (object) wp_insert_term( 'Adult', $pricing_taxonomy, array( 'slug' => 'adult' ) );
		$terms[] = (object) wp_insert_term( 'Child', $pricing_taxonomy, array( 'slug' => 'child' ) );

		Options::update( 'primary_pricing_category', $terms[ 0 ]->term_id );

		return $terms;
	}

	/**
	 * Get traveler categories, creates terms if not exists.
	 *
	 * @return WP_Term[]
	 */
	public function get_traveler_categories( array $args = array() ): array {
		$default = array( 'taxonomy' => 'trip-packages-categories', 'hide_empty' => false );
		$terms   = get_terms( wp_parse_args( $args, $default ) );
		if ( empty( $terms ) ) {
			$terms = array_map(
				function ( $term ) {
					return get_term( $term->term_id, 'trip-packages-categories' );
				},
				$this->insert_default_traveler_categories()
			);
		}

		return $terms;
	}

	/**
	 * Get the primary pricing category.
	 *
	 * @return WP_Term
	 */
	public function get_primary_pricing_category(): WP_Term {

		$category = get_term(
			Options::get( 'primary_pricing_category' ),
			'trip-packages-categories'
		);

		if ( $category instanceof WP_Term ) {
			return $category;
		}

		$terms = $this->get_traveler_categories();

		return $terms[ 0 ];
	}
}
