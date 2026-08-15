<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes, blocks and the dashboard widget — one list the Usage tab reads.
 */
class WMDS_Catalog {
	/**
	 * @return array<int, array{tag:string,title:string,description:string,example:string}>
	 */
	public static function shortcodes() {
		return array(
			array(
				'tag'         => 'vehicles',
				'title'       => __( 'Vehicle list', 'wp-mobile-de-sync' ),
				'description' => __( 'A list or grid from the synced inventory.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicles posts_per_page="12" layout="grid" filters="yes"]',
			),
			array(
				'tag'         => 'vehicle-filter',
				'title'       => __( 'Vehicle filter', 'wp-mobile-de-sync' ),
				'description' => __( 'Filter controls for the inventory.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-filter layout="sidebar" facets="make,price,fuel"]',
			),
			array(
				'tag'         => 'vehicle-count',
				'title'       => __( 'Vehicle count', 'wp-mobile-de-sync' ),
				'description' => __( 'How many vehicles match the current filters.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-count]',
			),
			array(
				'tag'         => 'vehicle-enquiry',
				'title'       => __( 'Vehicle enquiry', 'wp-mobile-de-sync' ),
				'description' => __( 'Enquiry form for the current vehicle.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-enquiry]',
			),
			array(
				'tag'         => 'vehicle-logo',
				'title'       => __( 'Manufacturer logo', 'wp-mobile-de-sync' ),
				'description' => __( 'Logo for a make, or for the vehicle in the loop.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-logo make="Audi"]',
			),
			array(
				'tag'         => 'emission-sticker',
				'title'       => __( 'Emission sticker', 'wp-mobile-de-sync' ),
				'description' => __( 'German emission sticker for the current vehicle.', 'wp-mobile-de-sync' ),
				'example'     => '[emission-sticker]',
			),
		);
	}

	/**
	 * @return array<int, array{name:string,title:string,description:string}>
	 */
	public static function blocks() {
		$out = array();

		foreach ( WMDS_Blocks::definitions() as $name => $definition ) {
			$out[] = array(
				'name'        => $name,
				'title'       => $definition['title'],
				'description' => $definition['description'],
			);
		}

		return $out;
	}

	/**
	 * @return array<int, array{id:string,title:string,where:string,description:string}>
	 */
	public static function widgets() {
		return array(
			array(
				'id'          => WMDS_Dashboard::WIDGET,
				'title'       => __( 'mobile.de — newest vehicles', 'wp-mobile-de-sync' ),
				'where'       => __( 'WordPress dashboard', 'wp-mobile-de-sync' ),
				'description' => __( 'Newest synced vehicles and the current sync status.', 'wp-mobile-de-sync' ),
			),
		);
	}
}
