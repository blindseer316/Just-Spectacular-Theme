<?php
/**
 * Just Spectacular Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JST_VERSION', '1.5.3' );


/**
 * Theme setup.
 */
function jst_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor-style.css' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'just-spectacular-theme' ),
		)
	);

	add_post_type_support( 'post', 'page-attributes' );
}
add_action( 'after_setup_theme', 'jst_setup' );

/**
 * Enqueue styles.
 */
function jst_scripts() {
	if ( is_singular() && get_post_meta( get_the_ID(), '_jst_disable_theme_style', true ) ) {
		return;
	}
	wp_enqueue_style( 'jst-style', get_stylesheet_uri(), array(), JST_VERSION );
}
add_action( 'wp_enqueue_scripts', 'jst_scripts' );

/**
 * Admin-only JS/CSS: quick-paste <style>/<script> buttons and a fix
 * that stops Ctrl/Cmd+Z inside meta box / theme options text fields
 * from triggering the block editor's global undo instead of the
 * field's own native undo stack.
 */
function jst_admin_scripts( $hook ) {
	$allowed = array( 'post.php', 'post-new.php', 'appearance_page_jst-theme-options', 'edit.php' );
	$is_jst_part_list = ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'jst_part' === $_GET['post_type'] );
	if ( ! in_array( $hook, $allowed, true ) || ( 'edit.php' === $hook && ! $is_jst_part_list ) ) {
		return;
	}
	wp_enqueue_script( 'jst-admin', get_template_directory_uri() . '/js/admin.js', array(), JST_VERSION, true );
	wp_enqueue_style( 'jst-admin', get_template_directory_uri() . '/css/admin.css', array(), JST_VERSION );
}
add_action( 'admin_enqueue_scripts', 'jst_admin_scripts' );

/**
 * Default nav menu fallback.
 *
 * Renders the registered "primary" menu location (Appearance > Menus).
 * Until a menu is assigned there, falls back to a list of current pages.
 * Only shown when the Theme Options "Navigation" box is empty, so a
 * custom pasted nav always takes priority.
 */
function jst_default_nav_fallback() {
	if ( get_option( 'jst_navigation', '' ) ) {
		return;
	}

	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => 'nav',
			'container_class' => 'jst-default-nav',
			'menu_class'     => 'jst-default-nav__list',
			'fallback_cb'    => 'jst_default_nav_pages_fallback',
		)
	);
}

/**
 * Fallback used by wp_nav_menu() when no menu is assigned to the
 * "primary" location yet — lists current pages.
 */
function jst_default_nav_pages_fallback() {
	echo '<nav class="jst-default-nav"><ul class="jst-default-nav__list">';
	wp_list_pages(
		array(
			'title_li' => '',
		)
	);
	echo '</ul></nav>';
}

/**
 * ------------------------------------------------------------------
 * Theme Options page (Appearance -> Theme Options)
 * ------------------------------------------------------------------
 */

function jst_register_theme_options_page() {
	add_theme_page(
		__( 'Theme Options', 'just-spectacular-theme' ),
		__( 'Theme Options', 'just-spectacular-theme' ),
		'manage_options',
		'jst-theme-options',
		'jst_render_theme_options_page'
	);
}
add_action( 'admin_menu', 'jst_register_theme_options_page' );

function jst_theme_options_fields() {
	return array(
		'jst_navigation'     => array(
			'label'       => __( 'Header Nav / Menu', 'just-spectacular-theme' ),
			'description' => __( 'Outputs at the very start of <body> via wp_body_open — use for your global header and navigation markup.', 'just-spectacular-theme' ),
		),
		'jst_footer'         => array(
			'label'       => __( 'Footer HTML', 'just-spectacular-theme' ),
			'description' => __( 'Outputs after the page content, before Footer Scripts — use for your global footer design and navigation markup.', 'just-spectacular-theme' ),
		),
		'jst_header_scripts' => array(
			'label'       => __( 'Header Scripts', 'just-spectacular-theme' ),
			'description' => __( 'Outputs inside <head> — use for external CSS links, fonts, and other head-level scripts.', 'just-spectacular-theme' ),
		),
		'jst_footer_scripts' => array(
			'label'       => __( 'Footer Scripts', 'just-spectacular-theme' ),
			'description' => __( 'Outputs before </body>, after Footer HTML — use for JavaScript, analytics, and tracking scripts.', 'just-spectacular-theme' ),
		),
	);
}

function jst_render_theme_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['jst_theme_options_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['jst_theme_options_nonce'] ), 'jst_save_theme_options' ) ) {
		foreach ( array_keys( jst_theme_options_fields() ) as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				// Admin-only trust context: raw HTML/script paste, intentionally not sanitized.
				update_option( $field, wp_unslash( $_POST[ $field ] ) );
			} else {
				update_option( $field, '' );
			}
		}
		update_option( 'jst_disable_tailwind_prose', isset( $_POST['jst_disable_tailwind_prose'] ) ? '1' : '' );
		echo '<div class="updated"><p>' . esc_html__( 'Theme options saved.', 'just-spectacular-theme' ) . '</p></div>';
	}

	$fields        = jst_theme_options_fields();
	$disable_prose = get_option( 'jst_disable_tailwind_prose', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Theme Options', 'just-spectacular-theme' ); ?></h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'jst_save_theme_options', 'jst_theme_options_nonce' ); ?>
			<?php foreach ( $fields as $field_id => $field ) : ?>
				<h2><?php echo esc_html( $field['label'] ); ?></h2>
				<p>
					<button type="button" class="button jst-quick-tag-btn" data-target="<?php echo esc_attr( $field_id ); ?>" data-tag="style"><?php esc_html_e( 'Insert <style>', 'just-spectacular-theme' ); ?></button>
					<button type="button" class="button jst-quick-tag-btn" data-target="<?php echo esc_attr( $field_id ); ?>" data-tag="script"><?php esc_html_e( 'Insert <script>', 'just-spectacular-theme' ); ?></button>
					<button type="button" class="button jst-quick-tag-btn" data-target="<?php echo esc_attr( $field_id ); ?>" data-tag="comment"><?php esc_html_e( 'Insert <!-- -->', 'just-spectacular-theme' ); ?></button>
				</p>
				<p>
					<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>" rows="14" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo get_option( $field_id, '' ); // phpcs:ignore -- intentionally unescaped raw HTML/script storage. ?></textarea>
				</p>
				<p><span class="description"><?php echo esc_html( $field['description'] ); ?></span></p>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Content Styling', 'just-spectacular-theme' ); ?></h2>
			<p>
				<label>
					<input type="checkbox" name="jst_disable_tailwind_prose" value="1" <?php checked( $disable_prose, '1' ); ?> />
					<?php esc_html_e( 'Disable Tailwind "prose" class on post/page content', 'just-spectacular-theme' ); ?>
				</label>
				<br>
				<span class="description">
					<?php esc_html_e( 'The "prose" class is added to post/page content by default (requires the Tailwind Typography plugin loaded via Header Scripts). Check this box to remove it sitewide.', 'just-spectacular-theme' ); ?>
				</span>
			</p>

			<?php submit_button( __( 'Save Options', 'just-spectacular-theme' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Returns the content wrapper class, including "prose" unless disabled
 * via Theme Options.
 */
function jst_content_class( $extra = '' ) {
	$classes = array();

	if ( $extra ) {
		$classes[] = $extra;
	}

	if ( ! get_option( 'jst_disable_tailwind_prose', '' ) ) {
		// max-w-none strips Tailwind Typography's own width cap so the
		// per-page Width setting on the outer container is what governs
		// content width, not the "prose" class.
		$classes[] = 'prose max-w-none';

		if ( is_singular() && get_post_meta( get_the_ID(), '_jst_prose_invert', true ) ) {
			$classes[] = 'prose-invert';
		}
	}

	return esc_attr( implode( ' ', $classes ) );
}

/**
 * Output Header Scripts in wp_head.
 */
function jst_output_header_scripts() {
	echo get_option( 'jst_header_scripts', '' ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'wp_head', 'jst_output_header_scripts' );

/**
 * Output Navigation markup at wp_body_open.
 */
function jst_output_navigation() {
	echo get_option( 'jst_navigation', '' ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'wp_body_open', 'jst_output_navigation' );

/**
 * Output Footer HTML markup right before </body>, before footer scripts.
 */
function jst_output_footer() {
	echo get_option( 'jst_footer', '' ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'jst_before_closing_body', 'jst_output_footer', 10 );

/**
 * Output Footer Scripts right before </body>, after Footer HTML.
 */
function jst_output_footer_scripts() {
	echo get_option( 'jst_footer_scripts', '' ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'jst_before_closing_body', 'jst_output_footer_scripts', 20 );

/**
 * ------------------------------------------------------------------
 * Per-page meta box: "Page Settings"
 * ------------------------------------------------------------------
 */

function jst_add_page_settings_meta_box() {
	$post_types = get_post_types( array( 'public' => true ) );

	foreach ( $post_types as $post_type ) {
		if ( 'attachment' === $post_type ) {
			continue;
		}

		add_meta_box(
			'jst_page_settings',
			__( 'Page Settings', 'just-spectacular-theme' ),
			'jst_render_page_settings_meta_box',
			$post_type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'jst_add_page_settings_meta_box' );

function jst_render_page_settings_meta_box( $post ) {
	wp_nonce_field( 'jst_save_page_settings', 'jst_page_settings_nonce' );

	$width           = get_post_meta( $post->ID, '_jst_page_width', true );
	$header_code     = get_post_meta( $post->ID, '_jst_page_header_code', true );
	$footer_code     = get_post_meta( $post->ID, '_jst_page_footer_code', true );
	$disable_style   = get_post_meta( $post->ID, '_jst_disable_theme_style', true );
	$hide_post_meta  = get_post_meta( $post->ID, '_jst_hide_post_meta', true );
	$prose_invert    = get_post_meta( $post->ID, '_jst_prose_invert', true );
	?>
	<p>
		<label for="jst_page_width"><strong><?php esc_html_e( 'Width', 'just-spectacular-theme' ); ?></strong></label><br>
		<input type="text" id="jst_page_width" name="jst_page_width" value="<?php echo esc_attr( $width ); ?>" placeholder="80rem" style="width:100%;max-width:300px;" />
		<br>
		<span class="description">
			<?php esc_html_e( 'Max content width, used by the Default, Plain, and Full Width templates. Accepts any valid CSS value (e.g. 80rem, 1200px, 100%, none). Defaults to 80rem (100% on Full Width) if left blank.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<p>
		<label for="jst_page_header_code"><strong><?php esc_html_e( 'Page Header Code', 'just-spectacular-theme' ); ?></strong></label><br>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="style"><?php esc_html_e( 'Insert <style>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="script"><?php esc_html_e( 'Insert <script>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="comment"><?php esc_html_e( 'Insert <!-- -->', 'just-spectacular-theme' ); ?></button>
		<br>
		<textarea id="jst_page_header_code" name="jst_page_header_code" rows="8" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $header_code ); ?></textarea>
		<br>
		<span class="description">
			<?php esc_html_e( 'Runs in addition to the global Header Scripts (Appearance > Theme Options), not instead of.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<p>
		<label for="jst_page_footer_code"><strong><?php esc_html_e( 'Page Footer Code', 'just-spectacular-theme' ); ?></strong></label><br>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_footer_code" data-tag="style"><?php esc_html_e( 'Insert <style>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_footer_code" data-tag="script"><?php esc_html_e( 'Insert <script>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_footer_code" data-tag="comment"><?php esc_html_e( 'Insert <!-- -->', 'just-spectacular-theme' ); ?></button>
		<br>
		<textarea id="jst_page_footer_code" name="jst_page_footer_code" rows="8" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $footer_code ); ?></textarea>
		<br>
		<span class="description">
			<?php esc_html_e( 'Runs in addition to the global Footer box (Appearance > Theme Options), not instead of.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_prose_invert" value="1" <?php checked( $prose_invert, '1' ); ?> />
			<?php esc_html_e( 'Prose invert (dark background)', 'just-spectacular-theme' ); ?>
		</label>
		<br>
		<span class="description">
			<?php esc_html_e( 'Adds "prose-invert" to the content class — flips prose text/heading/link colors to light variants for use on dark backgrounds.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_hide_post_meta" value="1" <?php checked( $hide_post_meta, '1' ); ?> />
			<?php esc_html_e( 'Hide post meta', 'just-spectacular-theme' ); ?>
		</label>
		<br>
		<span class="description">
			<?php esc_html_e( 'Hides the date/author meta line on the Full Width — With Title template.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_disable_theme_style" value="1" <?php checked( $disable_style, '1' ); ?> />
			<?php esc_html_e( 'Disable theme style.css on this page', 'just-spectacular-theme' ); ?>
		</label>
		<br>
		<span class="description">
			<?php esc_html_e( 'When checked, the theme\'s style.css (nav fallback, headings, cards, etc.) is not loaded on this page/post — useful for fully custom-built pages.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<?php
}

function jst_save_page_settings_meta_box( $post_id ) {
	if ( ! isset( $_POST['jst_page_settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['jst_page_settings_nonce'] ), 'jst_save_page_settings' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['jst_page_width'] ) ) {
		update_post_meta( $post_id, '_jst_page_width', sanitize_text_field( wp_unslash( $_POST['jst_page_width'] ) ) );
	}

	if ( isset( $_POST['jst_page_header_code'] ) ) {
		// Admin-only trust context: raw HTML/script paste, intentionally not sanitized.
		update_post_meta( $post_id, '_jst_page_header_code', wp_unslash( $_POST['jst_page_header_code'] ) );
	}

	if ( isset( $_POST['jst_page_footer_code'] ) ) {
		// Admin-only trust context: raw HTML/script paste, intentionally not sanitized.
		update_post_meta( $post_id, '_jst_page_footer_code', wp_unslash( $_POST['jst_page_footer_code'] ) );
	}

	update_post_meta( $post_id, '_jst_prose_invert', isset( $_POST['jst_prose_invert'] ) ? '1' : '' );
	update_post_meta( $post_id, '_jst_hide_post_meta', isset( $_POST['jst_hide_post_meta'] ) ? '1' : '' );
	update_post_meta( $post_id, '_jst_disable_theme_style', isset( $_POST['jst_disable_theme_style'] ) ? '1' : '' );
}
add_action( 'save_post', 'jst_save_page_settings_meta_box' );

/**
 * Output per-page additive header/footer code.
 */
function jst_output_page_header_code() {
	if ( is_singular() ) {
		echo get_post_meta( get_the_ID(), '_jst_page_header_code', true ); // phpcs:ignore -- intentional raw output, admin-trusted.
	}
}
add_action( 'wp_head', 'jst_output_page_header_code' );

function jst_output_page_footer_code() {
	if ( is_singular() ) {
		echo get_post_meta( get_the_ID(), '_jst_page_footer_code', true ); // phpcs:ignore -- intentional raw output, admin-trusted.
	}
}
add_action( 'jst_before_closing_body', 'jst_output_page_footer_code' );

/**
 * Simple breadcrumb trail: Home > Current.
 */
function jst_breadcrumbs() {
	echo '<nav class="jst-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'just-spectacular-theme' ) . '">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'just-spectacular-theme' ) . '</a>';

	if ( is_home() ) {
		echo ' <span class="jst-breadcrumbs__sep">/</span> ' . esc_html( get_the_title( get_option( 'page_for_posts' ) ) );
	} elseif ( is_category() || is_tag() || is_tax() || is_archive() ) {
		echo ' <span class="jst-breadcrumbs__sep">/</span> ' . wp_strip_all_tags( get_the_archive_title() );
	} elseif ( is_singular() ) {
		echo ' <span class="jst-breadcrumbs__sep">/</span> ' . esc_html( get_the_title() );
	} elseif ( is_search() ) {
		echo ' <span class="jst-breadcrumbs__sep">/</span> ' . esc_html__( 'Search Results', 'just-spectacular-theme' );
	}

	echo '</nav>';
}

/**
 * Hero band used on the index template: "Welcome to [Site Name]" on the
 * front page, breadcrumbs + contextual title everywhere else index.php
 * is used (blog posts page, category/tag/archive fallback).
 */
function jst_index_hero() {
	?>
	<div class="jst-hero">
		<div class="jst-container">
			<?php if ( is_front_page() ) : ?>
				<h1 class="jst-hero__title">
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__( 'Welcome to %s', 'just-spectacular-theme' ),
						esc_html( get_bloginfo( 'name' ) )
					);
					?>
				</h1>
			<?php else : ?>
				<?php jst_breadcrumbs(); ?>
				<h1 class="jst-hero__title">
					<?php
					if ( is_home() ) {
						echo esc_html( get_the_title( get_option( 'page_for_posts' ) ) );
					} elseif ( is_category() || is_tag() || is_tax() || is_archive() ) {
						the_archive_title( '', '' );
					}
					?>
				</h1>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Hero-style title band for the default page template: breadcrumbs +
 * page title, same visual treatment as the index hero.
 */
function jst_page_hero() {
	?>
	<div class="jst-hero">
		<div class="jst-container">
			<?php jst_breadcrumbs(); ?>
			<h1 class="jst-hero__title"><?php the_title(); ?></h1>
		</div>
	</div>
	<?php
}

/**
 * Helper: get the configured page width with fallback default.
 */
function jst_get_page_width( $post_id = null, $default = '80rem' ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	$width = get_post_meta( $post_id, '_jst_page_width', true );
	return $width ? $width : $default;
}

/**
 * ------------------------------------------------------------------
 * Template Parts CPT  (jst_part)
 * ------------------------------------------------------------------
 *
 * Admin-only library of reusable HTML snippets. Each part is inserted
 * into a page's Custom HTML block via [jst_part name="slug-here"].
 *
 * REST is enabled so parts are readable/editable through MCP tools
 * (Royal MCP / EasyMCP) the same way other CPTs are.
 * ------------------------------------------------------------------
 */

function jst_register_part_cpt() {
	register_post_type(
		'jst_part',
		array(
			'labels'              => array(
				'name'               => __( 'Template Parts', 'just-spectacular-theme' ),
				'singular_name'      => __( 'Template Part', 'just-spectacular-theme' ),
				'add_new'            => __( 'Add New Part', 'just-spectacular-theme' ),
				'add_new_item'       => __( 'Add New Template Part', 'just-spectacular-theme' ),
				'edit_item'          => __( 'Edit Template Part', 'just-spectacular-theme' ),
				'all_items'          => __( 'All Template Parts', 'just-spectacular-theme' ),
				'search_items'       => __( 'Search Template Parts', 'just-spectacular-theme' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-layout',
			'supports'            => array( 'title' ),
			'show_in_rest'        => true,
			'rest_base'           => 'jst-parts',
			'capability_type'     => 'post',
			'rewrite'             => false,
			'query_var'           => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'jst_register_part_cpt' );

/**
 * Tag taxonomy for Template Parts (organizational only — optional on each part).
 */
function jst_register_part_taxonomy() {
	register_taxonomy(
		'jst_part_tag',
		'jst_part',
		array(
			'labels'            => array(
				'name'          => __( 'Part Tags', 'just-spectacular-theme' ),
				'singular_name' => __( 'Part Tag', 'just-spectacular-theme' ),
				'add_new_item'  => __( 'Add New Tag', 'just-spectacular-theme' ),
				'new_item_name' => __( 'New Tag Name', 'just-spectacular-theme' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'rest_base'         => 'jst-part-tags',
			'show_admin_column' => true,
			'rewrite'           => false,
			'query_var'         => false,
		)
	);
}
add_action( 'init', 'jst_register_part_taxonomy' );

/**
 * Register the _jst_part_name and _jst_part_html meta fields for REST
 * so MCP tools can read and update them.
 */
function jst_register_part_meta() {
	register_post_meta(
		'jst_part',
		'_jst_part_name',
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'default'      => '',
		)
	);

	register_post_meta(
		'jst_part',
		'_jst_part_html',
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'default'      => '',
		)
	);
}
add_action( 'init', 'jst_register_part_meta' );

/**
 * Meta box: Part Name + HTML content + quick-paste buttons.
 */
function jst_add_part_meta_box() {
	add_meta_box(
		'jst_part_content',
		__( 'Part Content', 'just-spectacular-theme' ),
		'jst_render_part_meta_box',
		'jst_part',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_jst_part', 'jst_add_part_meta_box' );

function jst_render_part_meta_box( $post ) {
	wp_nonce_field( 'jst_save_part', 'jst_part_nonce' );

	$part_name = get_post_meta( $post->ID, '_jst_part_name', true );
	$part_html = get_post_meta( $post->ID, '_jst_part_html', true );

	$shortcode_preview = $part_name
		? '[jst_part name="' . esc_attr( $part_name ) . '"]'
		: __( '(set a Part Name below to generate the shortcode)', 'just-spectacular-theme' );
	?>
	<p>
		<label for="jst_part_name"><strong><?php esc_html_e( 'Part Name', 'just-spectacular-theme' ); ?></strong></label><br>
		<input type="text" id="jst_part_name" name="jst_part_name" value="<?php echo esc_attr( $part_name ); ?>" placeholder="trust-strip" style="width:100%;max-width:400px;" />
		<br>
		<span class="description">
			<?php esc_html_e( 'Lowercase, hyphens only. Used in the shortcode: [jst_part name="your-name-here"]. Must be unique across all Template Parts.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<?php if ( $part_name ) : ?>
	<p>
		<strong><?php esc_html_e( 'Shortcode', 'just-spectacular-theme' ); ?></strong><br>
		<code id="jst_shortcode_preview" style="background:#f0f0f1;padding:4px 8px;border-radius:3px;"><?php echo esc_html( $shortcode_preview ); ?></code>
		<button type="button" class="button jst-quick-tag-btn" id="jst_copy_shortcode" style="margin-left:6px;"><?php esc_html_e( 'Copy', 'just-spectacular-theme' ); ?></button>
	</p>
	<?php endif; ?>
	<p>
		<label for="jst_part_html"><strong><?php esc_html_e( 'HTML Content', 'just-spectacular-theme' ); ?></strong></label><br>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_part_html" data-tag="style"><?php esc_html_e( 'Insert <style>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_part_html" data-tag="script"><?php esc_html_e( 'Insert <script>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_part_html" data-tag="comment"><?php esc_html_e( 'Insert <!-- -->', 'just-spectacular-theme' ); ?></button>
		<br>
		<textarea id="jst_part_html" name="jst_part_html" rows="20" class="jst-metabox-field" style="width:100%;font-family:monospace;margin-top:4px;"><?php echo $part_html; // phpcs:ignore -- intentionally unescaped raw HTML storage. ?></textarea>
		<br>
		<span class="description">
			<?php esc_html_e( 'Paste the full HTML for this reusable section. Output raw on the front end — no sanitization. Admin-trusted.', 'just-spectacular-theme' ); ?>
		</span>
	</p>
	<?php
}

function jst_save_part_meta_box( $post_id ) {
	if ( ! isset( $_POST['jst_part_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['jst_part_nonce'] ), 'jst_save_part' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['jst_part_name'] ) ) {
		update_post_meta( $post_id, '_jst_part_name', sanitize_title( wp_unslash( $_POST['jst_part_name'] ) ) );
	}

	if ( isset( $_POST['jst_part_html'] ) ) {
		// Admin-only trust context: raw HTML paste, intentionally not sanitized.
		update_post_meta( $post_id, '_jst_part_html', wp_unslash( $_POST['jst_part_html'] ) );
	}
}
add_action( 'save_post_jst_part', 'jst_save_part_meta_box' );

/**
 * Add "Shortcode" column to the jst_part list screen.
 */
function jst_part_list_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['jst_shortcode'] = __( 'Shortcode', 'just-spectacular-theme' );
		}
	}
	return $new;
}
add_filter( 'manage_jst_part_posts_columns', 'jst_part_list_columns' );

function jst_part_list_column_content( $column, $post_id ) {
	if ( 'jst_shortcode' !== $column ) {
		return;
	}

	$name = get_post_meta( $post_id, '_jst_part_name', true );
	if ( ! $name ) {
		echo '<em style="color:#999;">' . esc_html__( 'No name set', 'just-spectacular-theme' ) . '</em>';
		return;
	}

	$shortcode = '[jst_part name="' . esc_attr( $name ) . '"]';
	echo '<code style="background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:12px;">' . esc_html( $shortcode ) . '</code> ';
	echo '<button type="button" class="button jst-quick-tag-btn jst-copy-btn" data-copy="' . esc_attr( $shortcode ) . '" style="margin-left:4px;">'
		. esc_html__( 'Copy', 'just-spectacular-theme' )
		. '</button>';
}
add_action( 'manage_jst_part_posts_custom_column', 'jst_part_list_column_content', 10, 2 );

/**
 * Shortcode: [jst_part name="part-name"]
 * Looks up the jst_part post by _jst_part_name meta and outputs its HTML raw.
 */
function jst_part_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'name' => '' ), $atts, 'jst_part' );

	if ( ! $atts['name'] ) {
		return '';
	}

	$parts = get_posts(
		array(
			'post_type'      => 'jst_part',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_jst_part_name',
					'value' => sanitize_title( $atts['name'] ),
				),
			),
			'no_found_rows'  => true,
		)
	);

	if ( empty( $parts ) ) {
		return '';
	}

	$html = get_post_meta( $parts[0]->ID, '_jst_part_html', true );
	return $html; // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_shortcode( 'jst_part', 'jst_part_shortcode' );

/**
 * Inline JS for Copy buttons: list screen shortcode copy + edit screen shortcode copy.
 */
function jst_part_admin_footer_js() {
	$screen = get_current_screen();
	if ( ! $screen || ( 'jst_part' !== $screen->post_type && 'edit-jst_part' !== $screen->id ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.jst-copy-btn' );
		if ( ! btn ) { return; }
		var text = btn.getAttribute( 'data-copy' );
		if ( ! text ) { return; }
		navigator.clipboard.writeText( text ).then( function () {
			var orig = btn.textContent;
			btn.textContent = 'Copied!';
			setTimeout( function () { btn.textContent = orig; }, 1500 );
		} );
	} );

	/* Edit screen: copy shortcode from the preview code element */
	var copyBtn = document.getElementById( 'jst_copy_shortcode' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			var preview = document.getElementById( 'jst_shortcode_preview' );
			if ( ! preview ) { return; }
			navigator.clipboard.writeText( preview.textContent ).then( function () {
				var orig = copyBtn.textContent;
				copyBtn.textContent = 'Copied!';
				setTimeout( function () { copyBtn.textContent = orig; }, 1500 );
			} );
		} );
	}
	</script>
	<?php
}
add_action( 'admin_footer', 'jst_part_admin_footer_js' );

/**
 * ------------------------------------------------------------------
 * ACF CPT compatibility: make theme templates available on any
 * post type that declares 'page-attributes' support — including
 * custom post types registered via ACF.
 * ------------------------------------------------------------------
 */
function jst_make_templates_global( $templates, $theme, $post, $post_type ) {
	if ( 'page' === $post_type || ! post_type_supports( $post_type, 'page-attributes' ) ) {
		return $templates;
	}

	$page_templates = wp_get_theme()->get_page_templates( null, 'page' );
	return array_merge( $templates, $page_templates );
}
add_filter( 'theme_templates', 'jst_make_templates_global', 10, 4 );
