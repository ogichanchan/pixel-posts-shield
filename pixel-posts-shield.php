<?php
/**
 * Plugin Name: Pixel Posts Shield
 * Plugin URI: https://github.com/ogichanchan/pixel-posts-shield
 * Description: A unique PHP-only WordPress utility. A pixel style posts plugin acting as a shield. Focused on simplicity and efficiency.
 * Version: 1.0.0
 * Author: ogichanchan
 * Author URI: https://github.com/ogichanchan
 * License: GPLv2 or later
 * Text Domain: pixel-posts-shield
 */

// Deny direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pixel_Posts_Shield class.
 *
 * This class handles all functionality for the Pixel Posts Shield plugin.
 * It provides a global setting for a pixel shield effect on posts,
 * per-post overrides, a custom column in the post list, and inline frontend styling.
 * All CSS and JS are generated inline.
 */
class Pixel_Posts_Shield {

	/**
	 * Constructor.
	 * Initializes the plugin by registering necessary hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_head', array( $this, 'admin_inline_styles' ) );
		add_action( 'wp_head', array( $this, 'frontend_inline_styles' ) );

		// Custom columns for posts and pages.
		add_filter( 'manage_posts_columns', array( $this, 'add_shield_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'display_shield_column_content' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'add_shield_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'display_shield_column_content' ), 10, 2 );

		// Meta box for per-post override.
		add_action( 'add_meta_boxes', array( $this, 'add_shield_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_shield_meta_box_data' ) );
	}

	/**
	 * Adds the plugin's settings page to the WordPress admin menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			esc_html__( 'Pixel Posts Shield Settings', 'pixel-posts-shield' ),
			esc_html__( 'Pixel Shield', 'pixel-posts-shield' ),
			'manage_options',
			'pixel-posts-shield',
			array( $this, 'settings_page_html' )
		);
	}

	/**
	 * Registers the plugin's settings.
	 */
	public function register_settings() {
		register_setting(
			'pixel-posts-shield-settings-group',
			'pps_global_shield_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'absint', // Ensure 0 or 1.
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'pixel-posts-shield-settings-group',
			'pps_shield_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => '#ff0000',
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'pps_main_settings_section',
			esc_html__( 'Global Shield Settings', 'pixel-posts-shield' ),
			array( $this, 'main_settings_section_callback' ),
			'pixel-posts-shield'
		);

		add_settings_field(
			'pps_global_shield_enabled_field',
			esc_html__( 'Enable Global Pixel Shield', 'pixel-posts-shield' ),
			array( $this, 'global_shield_enabled_callback' ),
			'pixel-posts-shield',
			'pps_main_settings_section'
		);

		add_settings_field(
			'pps_shield_color_field',
			esc_html__( 'Shield Effect Color', 'pixel-posts-shield' ),
			array( $this, 'shield_color_callback' ),
			'pixel-posts-shield',
			'pps_main_settings_section'
		);
	}

	/**
	 * Sanitize a hex color value.
	 *
	 * @param string $color The color value to sanitize.
	 * @return string The sanitized color value.
	 */
	public function sanitize_hex_color( $color ) {
		$color = sanitize_hex_color( $color );
		return $color ?: '#ff0000'; // Fallback to default if invalid.
	}

	/**
	 * Callback for the main settings section.
	 */
	public function main_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the global settings for the Pixel Posts Shield.', 'pixel-posts-shield' ) . '</p>';
	}

	/**
	 * Callback for the 'Enable Global Pixel Shield' setting field.
	 */
	public function global_shield_enabled_callback() {
		$enabled = get_option( 'pps_global_shield_enabled', 0 );
		?>
		<label for="pps_global_shield_enabled">
			<input type="checkbox" id="pps_global_shield_enabled" name="pps_global_shield_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
			<?php esc_html_e( 'Check to enable the pixel shield effect on all supported posts/pages by default.', 'pixel-posts-shield' ); ?>
		</label>
		<?php
	}

	/**
	 * Callback for the 'Shield Effect Color' setting field.
	 */
	public function shield_color_callback() {
		$color = get_option( 'pps_shield_color', '#ff0000' );
		?>
		<input type="text" id="pps_shield_color" name="pps_shield_color" value="<?php echo esc_attr( $color ); ?>" class="pps-color-field" data-default-color="#ff0000" />
		<p class="description">
			<?php esc_html_e( 'Enter a hexadecimal color code (e.g., #FF0000) for the shield effect.', 'pixel-posts-shield' ); ?>
		</p>
		<?php
	}

	/**
	 * Generates the HTML for the plugin's settings page.
	 */
	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pixel-posts-shield-settings-group' );
				do_settings_sections( 'pixel-posts-shield' );
				submit_button( esc_html__( 'Save Changes', 'pixel-posts-shield' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds a custom column to the posts and pages list tables.
	 *
	 * @param array $columns The array of existing columns.
	 * @return array The modified array of columns.
	 */
	public function add_shield_column( $columns ) {
		$columns['pps_shield_status'] = esc_html__( 'Shield Status', 'pixel-posts-shield' );
		return $columns;
	}

	/**
	 * Displays the content for the custom 'Shield Status' column.
	 *
	 * @param string $column_name The name of the current column.
	 * @param int    $post_id     The ID of the current post.
	 */
	public function display_shield_column_content( $column_name, $post_id ) {
		if ( 'pps_shield_status' === $column_name ) {
			$is_shielded = $this->is_post_shielded( $post_id );

			if ( $is_shielded ) {
				$shield_color_display = esc_attr( get_option( 'pps_shield_color', '#ff0000' ) );
				echo '<span class="pps-shield-icon active" style="background-color:' . $shield_color_display . ';" title="' . esc_attr__( 'Pixel Shield Active', 'pixel-posts-shield' ) . '"></span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'Active', 'pixel-posts-shield' ) . '</span>';
			} else {
				echo '<span class="pps-shield-icon inactive" title="' . esc_attr__( 'Pixel Shield Inactive', 'pixel-posts-shield' ) . '"></span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'Inactive', 'pixel-posts-shield' ) . '</span>';
			}
		}
	}

	/**
	 * Adds the Pixel Shield meta box to posts and pages.
	 */
	public function add_shield_meta_box() {
		$post_types = array( 'post', 'page' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'pps_shield_meta_box',
				esc_html__( 'Pixel Shield Control', 'pixel-posts-shield' ),
				array( $this, 'render_shield_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Renders the content of the Pixel Shield meta box.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_shield_meta_box( $post ) {
		// Add a nonce field for security.
		wp_nonce_field( 'pps_shield_meta_box_nonce', 'pps_shield_meta_box_nonce_field' );

		$is_globally_enabled = (bool) get_option( 'pps_global_shield_enabled', 0 );
		// '_pps_override_shield' value of '1' means the override is active.
		$override_active     = get_post_meta( $post->ID, '_pps_override_shield', true );

		$label_text = '';
		$is_checked = false; // Whether the checkbox should be checked.

		if ( $is_globally_enabled ) {
			// Global shield is ON. The meta box allows DISABLING it for this post.
			$label_text = esc_html__( 'Override: Disable Pixel Shield for this post.', 'pixel-posts-shield' );
			$is_checked = ( '1' === $override_active ); // Checked if user wants to disable.
		} else {
			// Global shield is OFF. The meta box allows ENABLING it for this post.
			$label_text = esc_html__( 'Override: Enable Pixel Shield for this post.', 'pixel-posts-shield' );
			$is_checked = ( '1' === $override_active ); // Checked if user wants to enable.
		}
		?>
		<p>
			<?php echo esc_html__( 'Current Global Status: ', 'pixel-posts-shield' ); ?>
			<strong><?php echo $is_globally_enabled ? esc_html__( 'Enabled', 'pixel-posts-shield' ) : esc_html__( 'Disabled', 'pixel-posts-shield' ); ?></strong>
		</p>
		<label for="pps_override_shield">
			<input type="checkbox" id="pps_override_shield" name="pps_override_shield" value="1" <?php checked( $is_checked, true ); ?> />
			<?php echo esc_html( $label_text ); ?>
		</label>
		<?php
	}

	/**
	 * Saves the data from the Pixel Shield meta box.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_shield_meta_box_data( $post_id ) {
		// Check if our nonce is set and verify it.
		if ( ! isset( $_POST['pps_shield_meta_box_nonce_field'] ) || ! wp_verify_nonce( $_POST['pps_shield_meta_box_nonce_field'], 'pps_shield_meta_box_nonce' ) ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		// Check if it's an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Sanitize and save the meta box data.
		$new_value = isset( $_POST['pps_override_shield'] ) ? '1' : '0';
		update_post_meta( $post_id, '_pps_override_shield', $new_value );
	}

	/**
	 * Determines if a given post should display the pixel shield effect.
	 *
	 * @param int $post_id The ID of the post.
	 * @return bool True if the post should be shielded, false otherwise.
	 */
	private function is_post_shielded( $post_id ) {
		$is_globally_enabled = (bool) get_option( 'pps_global_shield_enabled', 0 );
		// '_pps_override_shield' value of '1' means the override is active for this post.
		$override_active     = get_post_meta( $post_id, '_pps_override_shield', true );

		if ( $is_globally_enabled ) {
			// Global is ON. If override_active is '1', it means DISABLE for this post.
			return ( '1' !== $override_active );
		} else {
			// Global is OFF. If override_active is '1', it means ENABLE for this post.
			return ( '1' === $override_active );
		}
	}

	/**
	 * Injects inline CSS for the admin area (e.g., for custom column icons).
	 */
	public function admin_inline_styles() {
		?>
		<style type="text/css">
			/* Pixel Posts Shield Admin Styles */
			.column-pps_shield_status {
				width: 80px;
				text-align: center;
			}
			.pps-shield-icon {
				display: inline-block;
				width: 16px;
				height: 16px;
				border: 1px solid #ccc;
				vertical-align: middle;
				background-repeat: no-repeat;
				background-position: center center;
				background-size: cover;
				box-shadow: 1px 1px 0 rgba(0,0,0,0.1);
				box-sizing: border-box; /* Include padding and border in the element's total width and height */
			}
			.pps-shield-icon.active {
				/* Simple pixel-like design */
				background-image:
					repeating-linear-gradient(45deg, rgba(255,255,255,0.2) 0 2px, transparent 2px 4px),
					repeating-linear-gradient(-45deg, rgba(255,255,255,0.2) 0 2px, transparent 2px 4px);
				background-size: 8px 8px;
				background-color: var(--pps-shield-color, <?php echo esc_attr( get_option( 'pps_shield_color', '#ff0000' ) ); ?>); /* Use actual setting for preview */
				border-color: #5cb85c;
			}
			.pps-shield-icon.inactive {
				background-color: #f0f0f0;
				border-color: #ddd;
				box-shadow: none;
			}
			/* Basic styling for color field, simulating a basic color input */
			.pps-color-field {
				border: 1px solid #ccc;
				padding: 5px;
				width: 100px;
				font-family: monospace;
			}
		</style>
		<?php
	}

	/**
	 * Injects inline CSS for the frontend to display the pixel shield effect.
	 */
	public function frontend_inline_styles() {
		// Only apply on singular posts or pages.
		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		if ( $this->is_post_shielded( $post_id ) ) {
			$shield_color = get_option( 'pps_shield_color', '#ff0000' );
			$sanitized_color = sanitize_hex_color( $shield_color );
			if ( empty( $sanitized_color ) ) {
				$sanitized_color = '#ff0000'; // Fallback to default if sanitized color is empty.
			}

			// Target a common article element or main content div.
			// Using ::before pseudo-element for a non-intrusive overlay.
			// The .single-post.post-ID selector ensures specificity.
			$css = "
            body.single-post.post-{$post_id} article,
            body.page.page-id-{$post_id} article {
                position: relative;
                z-index: 1; /* Ensure our pseudo-element has context */
            }
            body.single-post.post-{$post_id} article::before,
            body.page.page-id-{$post_id} article::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: {$sanitized_color};
                background-image:
                    repeating-linear-gradient(45deg, rgba(255,255,255,0.1) 0 2px, transparent 2px 4px),
                    repeating-linear-gradient(-45deg, rgba(255,255,255,0.1) 0 2px, transparent 2px 4px);
                background-size: 8px 8px; /* Size of the pixel squares */
                opacity: 0.15; /* Make it subtle */
                pointer-events: none; /* Allow interaction with content below */
                z-index: 2; /* Layer above the article content but below popups */
            }
            ";
			echo '<style type="text/css" id="pixel-posts-shield-frontend-styles">' . wp_strip_all_tags( $css ) . '</style>';
		}
	}
}

// Instantiate the plugin class.
add_action( 'plugins_loaded', function() {
	new Pixel_Posts_Shield();
} );