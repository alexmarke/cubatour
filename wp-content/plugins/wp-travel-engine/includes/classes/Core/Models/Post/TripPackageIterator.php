<?php
/**
 * Class TripPackageIterator.
 *
 * @package WPTravelEngine\Models
 * @since 6.0.0
 */

namespace WPTravelEngine\Core\Models\Post;

use WPTravelEngine\Abstracts\Iterator;

/**
 * Class TripPackageIterator.
 *
 * @package WPTravelEngine\Models
 * @since 6.0.0
 */
class TripPackageIterator extends Iterator {

	/**
	 * The trip object.
	 *
	 * @var Trip
	 */
	protected Trip $trip;

	/**
	 * The data array.
	 *
	 * @var array
	 */
	protected array $packages = array();

	/**
	 * Iterator Constructor.
	 *
	 * @param Trip $trip The trip object.
	 *
	 * @since 6.1.2
	 */
	public function __construct( Trip $trip ) {
		$this->trip = $trip;

		$package_ids = (array) $this->trip->get_meta( 'packages_ids' );

		foreach ( $package_ids as $package_id ) {
			if ( is_numeric( $package_id ) ) {
				$package = get_post( $package_id );
				if ( $package ) {
					$this->packages[] = new TripPackage( $package_id, $this->trip );
				}
			}
		}

		parent::__construct( $this->packages );
	}

}
