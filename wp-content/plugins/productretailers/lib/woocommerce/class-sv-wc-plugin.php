<?php


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'EM_WC_Plugin' ) ) :

abstract class EM_WC_Plugin {

	/** Plugin Framework Version */
	const VERSION = '2.0.2';

	/** @var string plugin id */
	private $id;

	/** @var string plugin text domain */
	protected $text_domain;

	/** @var string version number */
	private $version;

	/** @var string plugin path without trailing slash */
	private $plugin_path;

	/** @var string plugin uri */
	private $plugin_url;

	/** @var \WC_Logger instance */
	private $logger;

	/** @var  \SV_WP_Admin_Message_Handler instance */
	private $message_handler;

	/** @var array string names of required PHP extensions */
	private $dependencies = array();

	/** @var boolean whether a dismissible notice has been rendered */
	private $dismissible_notice_rendered = false;


	/**
	 * Initialize the plugin
	 *
	 * Optional args:
	 *
	 * + `dependencies` - array string names of required PHP extensions
	 *
	 * Child plugin classes may add their own optional arguments
	 *
	 * @since 2.0
	 * @param string $id plugin id
	 * @param string $version plugin version number
	 * @param string $text_domain the plugin text domain
	 * @param array $args optional plugin arguments
	 */
	public function __construct( $id, $version, $text_domain, $args = array() ) {

		// required params
		$this->id          = $id;
		$this->version     = $version;
		$this->text_domain = $text_domain;

		if ( isset( $args['dependencies'] ) )       $this->dependencies = $args['dependencies'];

		// include library files after woocommerce is loaded
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'lib_includes' ) );

		// includes that are required to be available at all times
		$this->includes();

		// Admin
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

			// admin message handler
			require_once( 'class-sv-wp-admin-message-handler.php' );

			// render any admin notices
			add_action( 'admin_notices', array( $this, 'render_admin_notices'               ), 10 );
			add_action( 'admin_notices', array( $this, 'render_admin_dismissible_notice_js' ), 15 );

			// run every time
			$this->do_install();
		}

		// AJAX handler to dismiss any warning/error notices
		add_action( 'wp_ajax_wc_plugin_framework_' . $this->get_id() . '_dismiss_message', array( $this, 'handle_dismiss_message' ) );

		// Load translation files
		add_action( 'init', array( $this, 'load_translation' ) );
	}


	/**
	 * Load plugin text domain.  This implementation should look simply like:
	 *
	 * *load_plugin_textdomain*( 'text-domain-string', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	 *
	 * *'s used to avoid errors from stupid Codestyling Localization
	 *
	 * Note that the actual text domain string should be used, and not a
	 * variable or constant, otherwise localization plugins (Codestyling) will
	 * not be able to detect the localization directory.
	 *
	 * @since 1.0
	 */
	abstract public function load_translation();


	/**
	 * Include required library files
	 *
	 * @since 2.0
	 */
	public function lib_includes() {
		// stub method
	}


	/**
	 * Include any critical files which must be available as early as possible
	 *
	 * @since 2.0
	 */
	private function includes() {
		require_once( 'class-sv-wc-plugin-compatibility.php' );
	}


	/** Admin methods ******************************************************/


	/**
	 * Returns true if on the admin plugin settings page, if any
	 *
	 * @since 2.0
	 * @return boolean true if on the admin plugin settings page
	 */
	public function is_plugin_settings() {
		// optional method, not all plugins *have* a settings page
		return false;
	}


	/**
	 * Checks if required PHP extensions are loaded and adds an admin notice
	 * for any missing extensions.  Also plugin settings can be checked
	 * as well.
	 *
	 * @since 2.0
	 */
	public function render_admin_notices() {

		// notices for any missing dependencies
		$this->render_dependencies_admin_notices();
	}


	/**
	 * Checks if required PHP extensions are not loaded and adds a dismissible admin
	 * notice if so.  Notice will not be rendered to the admin user once dismissed
	 * unless on the plugin settings page, if any
	 *
	 * @since 2.0
	 * @see EM_WC_Plugin::render_admin_notices()
	 */
	protected function render_dependencies_admin_notices() {

		// report any missing extensions
		$missing_extensions = $this->get_missing_dependencies();

		if ( count( $missing_extensions ) > 0 && ( ! $this->is_message_dismissed( 'missing-extensions' ) || $this->is_plugin_settings() ) ) {

			$message = sprintf(
				_n(
					'%s requires the %s PHP extension to function.  Contact your host or server administrator to configure and install the missing extension.',
					'%s requires the following PHP extensions to function: %s.  Contact your host or server administrator to configure and install the missing extensions.',
					count( $missing_extensions ),
					$this->text_domain
				),
				$this->get_plugin_name(),
				'<strong>' . implode( ', ', $missing_extensions ) . '</strong>'
			);

			$this->add_dismissible_notice( $message, 'missing-extensions' );

		}
	}


	/**
	 * Adds the given $message as a dismissible notice identified by $message_id
	 *
	 * @since 2.0
	 */
	public function add_dismissible_notice( $message, $message_id ) {

		// dismiss link unless we're on the plugin settings page, in which case we'll always display the notice
		$dismiss_link = sprintf( '<a href="#" class="js-wc-plugin-framework-%s-message-dismiss" data-message-id="%s">%s</a>', $this->get_id(), $message_id, __( 'Dismiss', $this->text_domain ) );

		if ( $this->is_plugin_settings() ) {
			$dismiss_link = '';
		}

		echo sprintf( '<div class="error"><p>%s %s</p></div>', $message, $dismiss_link );

		$this->dismissible_notice_rendered = true;
	}


	/**
	 * Render the javascript to handle the notice "dismiss" functionality
	 *
	 * @since 2.0
	 */
	public function render_admin_dismissible_notice_js() {

		// if a notice was rendered, add the javascript code to handle the notice dismiss action
		if ( ! $this->dismissible_notice_rendered ) {
			return;
		}

		ob_start();
		?>
		// hide notice
		$( 'a.js-wc-plugin-framework-<?php echo $this->get_id(); ?>-message-dismiss' ).click( function() {

			$.get(
				ajaxurl,
				{
					action: 'wc_plugin_framework_<?php echo $this->get_id(); ?>_dismiss_message',
					messageid: $( this ).data( 'message-id' )
				}
			);

			$( this ).closest( 'div.error' ).fadeOut();

			return false;
		} );
		<?php
		$javascript = ob_get_clean();

		EM_WC_Plugin_Compatibility::wc_enqueue_js( $javascript );
	}

	/**
	 * Dismiss the identified message
	 *
	 * @since 2.0
	 */
	public function handle_dismiss_message() {

		$this->dismiss_message( $_REQUEST['messageid'] );

	}


	/** Helper methods ******************************************************/


	/**
	 * Gets the string name of any required PHP extensions that are not loaded
	 *
	 * @since 2.0
	 * @return array of missing dependencies
	 */
	public function get_missing_dependencies() {

		$missing_extensions = array();

		foreach ( $this->get_dependencies() as $ext ) {

			if ( ! extension_loaded( $ext ) ) {
				$missing_extensions[] = $ext;
			}
		}

		return $missing_extensions;
	}


	/**
	 * Saves errors or messages to WooCommerce Log (woocommerce/logs/plugin-id-xxx.txt)
	 *
	 * @since 2.0
	 * @param string $message error or message to save to log
	 * @param string $log_id optional log id to segment the files by, defaults to plugin id
	 */
	public function log( $message, $log_id = null ) {

		if ( is_null( $log_id ) ) {
			$log_id = $this->get_id();
		}

		if ( ! is_object( $this->logger ) ) {
			$this->logger = EM_WC_Plugin_Compatibility::new_wc_logger();
		}

		$this->logger->add( $log_id, $message );

	}


	/**
	 * Marks the identified admin message as dismissed for the given user
	 *
	 * @since 2.0
	 * @param string $message_id the message identifier
	 * @param int $user_id optional user identifier, defaults to current user
	 * @return boolean true if the message has been dismissed by the admin user
	 */
	protected function dismiss_message( $message_id, $user_id = null ) {

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$dismissed_messages = get_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_id() . '_dismissed_messages', true );

		$dismissed_messages[ $message_id ] = true;

		update_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_id() . '_dismissed_messages', $dismissed_messages );

		do_action( 'wc_' . $this->get_id(). '_dismiss_message', $message_id, $user_id );
	}


	/**
	 * Returns true if the identified admin message has been dismissed for the
	 * given user
	 *
	 * @since 2.0
	 * @param string $message_id the message identifier
	 * @param int $user_id optional user identifier, defaults to current user
	 * @return boolean true if the message has been dismissed by the admin user
	 */
	protected function is_message_dismissed( $message_id, $user_id = null ) {

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$dismissed_messages = get_user_meta( $user_id, '_wc_plugin_framework_' . $this->get_id() . '_dismissed_messages', true );

		return isset( $dismissed_messages[ $message_id ] ) && $dismissed_messages[ $message_id ];
	}


	/** Getter methods ******************************************************/


	/**
	 * The implementation for this abstract method should simply be:
	 *
	 * return __FILE__;
	 *
	 * @since 2.0
	 * @return string the full path and filename of the plugin file
	 */
	abstract protected function get_file();


	/**
	 * Returns the plugin id
	 *
	 * @since 2.0
	 * @return string plugin id
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Returns the plugin id with dashes in place of underscores, and
	 * appropriate for use in frontend element names, classes and ids
	 *
	 * @since 2.0
	 * @return string plugin id with dashes in place of underscores
	 */
	public function get_id_dasherized() {
		return str_replace( '_', '-', $this->get_id() );
	}


	/**
	 * Returns the plugin full name including "WooCommerce", ie
	 * "WooCommerce X".  This method is defined abstract for localization purposes
	 *
	 * @since 2.0
	 * @return string plugin name
	 */
	abstract public function get_plugin_name();


	/**
	 * Returns the plugin version name.  Defaults to wc_{plugin id}_version
	 *
	 * @since 2.0
	 * @return string the plugin version name
	 */
	protected function get_plugin_version_name() {
		return 'wc_' . $this->get_id() . '_version';
	}


	/**
	 * Returns the current version of the plugin
	 *
	 * @since 2.0
	 * @return string plugin version
	 */
	public function get_version() {
		return $this->version;
	}


	/**
	 * Get the PHP dependencies for extension depending on the gateway being used
	 *
	 * @since 2.0
	 * @return array of required PHP extension names, based on the gateway in use
	 */
	protected function get_dependencies() {
		return $this->dependencies;
	}


	/**
	 * Returns the "Configure" plugin action link to go directly to the plugin
	 * settings page (if any)
	 *
	 * @since 2.0
	 * @see EM_WC_Plugin::get_settings_url()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin configure link
	 */
	public function get_settings_link( $plugin_id = null ) {

		$settings_url = $this->get_settings_url( $plugin_id );

		if ( $settings_url ) {
			return sprintf( '<a href="%s">%s</a>', $settings_url, __( 'Configure', $this->text_domain ) );
		}

		// no settings
		return '';
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 2.0
	 * @see EM_WC_Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {

		// stub method
		return '';
	}


	/**
	 * Gets the plugin documentation url, which defaults to:
	 * http://docs.woothemes.com/document/woocommerce-{dasherized plugin id}/
	 *
	 * @since 2.0
	 * @return string documentation URL
	 */
	public function get_documentation_url() {

		return 'http://docs.woothemes.com/document/woocommerce-' . $this->get_id_dasherized() . '/';
	}


	/**
	 * Gets the plugin review URL, which defaults to:
	 * {product page url}#review_form
	 *
	 * @since 2.0
	 * @return string review url
	 */
	public function get_review_url() {

		return $this->get_product_page_url() . '#review_form';
	}


	/**
	 * Gets the emediaexperts.com product page URL, which defaults to:
	 * http://www.emediaexperts.com/product/{dasherized plugin id}/
	 *
	 * @since 2.0
	 * @return string emediaexperts.com product page url
	 */
	public function get_product_page_url() {

		return 'http://www.emediaexperts.com/product/' . $this->get_id_dasherized() . '/';
	}


	/**
	 * Returns the plugin's path without a trailing slash, i.e.
	 * /path/to/wp-content/plugins/plugin-directory
	 *
	 * @since 2.0
	 * @return string the plugin path
	 */
	public function get_plugin_path() {

		if ( $this->plugin_path ) {
			return $this->plugin_path;
		}

		return $this->plugin_path = untrailingslashit( plugin_dir_path( $this->get_file() ) );
	}


	/**
	 * Returns the plugin's url without a trailing slash, i.e.
	 * http://emediaexperts.com/wp-content/plugins/plugin-directory
	 *
	 * @since 2.0
	 * @return string the plugin URL
	 */
	public function get_plugin_url() {

		if ( $this->plugin_url ) {
			return $this->plugin_url;
		}

		return $this->plugin_url = untrailingslashit( plugins_url( '/', $this->get_file() ) );
	}


	/**
	 * Returns the woocommerce uploads path, sans trailing slash.  Oddly WooCommerce
	 * core does not provide a way to get this
	 *
	 * @since 2.0
	 * @return string upload path for woocommerce
	 */
	public static function get_woocommerce_uploads_path() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/woocommerce_uploads';
	}


	/**
	 * Returns the relative path to the framework image directory, with a
	 * trailing slash
	 *
	 * @since 2.0
	 * @return string relative path to framework image directory
	 */
	public function get_framework_image_path() {
		return 'lib/emediaexperts/woocommerce/assets/images/';
	}


	/**
	 * Returns the WP Admin Message Handler instance for use with
	 * setting/displaying admin messages & errors
	 *
	 * @since 2.0
	 * @return SV_WP_Admin_Message_Handler
	 */
	public function get_message_handler() {

		if ( is_object( $this->message_handler ) ) {

			return $this->message_handler;
		}

		return $this->message_handler = new SV_WP_Admin_Message_Handler( $this->get_id() );
	}


	/**
	 * Helper function to determine whether a plugin is active
	 *
	 * @since 2.0
	 * @param string $plugin_name the plugin name, as the plugin-dir/plugin-class.php
	 * @return boolean true if the named plugin is installed and active
	 */
	public function is_plugin_active( $plugin_name ) {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( $plugin_name, $active_plugins ) || array_key_exists( $plugin_name, $active_plugins );

	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Handles version checking
	 *
	 * @since 2.0
	 */
	protected function do_install() {

		$installed_version = get_option( $this->get_plugin_version_name() );

		// installed version lower than plugin version?
		if ( version_compare( $installed_version, $this->get_version(), '<' ) ) {

			if ( ! $installed_version ) {
				$this->install();
			} else {
				$this->upgrade( $installed_version );
			}

			// new version number
			update_option( $this->get_plugin_version_name(), $this->get_version() );
		}

	}


	/**
	 * Plugin install method.  Perform any installation tasks here
	 *
	 * @since 2.0
	 */
	protected function install() {
		// stub
	}


	/**
	 * Plugin upgrade method.  Perform any required upgrades here
	 *
	 * @since 2.0
	 * @param string $installed_version the currently installed version
	 */
	protected function upgrade( $installed_version ) {
		// stub
	}


}

endif; // Class exists check
