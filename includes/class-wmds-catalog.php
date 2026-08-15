<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a site builder can place: shortcodes, blocks and the dashboard widget.
 *
 * One catalogue the Usage tab, the About tab and the dashboard widget all
 * read from, so a shortcode added in one place cannot be missing from the
 * documentation.
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
				'description' => __( 'Renders a list or grid of vehicles from the synced inventory.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicles posts_per_page="12" layout="grid" filters="yes" pagination="yes"]',
			),
			array(
				'tag'         => 'vehicle-filter',
				'title'       => __( 'Vehicle filter', 'wp-mobile-de-sync' ),
				'description' => __( 'Filter controls for the inventory. Use alone or above a vehicle list.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-filter layout="sidebar" facets="make,price,fuel"]',
			),
			array(
				'tag'         => 'vehicle-count',
				'title'       => __( 'Vehicle count', 'wp-mobile-de-sync' ),
				'description' => __( 'How many vehicles the current filters leave, or the whole inventory.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-count] · [vehicle-count filtered="no"]',
			),
			array(
				'tag'         => 'vehicle-enquiry',
				'title'       => __( 'Vehicle enquiry', 'wp-mobile-de-sync' ),
				'description' => __( 'A form that asks about a vehicle. Defaults to the current vehicle.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-enquiry] · [vehicle-enquiry vehicle="123" heading="Ask us"]',
			),
			array(
				'tag'         => 'vehicle-logo',
				'title'       => __( 'Manufacturer logo', 'wp-mobile-de-sync' ),
				'description' => __( 'The logo for a make, or for the current vehicle when used in the loop.', 'wp-mobile-de-sync' ),
				'example'     => '[vehicle-logo make="Audi"]',
			),
			array(
				'tag'         => 'emission-sticker',
				'title'       => __( 'Emission sticker', 'wp-mobile-de-sync' ),
				'description' => __( 'The German emission sticker image for a key or the current vehicle.', 'wp-mobile-de-sync' ),
				'example'     => '[emission-sticker] · [emission-sticker key="green"]',
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
				'description' => __( 'Shows the newest synced vehicles and the current sync status. Configure the count and sort order via the widget\'s Configure link.', 'wp-mobile-de-sync' ),
			),
		);
	}
}
