<?php
	/**
	 * Plugin Name: Freemius Ninja Forms Demo
	 * Plugin URI:  http://freemius.com/
	 * Description: Adds the menu and submenu slugs.
	 * Version:     1.0.0
	 * Author:      Freemius
	 * Author URI:  http://freemius.com
	 * License: GPL2
	 */

	/**
	 * @package     Freemius Menu Slugger
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	function fs_ninja_add_settings_page() {
		$hook = add_menu_page(
			'Settings',
			'Ninja Upgrade',
			'manage_options',
			'fs-manual-optin',
			'fs_ninja_render_settings_page'
		);
	}

	function fs_ninja_render_settings_page() {
		ninja_forms_actions();

		?>
		<h1>Manual Opt-in</h1>
		<h3>Current version: <?php echo nf_plugin_version( '' ) ?></h3>
		<?php if ( '2.9' === nf_plugin_version( '' ) ) : ?>
			<form action="" method="post">
				<input type="hidden" name="ninja_action" value="upgrade">
				<button class="button button-primary">Upgrade Ninja Forms</button>
			</form>
		<?php else : ?>
			<form action="" method="post">
				<input type="hidden" name="ninja_action" value="downgrade">
				<button class="button">Downgrade Ninja Forms</button>
			</form>
		<?php endif ?>
		<?php if ( nf_is_freemius_on() && nf_fs()->is_registered() ) : ?>
			<br>
			<form action="" method="post">
				<input type="hidden" name="ninja_action" value="opt_out">
				<button class="button">Opt-out from Freemius</button>
			</form>
		<?php endif ?>
	<?php
	}

	add_action( 'admin_menu', 'fs_ninja_add_settings_page' );

	function nf_plugin_version( $version ) {
		return get_option( 'ninja_forms_version', '2.9' );
	}

// Create a helper function for easy SDK access.
	function nf_fs() {
		global $nf_fs;

		if ( ! isset( $nf_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			$nf_fs = fs_dynamic_init( array(
				'slug'           => 'ninja-forms',
				'id'             => '209',
				'public_key'     => 'pk_f2f84038951d45fc8e4ff9747da6d',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => false,
				'menu'           => array(
					'slug'    => 'ninja-forms',
					'account' => false,
					'support' => false,
					'contact' => false,
				),
			) );
		}

		return $nf_fs;
	}

	function nf_is_freemius_on() {
		return get_option( 'ninja_forms_freemius', 0 );
	}

	function nf_override_plugin_version() {
		// Init Freemius and override plugin's version.
		if ( ! has_filter( 'fs_plugin_version_ninja-forms' ) ) {
			add_filter( 'fs_plugin_version_ninja-forms', 'nf_plugin_version' );
		}
	}

	function nf_uninstall_cleanup() {
		// Your uninstall script.
	}

	if ( nf_is_freemius_on() ) {
		// Override plugin's version, should be executed before Freemius init.
		nf_override_plugin_version();

		// Init Freemius.
		nf_fs();

		nf_fs()->add_action( 'after_uninstall', 'nf_uninstall_cleanup' );
	} else {
		register_uninstall_hook( __FILE__, 'nf_uninstall_cleanup' );
	}

	function ninja_forms_actions() {
		if ( empty( $_POST['ninja_action'] ) || ! in_array( $_POST['ninja_action'], array(
				'upgrade',
				'downgrade',
				'opt_out'
			) )
		) {
			return;
		}

		switch ( $_POST['ninja_action'] ) {
			case 'upgrade':
				update_option( 'ninja_forms_version', '3.0' );
				// Turn Freemius on.
				update_option( 'ninja_forms_freemius', 1 );

				nf_override_plugin_version();

				if ( ! nf_fs()->is_registered() && nf_fs()->has_api_connectivity() ) {
					if ( nf_fs()->opt_in() ) {
						// Successful opt-in into Freemius.
					}
				} else if ( nf_fs()->is_registered() ) {
					// Send immediate re-upgrade event.
					nf_fs()->_run_sync_install();
				}
				break;

			case 'downgrade':
				update_option( 'ninja_forms_version', '2.9' );

				if ( nf_fs()->is_registered() ) {
					// Send immediate downgrade event.
					nf_fs()->_run_sync_install();
				}
				break;

			case 'opt_out':
				nf_fs()->delete_account_event();

				// Turn Freemius off.
				update_option( 'ninja_forms_freemius', 0 );
				break;
		}
	}
