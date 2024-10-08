<?php
/**
 * Traveler Category Model.
 *
 * @package WPTravelEngine
 * @since 6.0.0
 */

namespace WPTravelEngine\Core\Models\Post;

#[\AllowDynamicProperties]
/**
 * Class TravelerCategory.
 * This class represents a traveler category to the WP Travel Engine plugin.
 *
 * @since 6.0.0
 */
class TravelerCategory {

	/**
	 * The trip object.
	 *
	 * @var Trip
	 */
	protected Trip $trip;

	/**
	 * The package object.
	 *
	 * @var TripPackage
	 */
	protected TripPackage $package;

	/**
	 * The traveler category price.
	 *
	 * @var float|string
	 */
	public $price;

	/**
	 * The traveler category sale price.
	 *
	 * @var float|string
	 */
	public $sale_price;

	/**
	 * Traveler Category Model Constructor.
	 *
	 * @param Trip $trip The trip object.
	 * @param TripPackage $package The trip package object.
	 * @param array $package_category_data The package category data.
	 */
	public function __construct( Trip $trip, TripPackage $package, array $package_category_data ) {
		$this->trip    = $trip;
		$this->package = $package;

		$key_mapping = array(
			'c_ids'         => 'id',
			'labels'        => 'label',
			'prices'        => 'price',
			'pricing_types' => 'pricing_type',
			'sale_prices'   => 'sale_price',
			'min_paxes'     => 'min_pax',
			'max_paxes'     => 'max_pax',
			'enabled_sale'  => 'has_sale',
		);

		foreach ( $package_category_data as $property => $value ) {
			if ( isset( $key_mapping[ $property ] ) ) {
				$mapped_property = $key_mapping[ $property ];
				if ( in_array( $property, [ 'prices', 'sale_prices' ], true ) ) {
					$value = is_numeric( $value ) ? max( 0, (float) $value ) : '';
				}
				$this->{$mapped_property} = $value;
				continue;
			}
			$this->{$property} = $value;
		}
	}

	/**
	 * Get category value.
	 *
	 * @param mixed $key The key to get.
	 * @param mixed $default The default value to return if the key is not set.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		switch ( $key ) {
			case 'group_pricing':
				$value = $this->package->get_group_pricing()[ $this->{'id'} ] ?? [];
				break;
			default:
				$value = $this->{$key} ?? $default;
		}

		return $value;
	}

	/**
	 * Calculate Sale Percentage.
	 *
	 * @return float
	 */
	public function sale_percentage(): float {
		return ( ! $this->price ) ? 0 : ( ( $this->price - $this->sale_price ) / $this->price ) * 100;
	}

	/**
	 * Get the traveler category actual price.
	 *
	 * @return float|string
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * Get the traveler category sale price.
	 *
	 * @return float|string
	 */
	public function get_sale_price() {
		return $this->sale_price;
	}
}
