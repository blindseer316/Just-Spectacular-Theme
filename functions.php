<?php
/**
 * Just Spectacular Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JST_VERSION', '2.9.0' );


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

	// Enqueue custom CSS file from uploads if it exists and has content.
	$upload_dir = wp_upload_dir();
	$css_file   = $upload_dir['basedir'] . '/jst-custom.css';
	if ( file_exists( $css_file ) && filesize( $css_file ) > 0 ) {
		wp_enqueue_style( 'jst-custom', $upload_dir['baseurl'] . '/jst-custom.css', array( 'jst-style' ), (string) filemtime( $css_file ) );
	}
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
		'jst_header_scripts' => array(
			'label'       => __( 'Header Scripts & Custom CSS', 'just-spectacular-theme' ),
			'description' => __( 'Outputs inside <head> — use for external CSS links, fonts, custom <style> blocks, and other head-level scripts.', 'just-spectacular-theme' ),
		),
		'jst_navigation'     => array(
			'label'       => __( 'Header Nav / Menu', 'just-spectacular-theme' ),
			'description' => __( 'Outputs at the very start of &lt;body&gt; via wp_body_open — use for your global header and navigation markup. Shortcodes: [jst_menu] renders the WordPress primary nav menu (supports location, ul_class, depth attributes).', 'just-spectacular-theme' ),
		),
		'jst_footer'         => array(
			'label'       => __( 'Footer HTML', 'just-spectacular-theme' ),
			'description' => __( 'Outputs after the page content, before Footer Scripts — use for your global footer design and navigation markup.', 'just-spectacular-theme' ),
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
		update_option( 'jst_prose_invert', isset( $_POST['jst_prose_invert'] ) ? '1' : '' );
		update_option( 'jst_sort_by_modified', isset( $_POST['jst_sort_by_modified'] ) ? '1' : '' );

		// Color Bridge — maps a client palette's custom properties to JST's canonical --jst-* vars.
		$color_bridge = isset( $_POST['jst_color_bridge'] ) ? wp_unslash( $_POST['jst_color_bridge'] ) : '';
		update_option( 'jst_color_bridge', $color_bridge );

		// Write Custom CSS to a real file in uploads so it's enqueued as a linked stylesheet.
		$css_content = isset( $_POST['jst_custom_css'] ) ? wp_unslash( $_POST['jst_custom_css'] ) : '';
		update_option( 'jst_custom_css', $css_content );
		$upload_dir = wp_upload_dir();
		$css_file   = $upload_dir['basedir'] . '/jst-custom.css';
		file_put_contents( $css_file, $css_content ); // phpcs:ignore -- intentional admin write.

		echo '<div class="updated"><p>' . esc_html__( 'Theme options saved.', 'just-spectacular-theme' ) . '</p></div>';
	}

	$fields        = jst_theme_options_fields();
	$disable_prose    = get_option( 'jst_disable_tailwind_prose', '' );
	$prose_invert     = get_option( 'jst_prose_invert', '' );
	$sort_by_modified = get_option( 'jst_sort_by_modified', '' );
	?>
	<style>
	#jst-sticky-save {
		position: sticky;
		top: 32px;
		z-index: 100;
		background: #fff;
		border-bottom: 1px solid #dcdcde;
		padding: 10px 0 10px 14px;
		margin-bottom: 1rem;
		display: flex;
		align-items: center;
		gap: 1rem;
	}
	#jst-sticky-save .jst-save-label { font-weight: 600; color: #1d2327; margin-right: 8px; }

	/* Two-column layout */
	#jst-options-columns { display: flex; gap: 20px; align-items: flex-start; }
	#jst-options-fields  { flex: 1; min-width: 0; }
	#jst-options-import-col {
		width: 320px;
		flex-shrink: 0;
		position: sticky;
		top: 80px;
		max-height: calc(100vh - 100px);
		overflow-y: auto;
	}

	/* Import panel */
	#jst-opts-import {
		background: #f6f7f7;
		border: 1px solid #dcdcde;
		border-radius: 4px;
		padding: 14px;
		font-size: 12px;
	}
	#jst-opts-import h3 { margin: 0 0 6px; font-size: 13px; font-weight: 600; }
	#jst-opts-import textarea { width: 100%; font-family: monospace; font-size: 11px; resize: vertical; box-sizing: border-box; }
	.jst-opts-extracted {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 3px;
		margin-bottom: 6px;
		overflow: hidden;
	}
	.jst-opts-ext-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 10px;
		font-size: 12px;
		font-weight: 600;
	}
	.jst-opts-ext-preview {
		border-top: 1px solid #f0f0f1;
		padding: 6px 10px;
		font-family: monospace;
		font-size: 10px;
		color: #646970;
		white-space: pre;
		overflow-x: auto;
		max-height: 80px;
		background: #fafafa;
	}
	.jst-opts-badge {
		font-size: 10px;
		font-weight: 700;
		padding: 2px 5px;
		border-radius: 3px;
		text-transform: uppercase;
		margin-left: auto;
	}
	.jst-opts-badge.found  { background: #d1e7dd; color: #0a3622; }
	.jst-opts-badge.empty  { background: #f8d7da; color: #58151c; }

	/* Color Bridge collapsible box */
	#jst-color-bridge-box {
		background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;
		padding: 10px 14px; margin: 8px 0 20px;
	}
	#jst-color-bridge-box summary {
		cursor: pointer; font-weight: 600; font-size: 13px; color: #1d2327;
		padding: 4px 0;
	}
	#jst-color-bridge-box[open] summary { margin-bottom: 8px; }

	/* Tabs */
	#jst-tab-nav { display:flex; gap:0; margin: 12px 0 0; border-bottom: 2px solid #dcdcde; }
	.jst-tab-btn {
		background: none; border: none; border-bottom: 2px solid transparent;
		margin-bottom: -2px; padding: 8px 16px; font-size: 14px; font-weight: 600;
		color: #646970; cursor: pointer;
	}
	.jst-tab-btn:hover { color: #1d2327; }
	.jst-tab-btn.jst-tab-active { color: #1d2327; border-bottom-color: #2271b1; }
	.jst-tab-panel { display: none; }
	.jst-tab-panel.jst-tab-visible { display: block; }

	/* Import Templates tab */
	#jst-import-dropzone {
		border: 2px dashed #c3c4c7; border-radius: 6px; padding: 32px;
		text-align: center; cursor: pointer; transition: border-color 0.15s;
		margin-bottom: 16px;
	}
	#jst-import-dropzone.jst-drop-over { border-color: #2271b1; background: #f0f6fc; }
	#jst-import-dropzone p { margin: 6px 0; color: #646970; font-size: 13px; }
	.jst-import-card {
		background: #fff; border: 1px solid #dcdcde; border-radius: 4px;
		margin-bottom: 12px; overflow: hidden;
	}
	.jst-import-card-head {
		display: flex; align-items: center; gap: 10px;
		padding: 10px 12px; background: #f6f7f7;
		border-bottom: 1px solid #dcdcde; font-size: 12px; font-weight: 600;
	}
	.jst-import-card-head .jst-file-name { flex: 1; color: #1d2327; }
	.jst-import-card-body { padding: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
	.jst-import-card-body label { display: flex; flex-direction: column; gap: 3px; font-size: 12px; color: #646970; }
	.jst-import-card-body input[type="text"],
	.jst-import-card-body select { width: 100%; font-size: 12px; padding: 4px 6px; box-sizing: border-box; }
	.jst-import-card-body .jst-title-row { grid-column: 1 / -1; }
	.jst-import-card-foot { padding: 8px 12px; border-top: 1px solid #f0f0f1; display: flex; align-items: center; gap: 8px; }
	/* Import tab three-column layout: upload controls | cards (widest) | output */
	#jst-import-columns { display: flex; gap: 20px; align-items: flex-start; }
	#jst-import-left {
		width: 260px; flex-shrink: 0;
		position: sticky; top: 32px;
	}
	#jst-import-middle { flex: 1; min-width: 0; }
	#jst-import-right {
		width: 320px; flex-shrink: 0;
		position: sticky; top: 32px;
		max-height: calc(100vh - 60px); overflow-y: auto;
	}
	/* Output log */
	#jst-import-output {
		background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;
		padding: 12px; min-height: 120px;
	}
	#jst-import-output h3 { margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #1d2327; }
	.jst-out-row {
		display: flex; align-items: baseline; gap: 8px;
		padding: 6px 0; border-bottom: 1px solid #f0f0f1; font-size: 12px;
	}
	.jst-out-row:last-child { border-bottom: none; }
	.jst-out-icon { font-size: 14px; flex-shrink: 0; }
	.jst-out-title { font-weight: 600; color: #1d2327; flex: 1; }
	.jst-out-links a { font-size: 11px; margin-left: 6px; }
	.jst-out-err { color: #8a1a1a; font-size: 11px; }
	.jst-out-pending { color: #646970; font-size: 11px; font-style: italic; }
	</style>

	<?php
	// Post types available for import (public + REST-enabled, no attachments).
	$jst_import_pts = get_post_types( array( 'public' => true, 'show_in_rest' => true ), 'objects' );
	unset( $jst_import_pts['attachment'] );
	$jst_pt_map = array();
	foreach ( $jst_import_pts as $pt ) {
		$jst_pt_map[ $pt->name ] = array(
			'label'     => $pt->labels->singular_name,
			'rest_base' => $pt->rest_base ?: $pt->name,
		);
	}
	// Templates registered in this theme.
	$jst_templates = array_merge(
		array( '' => 'Default' ),
		wp_get_theme()->get_page_templates()
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Theme Options', 'just-spectacular-theme' ); ?></h1>

		<div id="jst-tab-nav">
			<button type="button" class="jst-tab-btn jst-tab-active" data-tab="jst-tab-options"><?php esc_html_e( 'Theme Options', 'just-spectacular-theme' ); ?></button>
			<button type="button" class="jst-tab-btn" data-tab="jst-tab-import"><?php esc_html_e( 'Import Templates', 'just-spectacular-theme' ); ?></button>
			<button type="button" class="jst-tab-btn" data-tab="jst-tab-menu"><?php esc_html_e( 'Menu', 'just-spectacular-theme' ); ?></button>
		</div>

		<div id="jst-tab-options" class="jst-tab-panel jst-tab-visible">
		<form method="post" action="" id="jst-opts-form">
			<?php wp_nonce_field( 'jst_save_theme_options', 'jst_theme_options_nonce' ); ?>

			<div id="jst-sticky-save">
				<span class="jst-save-label"><?php esc_html_e( 'JST Theme Options', 'just-spectacular-theme' ); ?></span>
				<?php submit_button( __( 'Save Options', 'just-spectacular-theme' ), 'primary', 'submit', false ); ?>
			</div>

			<div id="jst-options-columns">

				<!-- Left: the four textareas + content styling -->
				<div id="jst-options-fields">
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

						<?php if ( 'jst_header_scripts' === $field_id ) : ?>
						<details id="jst-color-bridge-box">
							<summary><?php esc_html_e( 'Color Bridge (Palette Mapping)', 'just-spectacular-theme' ); ?></summary>
							<p><span class="description"><?php esc_html_e( 'Maps this site\'s palette variables to JST\'s canonical --jst-* vars, so blog post titles, borders, and content colors follow the palette automatically. Only needed if the theme looks unstyled/default despite a palette being pasted above.', 'just-spectacular-theme' ); ?></span></p>
							<p>
								<button type="button" id="jst-bridge-detect-btn" class="button button-secondary"><?php esc_html_e( 'Auto-detect from Header Scripts', 'just-spectacular-theme' ); ?></button>
								<span id="jst-bridge-detect-status" style="font-size:11px;color:#646970;"></span>
							</p>
							<p>
								<textarea id="jst_color_bridge" name="jst_color_bridge" rows="10" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo esc_textarea( get_option( 'jst_color_bridge', '' ) ); ?></textarea>
							</p>
						</details>
						<?php endif; ?>
					<?php endforeach; ?>

					<h2><?php esc_html_e( 'Custom CSS', 'just-spectacular-theme' ); ?></h2>
					<p><span class="description"><?php esc_html_e( 'Saved as /wp-content/uploads/jst-custom.css and enqueued as a linked stylesheet — not inline. Version-busted automatically on every save. Use for nav, footer, and any per-client CSS that doesn\'t belong in Header Scripts.', 'just-spectacular-theme' ); ?></span></p>
					<p>
						<textarea id="jst_custom_css" name="jst_custom_css" rows="18" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo esc_textarea( get_option( 'jst_custom_css', '' ) ); ?></textarea>
					</p>

					<h2><?php esc_html_e( 'Admin Behavior', 'just-spectacular-theme' ); ?></h2>
					<p>
						<label>
							<input type="checkbox" name="jst_sort_by_modified" value="1" <?php checked( $sort_by_modified, '1' ); ?> />
							<?php esc_html_e( 'Sort all post-type lists by date modified (newest first)', 'just-spectacular-theme' ); ?>
						</label>
						<br>
						<span class="description">
							<?php esc_html_e( 'Overrides the default list order in wp-admin — pages, posts, and any custom post type are sorted by last modified date instead of publish date. Users can still click column headers to re-sort.', 'just-spectacular-theme' ); ?>
						</span>
					</p>

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
					<p>
						<label>
							<input type="checkbox" name="jst_prose_invert" value="1" <?php checked( $prose_invert, '1' ); ?> />
							<?php esc_html_e( 'Prose invert (dark background)', 'just-spectacular-theme' ); ?>
						</label>
						<br>
						<span class="description">
							<?php esc_html_e( 'Adds "prose-invert" sitewide — flips prose text/heading/link colors to light variants for dark background sites. Can also be set per-page in Page Settings.', 'just-spectacular-theme' ); ?>
						</span>
					</p>

					<?php submit_button( __( 'Save Options', 'just-spectacular-theme' ) ); ?>
				</div>

				<!-- Right: import panel -->
				<div id="jst-options-import-col">
					<div id="jst-opts-import">
						<h3><?php esc_html_e( 'Import from HTML Template', 'just-spectacular-theme' ); ?></h3>
						<p style="color:#646970;font-size:11px;margin:0 0 8px;"><?php esc_html_e( 'Upload your base HTML file. The scanner extracts nav, header scripts, footer, and footer scripts automatically.', 'just-spectacular-theme' ); ?></p>
						<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
							<label class="button button-small" style="cursor:pointer;">
								<?php esc_html_e( 'Upload HTML File', 'just-spectacular-theme' ); ?>
								<input type="file" id="jst-opts-file" accept=".html,text/html" style="display:none;">
							</label>
							<span style="color:#646970;font-size:11px;"><?php esc_html_e( 'or paste ↓', 'just-spectacular-theme' ); ?></span>
						</div>
						<textarea id="jst-opts-html" rows="5" placeholder="Paste full HTML here…"></textarea>
						<div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
							<button type="button" id="jst-opts-scan-btn" class="button button-primary button-small"><?php esc_html_e( 'Scan', 'just-spectacular-theme' ); ?></button>
							<span id="jst-opts-status" style="font-size:11px;color:#646970;"></span>
						</div>
						<div id="jst-opts-results" style="margin-top:10px;"></div>
						<div id="jst-opts-actions" style="display:none;margin-top:8px;gap:8px;align-items:center;flex-wrap:wrap;">
							<button type="button" id="jst-opts-apply-btn" class="button button-primary button-small"><?php esc_html_e( 'Apply All Found', 'just-spectacular-theme' ); ?></button>
							<span id="jst-opts-apply-status" style="font-size:11px;color:#646970;"></span>
						</div>
					</div>
				</div>

			</div><!-- #jst-options-columns -->
		</form>
		</div><!-- #jst-tab-options -->

		<div id="jst-tab-import" class="jst-tab-panel" style="padding-top:16px;">
			<div id="jst-import-columns">

				<!-- Left: upload controls (sticky, narrow) -->
				<div id="jst-import-left">
					<div id="jst-import-dropzone">
						<p style="font-size:15px;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Drop HTML files here', 'just-spectacular-theme' ); ?></p>
						<p><?php esc_html_e( 'or', 'just-spectacular-theme' ); ?></p>
						<label class="button button-primary" style="cursor:pointer;">
							<?php esc_html_e( 'Choose Files', 'just-spectacular-theme' ); ?>
							<input type="file" id="jst-import-file-input" accept=".html,text/html" multiple style="display:none;">
						</label>
						<p style="margin-top:10px;font-size:11px;"><?php esc_html_e( 'Strips header, footer, mobile drawer, scripts — keeps page body sections.', 'just-spectacular-theme' ); ?></p>
					</div>

					<div id="jst-import-bulk-actions" style="display:none;margin-bottom:12px;align-items:center;gap:8px;">
						<button type="button" id="jst-import-create-all" class="button button-primary"><?php esc_html_e( 'Create All', 'just-spectacular-theme' ); ?></button>
						<span id="jst-import-bulk-status" style="font-size:12px;color:#646970;"></span>
					</div>
				</div>

				<!-- Middle: page cards (widest) -->
				<div id="jst-import-middle">
					<div id="jst-import-cards"></div>
				</div>

				<!-- Right: output log (narrow) -->
				<div id="jst-import-right">
					<div id="jst-import-output">
						<h3><?php esc_html_e( 'Output', 'just-spectacular-theme' ); ?></h3>
						<p class="jst-out-pending"><?php esc_html_e( 'Created pages will appear here.', 'just-spectacular-theme' ); ?></p>
					</div>
				</div>

			</div>
		</div><!-- #jst-tab-import -->

		<div id="jst-tab-menu" class="jst-tab-panel" style="padding-top:16px;">
			<div id="jst-menu-wrap" style="max-width:640px;">

				<p style="color:#646970;font-size:12px;"><?php esc_html_e( 'Reads your saved Header Nav / Menu HTML and shows it as a plain list — add a page under an existing menu item, remove one, or add a whole new top-level item. Changes save immediately.', 'just-spectacular-theme' ); ?></p>
				<p>
					<button type="button" id="jst-menu-load-btn" class="button button-primary"><?php esc_html_e( 'Load Current Menu', 'just-spectacular-theme' ); ?></button>
					<span id="jst-menu-load-status" style="font-size:12px;color:#646970;margin-left:8px;"></span>
				</p>

				<div id="jst-menu-add-toplevel-row" style="display:none;margin:10px 0;"></div>

				<div id="jst-menu-outline"></div>

			</div>
		</div><!-- #jst-tab-menu -->

	</div><!-- .wrap -->

	<script>
	var jstRestNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
	var jstRestUrl   = <?php echo wp_json_encode( rest_url() ); ?>;
	var jstPtMap     = <?php echo wp_json_encode( $jst_pt_map ); ?>;
	var jstTemplates = <?php echo wp_json_encode( $jst_templates ); ?>;
	</script>
	<script>
	// ── Shared Header Scripts diff helpers (used by both the Theme Options
	// scan panel and the Import Templates tab) ─────────────────────────────
	function jstSplitCssTopLevel( css ) {
		var blocks = [];
		var depth  = 0;
		var start  = 0;
		for ( var i = 0; i < css.length; i++ ) {
			if ( css[ i ] === '{' ) { depth++; }
			else if ( css[ i ] === '}' ) {
				depth--;
				if ( depth === 0 ) {
					var block = css.slice( start, i + 1 ).trim();
					if ( block ) { blocks.push( block ); }
					start = i + 1;
				}
			}
		}
		return blocks;
	}

	// Splits JS into top-level statements (each IIFE like `(function(){...})();`
	// becomes its own unit) by tracking combined ( / { depth and cutting at
	// each top-level ';'. Not a real parser — good enough for the hand-authored
	// template scripts this tool deals with, same trust/scope as the CSS
	// splitter above.
	function jstSplitScriptTopLevel( js ) {
		var blocks = [];
		var depth  = 0;
		var start  = 0;
		for ( var i = 0; i < js.length; i++ ) {
			var ch = js[ i ];
			if ( ch === '{' || ch === '(' ) { depth++; }
			else if ( ch === '}' || ch === ')' ) { depth--; }
			else if ( ch === ';' && depth === 0 ) {
				var block = js.slice( start, i + 1 ).trim();
				if ( block ) { blocks.push( block ); }
				start = i + 1;
			}
		}
		var tail = js.slice( start ).trim();
		if ( tail ) { blocks.push( tail ); }
		return blocks;
	}

	// Strips /* ... */ block comments — shared by CSS and JS (identical syntax).
	function jstStripBlockComments( css ) {
		return css.replace( /\/\*[\s\S]*?\*\//g, '' );
	}

	// Strips // line comments — naive (doesn't understand strings/regex
	// containing "//"), acceptable for the trusted admin-authored scripts
	// this tool handles.
	function jstStripLineComments( js ) {
		return js.split( '\n' ).map( function( line ) {
			var idx = line.indexOf( '//' );
			return idx === -1 ? line : line.slice( 0, idx );
		} ).join( '\n' );
	}

	// Splits a head/footer fragment string into typed, independently
	// comparable blocks: { type: 'style'|'script'|'tag', text } — CSS rules
	// and JS top-level statements/IIFEs are split out individually so, e.g.,
	// a shared mobile-menu IIFE and a page-specific tab-toggle IIFE bundled
	// in the same <script> tag are compared (and can be appended) separately
	// instead of the whole tag having to match byte-for-byte.
	function jstSplitHeadBlocks( str ) {
		var doc = ( new DOMParser() ).parseFromString( '<!doctype html><body>' + str + '</body>', 'text/html' );
		var blocks = [];
		Array.from( doc.body.children ).forEach( function( el ) {
			if ( 'STYLE' === el.tagName ) {
				jstSplitCssTopLevel( jstStripBlockComments( el.textContent ) ).forEach( function( rule ) {
					blocks.push( { type: 'style', text: rule } );
				} );
			} else if ( 'SCRIPT' === el.tagName && ! el.src && el.textContent.trim() && 'application/ld+json' !== el.getAttribute( 'type' ) ) {
				var cleaned = jstStripLineComments( jstStripBlockComments( el.textContent ) );
				jstSplitScriptTopLevel( cleaned ).forEach( function( stmt ) {
					blocks.push( { type: 'script', text: stmt } );
				} );
			} else {
				blocks.push( { type: 'tag', text: el.outerHTML } );
			}
		} );
		return blocks;
	}

	function jstNormalizeBlock( block ) {
		return block.type + '::' + block.text.replace( /\s+/g, ' ' ).trim();
	}

	// Extracts head-level link/style/script tags from a parsed document,
	// excluding meta/title/base — same rule set used for jst_header_scripts.
	function jstExtractHeadScripts( doc ) {
		var parts = [];
		if ( doc.head ) {
			Array.from( doc.head.children ).forEach( function( el ) {
				var tag = el.tagName.toLowerCase();
				if ( tag === 'meta' || tag === 'title' || tag === 'base' ) { return; }
				parts.push( el.outerHTML );
			} );
		}
		return parts.join( '\n' ).trim();
	}

	// Extracts <script> tags anywhere in <body> — same rule set used for
	// jst_footer_scripts (bottom-of-page scripts).
	function jstExtractFooterScripts( doc ) {
		var parts = [];
		if ( doc.body ) {
			Array.from( doc.body.querySelectorAll( 'script' ) ).forEach( function( el ) {
				parts.push( el.outerHTML );
			} );
		}
		return parts.join( '\n' ).trim();
	}

	// Returns only the blocks in newContent that don't already exist in
	// existingValue (both split into atomic, typed, comparable units first).
	function jstComputeFreshBlocks( newContent, existingValue ) {
		var existingBlocks = jstSplitHeadBlocks( existingValue || '' ).map( jstNormalizeBlock );
		var newBlocks       = jstSplitHeadBlocks( newContent );
		return newBlocks.filter( function( b ) {
			return existingBlocks.indexOf( jstNormalizeBlock( b ) ) === -1;
		} );
	}

	// Builds a ready-to-insert HTML/CSS/JS string from checked diff blocks —
	// style blocks wrapped in one <style> tag, script statements wrapped in
	// one <script> tag, whole tags (link, <script src>, ld+json) as-is.
	function jstBuildAddition( blocks ) {
		var styleRules = blocks.filter( function( b ) { return 'style' === b.type; } ).map( function( b ) { return b.text; } );
		var scriptStmts = blocks.filter( function( b ) { return 'script' === b.type; } ).map( function( b ) { return b.text; } );
		var tags = blocks.filter( function( b ) { return 'tag' === b.type; } ).map( function( b ) { return b.text; } );

		var addition = '';
		if ( styleRules.length ) { addition += '<style>\n' + styleRules.join( '\n' ) + '\n</style>\n'; }
		if ( scriptStmts.length ) { addition += '<script>\n' + scriptStmts.join( '\n\n' ) + '\n<\/script>\n'; }
		if ( tags.length ) { addition += tags.join( '\n' ) + '\n'; }
		return addition.trim();
	}

	// Renders a checklist of fresh blocks + an "Append" button that writes
	// only the checked ones into targetTextarea without touching existing
	// content. If pageCodeSetter is provided, a second button lets the
	// caller route the same checked blocks somewhere else instead (e.g. a
	// specific page's own Header/Footer Code field rather than the
	// site-wide box). targetLabel/pageFieldLabel customize button text so
	// this same function serves both header and footer diffs.
	function jstRenderFreshBlocksUI( freshBlocks, container, targetTextarea, targetLabel, pageCodeSetter, pageFieldLabel ) {
		container.innerHTML = '';
		container.style.display = 'block';

		var selectAllRow = document.createElement( 'label' );
		selectAllRow.style.cssText = 'display:flex;gap:6px;align-items:center;padding:4px 0 8px;font-size:11px;font-weight:600;color:#1d2327;';
		var selectAllCb = document.createElement( 'input' );
		selectAllCb.type = 'checkbox';
		selectAllCb.checked = true;
		selectAllRow.appendChild( selectAllCb );
		selectAllRow.appendChild( document.createTextNode( 'Select all' ) );
		container.appendChild( selectAllRow );

		var list = document.createElement( 'div' );
		list.style.cssText = 'background:#fff;border:1px solid #dcdcde;border-radius:3px;padding:6px;margin-bottom:6px;max-height:200px;overflow-y:auto;';

		freshBlocks.forEach( function( block, idx ) {
			var row = document.createElement( 'label' );
			row.style.cssText = 'display:flex;gap:6px;align-items:flex-start;padding:4px 0;font-size:11px;font-family:monospace;border-bottom:1px solid #f0f0f1;';
			var cb = document.createElement( 'input' );
			cb.type    = 'checkbox';
			cb.checked = true;
			cb.dataset.diffIndex = idx;
			cb.addEventListener( 'change', updateButtons );
			var txt = document.createElement( 'span' );
			txt.style.whiteSpace = 'pre-wrap';
			var blockText = block.text;
			txt.textContent = blockText.length > 200 ? blockText.slice( 0, 200 ) + '…' : blockText;
			row.appendChild( cb );
			row.appendChild( txt );
			list.appendChild( row );
		} );
		container.appendChild( list );

		selectAllCb.addEventListener( 'change', function() {
			list.querySelectorAll( 'input[type="checkbox"]' ).forEach( function( cb ) { cb.checked = selectAllCb.checked; } );
			updateButtons();
		} );

		function getChecked() {
			return Array.from( list.querySelectorAll( 'input:checked' ) ).map( function( cb ) {
				return freshBlocks[ cb.dataset.diffIndex ];
			} );
		}

		var btnRow = document.createElement( 'div' );
		btnRow.style.cssText = 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;';
		container.appendChild( btnRow );

		var appendStatus = document.createElement( 'span' );
		appendStatus.style.cssText = 'font-size:11px;color:#646970;';

		var appendBtn, pageBtn;

		function updateButtons() {
			var n = getChecked().length;
			var allChecked = n === freshBlocks.length;
			selectAllCb.checked = n > 0 && allChecked;
			selectAllCb.indeterminate = n > 0 && ! allChecked;
			if ( appendBtn ) {
				appendBtn.disabled = n === 0;
				appendBtn.textContent = 'Append ' + n + ' Rule' + ( n !== 1 ? 's' : '' ) + ' to ' + targetLabel;
			}
			if ( pageBtn ) {
				pageBtn.disabled = n === 0;
				pageBtn.textContent = 'Add ' + n + ' Rule' + ( n !== 1 ? 's' : '' ) + ' to This Page’s ' + pageFieldLabel + ' Instead';
			}
		}

		if ( targetTextarea ) {
			appendBtn = document.createElement( 'button' );
			appendBtn.type = 'button';
			appendBtn.className = 'button button-primary button-small';
			btnRow.appendChild( appendBtn );

			appendBtn.addEventListener( 'click', function() {
				var checked = getChecked();
				if ( ! checked.length ) { return; }
				targetTextarea.value = targetTextarea.value.trim() + '\n\n' + jstBuildAddition( checked );
				appendStatus.textContent = 'Appended — hit Save Options to commit.';
			} );
		}

		if ( pageCodeSetter ) {
			pageBtn = document.createElement( 'button' );
			pageBtn.type = 'button';
			pageBtn.className = 'button button-secondary button-small';
			btnRow.appendChild( pageBtn );

			pageBtn.addEventListener( 'click', function() {
				var checked = getChecked();
				if ( ! checked.length ) { return; }
				pageCodeSetter( jstBuildAddition( checked ) );
				appendStatus.textContent = 'Set on this page’s ' + pageFieldLabel + ' — will be included when the page is created.';
			} );
		}

		btnRow.appendChild( appendStatus );
		updateButtons();
	}

	( function() {
		var fileInput = document.getElementById( 'jst-opts-file' );
		var htmlArea  = document.getElementById( 'jst-opts-html' );
		var scanBtn   = document.getElementById( 'jst-opts-scan-btn' );
		var status    = document.getElementById( 'jst-opts-status' );
		var results   = document.getElementById( 'jst-opts-results' );
		var actions   = document.getElementById( 'jst-opts-actions' );
		var headerArea2 = document.getElementById( 'jst_header_scripts' );

		// Extracted content keyed by option field id.
		var extracted = {};

		fileInput.addEventListener( 'change', function( e ) {
			var file = e.target.files[0];
			if ( ! file ) { return; }
			var reader = new FileReader();
			reader.onload = function( ev ) {
				htmlArea.value = ev.target.result;
				runScan();
			};
			reader.readAsText( file );
		} );

		scanBtn.addEventListener( 'click', runScan );

		function outerHtmlOf( el ) { return el ? el.outerHTML : ''; }

		// ── Header/Footer Scripts diff tool (uses shared jst* helpers above) ────
		var footerScriptsArea = document.getElementById( 'jst_footer_scripts' );

		function renderHeaderDiff( newContent, container ) {
			var freshBlocks = jstComputeFreshBlocks( newContent, headerArea2 ? headerArea2.value : '' );

			container.innerHTML = '';
			container.style.display = 'block';

			if ( ! freshBlocks.length ) {
				container.innerHTML = '<p style="font-size:11px;color:#646970;margin:0;">No new rules found — everything here already exists in the saved Header Scripts.</p>';
				return;
			}

			jstRenderFreshBlocksUI( freshBlocks, container, headerArea2, 'Header Scripts' );
		}

		function renderFooterScriptsDiff( newContent, container ) {
			var freshBlocks = jstComputeFreshBlocks( newContent, footerScriptsArea ? footerScriptsArea.value : '' );

			container.innerHTML = '';
			container.style.display = 'block';

			if ( ! freshBlocks.length ) {
				container.innerHTML = '<p style="font-size:11px;color:#646970;margin:0;">No new scripts found — everything here already exists in the saved Footer Scripts.</p>';
				return;
			}

			jstRenderFreshBlocksUI( freshBlocks, container, footerScriptsArea, 'Footer Scripts' );
		}

		function runScan() {
			var raw = htmlArea.value.trim();
			results.innerHTML = '';
			actions.style.display = 'none';
			extracted = {};

			if ( ! raw ) { status.textContent = 'No HTML yet.'; return; }

			var doc = ( new DOMParser() ).parseFromString( raw, 'text/html' );

			// Returns the nearest preceding HTML comment for an element, if any.
			function precedingComment( el ) {
				if ( ! el ) { return ''; }
				var sib = el.previousSibling;
				while ( sib && sib.nodeType === 3 && sib.textContent.trim() === '' ) { sib = sib.previousSibling; }
				return ( sib && sib.nodeType === 8 ) ? '<!-- ' + sib.nodeValue.trim() + ' -->\n' : '';
			}

			// --- 1. Nav / Header ---
			// Look for: <header id="site-nav">, <header id="nav">, <div id="topbar">,
			// <section id="nav">, <nav>, any element with id/class containing "topbar".
			var navEl = (
				doc.querySelector( 'header[id*="nav"]' ) ||
				doc.querySelector( 'header[id*="header"]' ) ||
				doc.querySelector( '[id="topbar"]' ) ||
				doc.querySelector( '[class*="topbar"]' ) ||
				doc.querySelector( 'section[id*="nav"]' ) ||
				doc.querySelector( 'header' )
			);
			// Also grab a topbar sibling if it exists separately.
			var topbarEl = ( navEl && ! navEl.matches( '[id*="topbar"],[class*="topbar"]' ) )
				? doc.querySelector( '[id*="topbar"],[class*="topbar"]' )
				: null;
			// Also grab mobile drawer/menu siblings adjacent to the header.
			var mobileDrawer = (
				doc.querySelector( '[id*="mobile-drawer"],[class*="mobile-drawer"]' ) ||
				doc.querySelector( '[id*="mobile-menu"],[class*="mobile-menu"]' ) ||
				doc.querySelector( '[id*="drawer"],[class*="drawer"]' )
			);
			// Don't double-add if already inside navEl.
			if ( mobileDrawer && navEl && navEl.contains( mobileDrawer ) ) { mobileDrawer = null; }
			var navHtml = '';
			if ( topbarEl    ) { navHtml += precedingComment( topbarEl )    + topbarEl.outerHTML    + '\n'; }
			if ( navEl       ) { navHtml += precedingComment( navEl )       + navEl.outerHTML       + '\n'; }
			if ( mobileDrawer) { navHtml += precedingComment( mobileDrawer ) + mobileDrawer.outerHTML; }
			navHtml = navHtml.trim();
			extracted['jst_navigation'] = navHtml;

			// --- 2. Header Scripts / CSS ---
			// All <link rel="stylesheet">, font <link>, <style> in <head>.
			// Exclude <meta>, <title>, <base>, <script> (those go to footer scripts).
			var headScriptParts = [];
			if ( doc.head ) {
				Array.from( doc.head.children ).forEach( function( el ) {
					var tag = el.tagName.toLowerCase();
					if ( tag === 'meta' || tag === 'title' || tag === 'base' ) { return; }
					// include <link>, <style>, <script> in head
					headScriptParts.push( el.outerHTML );
				} );
			}
			extracted['jst_header_scripts'] = headScriptParts.join( '\n' ).trim();

			// --- 3. Footer HTML ---
			// <footer> element + any sticky CTA (.cta-sticky-mobile / #cta-sticky-mobile / [class*="sticky"][class*="cta"]).
			var footerEl  = doc.querySelector( 'footer' );
			var stickyCta = (
				doc.querySelector( '#cta-sticky-mobile' ) ||
				doc.querySelector( '.cta-sticky-mobile' ) ||
				doc.querySelector( '[class*="sticky-cta"],[class*="cta-sticky"],[id*="sticky-cta"],[id*="cta-sticky"]' ) ||
				doc.querySelector( '[role="complementary"][aria-label*="contact" i],[role="complementary"][aria-label*="cta" i]' ) ||
				doc.querySelector( '[class*="mobile-bar"],[id*="mobile-bar"]' ) ||
				doc.querySelector( '[class*="sticky"][class*="cta"],[id*="sticky"][id*="cta"]' )
			);
			// Walk up if we landed on a button/anchor instead of the wrapper container.
			if ( stickyCta && ( stickyCta.tagName === 'BUTTON' || stickyCta.tagName === 'A' ) ) {
				var stickyParent = stickyCta.parentElement;
				if ( stickyParent && stickyParent.tagName !== 'BODY' && stickyParent.tagName !== 'MAIN' && ! ( footerEl && footerEl.contains( stickyParent ) ) ) {
					stickyCta = stickyParent;
				}
			}
			var footerHtml = '';
			// If sticky CTA is inside the footer don't double-add it.
			if ( stickyCta && footerEl && footerEl.contains( stickyCta ) ) { stickyCta = null; }
			if ( footerEl  ) { footerHtml += precedingComment( footerEl )  + footerEl.outerHTML; }
			if ( stickyCta ) { footerHtml += '\n' + precedingComment( stickyCta ) + stickyCta.outerHTML; }
			extracted['jst_footer'] = footerHtml.trim();

			// --- 4. Footer Scripts ---
			// <script> tags that appear in <body> (typically at the bottom).
			var bodyScripts = [];
			if ( doc.body ) {
				Array.from( doc.body.querySelectorAll( 'script' ) ).forEach( function( el ) {
					bodyScripts.push( el.outerHTML );
				} );
			}
			extracted['jst_footer_scripts'] = bodyScripts.join( '\n' ).trim();

			// Render results.
			var labels = {
				'jst_navigation':     'Header Nav / Menu',
				'jst_header_scripts': 'Header Scripts & CSS',
				'jst_footer':         'Footer HTML',
				'jst_footer_scripts': 'Footer Scripts',
			};
			var foundCount = 0;
			Object.keys( labels ).forEach( function( key ) {
				var content = extracted[ key ] || '';
				var found   = content.length > 0;
				if ( found ) { foundCount++; }

				var card = document.createElement( 'div' );
				card.className = 'jst-opts-extracted';

				var hdr = document.createElement( 'div' );
				hdr.className = 'jst-opts-ext-header';

				var cb = document.createElement( 'input' );
				cb.type    = 'checkbox';
				cb.checked = found;
				cb.dataset.field = key;
				if ( ! found ) { cb.disabled = true; }

				var lbl = document.createElement( 'span' );
				lbl.textContent = labels[ key ];

				var badge = document.createElement( 'span' );
				badge.className = 'jst-opts-badge ' + ( found ? 'found' : 'empty' );
				badge.textContent = found ? 'Found' : 'Not found';

				hdr.appendChild( cb );
				hdr.appendChild( lbl );
				hdr.appendChild( badge );
				card.appendChild( hdr );

				if ( found ) {
					var pre = document.createElement( 'div' );
					pre.className = 'jst-opts-ext-preview';
					pre.textContent = content.slice( 0, 400 ) + ( content.length > 400 ? '\n…' : '' );
					card.appendChild( pre );
				}

				// Header Scripts gets an extra "detect new rules only" diff tool —
				// useful when importing a variant page (e.g. a service-area page)
				// built on top of an already-configured base template, so only
				// the handful of new/changed rules get surfaced instead of the
				// whole head block.
				if ( found && ( 'jst_header_scripts' === key || 'jst_footer_scripts' === key ) ) {
					var diffFn = ( 'jst_header_scripts' === key ) ? renderHeaderDiff : renderFooterScriptsDiff;

					var diffBtn = document.createElement( 'button' );
					diffBtn.type = 'button';
					diffBtn.className = 'button button-small';
					diffBtn.style.margin = '6px 10px 0';
					diffBtn.textContent = 'Detect New Rules Only';
					card.appendChild( diffBtn );

					var diffBox = document.createElement( 'div' );
					diffBox.style.margin = '6px 10px 10px';
					diffBox.style.display = 'none';
					card.appendChild( diffBox );

					diffBtn.addEventListener( 'click', function() {
						diffFn( content, diffBox );
					} );
				}

				results.appendChild( card );
			} );

			status.textContent = foundCount + ' of 4 sections found.';
			if ( foundCount > 0 ) {
				actions.style.display = 'flex';
			}
		}

		document.getElementById( 'jst-opts-apply-btn' ).addEventListener( 'click', function() {
			var applyStatus = document.getElementById( 'jst-opts-apply-status' );
			var applied = 0;

			results.querySelectorAll( 'input[type="checkbox"]:checked' ).forEach( function( cb ) {
				var field   = cb.dataset.field;
				var content = extracted[ field ];
				var ta      = document.getElementById( field );
				if ( ta && content ) {
					ta.value = content;
					applied++;
				}
			} );

			applyStatus.textContent = applied + ' field' + ( applied !== 1 ? 's' : '' ) + ' applied — hit Save Options to commit.';
		} );
	} )();

	// ── Color Bridge auto-detect ────────────────────────────────────────────
	( function() {
		var detectBtn = document.getElementById( 'jst-bridge-detect-btn' );
		if ( ! detectBtn ) { return; }
		var detectStatus = document.getElementById( 'jst-bridge-detect-status' );
		var bridgeArea    = document.getElementById( 'jst_color_bridge' );
		var headerArea    = document.getElementById( 'jst_header_scripts' );

		// JST canonical vars → matching heuristics against a source var name.
		var rules = [
			{ target: '--jst-accent',       test: function(n){ return /primary|accent/.test(n) && ! /light|dim|dark|hover|mute/.test(n); } },
			{ target: '--jst-accent-hover', test: function(n){ return /primary|accent/.test(n) && /dim|dark|hover/.test(n); } },
			{ target: '--jst-text-dim',     test: function(n){ return /(white|text|fg)/.test(n) && /dim/.test(n); } },
			{ target: '--jst-muted',        test: function(n){ return /(white|text|fg)/.test(n) && /mute/.test(n); } },
			{ target: '--jst-text',         test: function(n){ return /(white|text|fg)/.test(n) && ! /dim|mute|line|border/.test(n); } },
			{ target: '--jst-border',       test: function(n){ return /line|border/.test(n); } },
			{ target: '--jst-bg-alt',       test: function(n){ return /surface-2|surface2|bg-2|bg2|section-bg-2/.test(n); } },
			{ target: '--jst-white',        test: function(n){ return /^--(brand-)?surface$|^--(brand-)?bg$/.test(n); } },
		];

		// Collect candidate color sources from two formats:
		// 1. CSS custom properties: --name: value;
		// 2. JS/Tailwind-config object entries: 'name': '#hex' or "name": "rgba(...)"
		function collectCandidates( src ) {
			var list = [];
			var reVar = /(--[a-zA-Z0-9-]+)\s*:\s*([^;]+);/g;
			var m;
			while ( ( m = reVar.exec( src ) ) !== null ) {
				list.push( { name: m[1], isVar: true } );
			}
			var reObj = /['"]([a-zA-Z][a-zA-Z0-9-]*)['"]\s*:\s*['"]((?:#[0-9a-fA-F]{3,8})|rgba?\([^)'"]+\))['"]/g;
			while ( ( m = reObj.exec( src ) ) !== null ) {
				list.push( { name: '--' + m[1], isVar: false, value: m[2] } );
			}
			return list;
		}

		function colorLine( candidate ) {
			return candidate.isVar ? ( 'var(' + candidate.name + ')' ) : candidate.value;
		}

		detectBtn.addEventListener( 'click', function() {
			var src = headerArea ? headerArea.value : '';
			var found = {};
			var candidates = collectCandidates( src );

			rules.forEach( function( rule ) {
				for ( var i = 0; i < candidates.length; i++ ) {
					var name = candidates[ i ].name.toLowerCase();
					if ( rule.test( name ) && ! found[ rule.target ] ) {
						found[ rule.target ] = candidates[ i ];
						break;
					}
				}
			} );

			// Prefer the body{} rule's actual text color (var or literal) —
			// more reliable than name-guessing (e.g. "ink" is usually a bg tone).
			var bodyMatch = src.match( /\bbody\s*\{([^}]*)\}/i );
			if ( bodyMatch ) {
				var textMatch = bodyMatch[1].match( /(?:^|;)\s*color\s*:\s*(var\(\s*--[a-zA-Z0-9-]+\s*\)|#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/i );
				if ( textMatch ) {
					var raw = textMatch[1].trim();
					var varInner = raw.match( /^var\(\s*(--[a-zA-Z0-9-]+)\s*\)$/i );
					found['--jst-text'] = varInner
						? { name: varInner[1], isVar: true }
						: { name: null, isVar: false, value: raw };
				}
			}

			var lines = Object.keys( found );
			if ( ! lines.length ) {
				detectStatus.textContent = 'No matching variables found — check naming or edit manually.';
				return;
			}

			var css = ':root {\n';
			lines.forEach( function( target ) {
				css += '  ' + target + ': ' + colorLine( found[ target ] ) + ';\n';
			} );
			css += '}';

			bridgeArea.value = css;
			var box = document.getElementById( 'jst-color-bridge-box' );
			if ( box ) { box.open = true; }
			detectStatus.textContent = lines.length + ' mapping' + ( lines.length !== 1 ? 's' : '' ) + ' found — review before saving.';
		} );
	} )();

	// ── Tab switching ────────────────────────────────────────────────────────
	( function() {
		var btns   = document.querySelectorAll( '.jst-tab-btn' );
		var panels = document.querySelectorAll( '.jst-tab-panel' );
		btns.forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				btns.forEach( function( b ) { b.classList.remove( 'jst-tab-active' ); } );
				panels.forEach( function( p ) { p.classList.remove( 'jst-tab-visible' ); } );
				btn.classList.add( 'jst-tab-active' );
				var target = document.getElementById( btn.dataset.tab );
				if ( target ) { target.classList.add( 'jst-tab-visible' ); }
			} );
		} );
	} )();

	// ── Import Templates tab ─────────────────────────────────────────────────
	( function() {
		var dropzone   = document.getElementById( 'jst-import-dropzone' );
		var fileInput  = document.getElementById( 'jst-import-file-input' );
		var cardsEl    = document.getElementById( 'jst-import-cards' );
		var bulkBar    = document.getElementById( 'jst-import-bulk-actions' );
		var bulkStatus = document.getElementById( 'jst-import-bulk-status' );
		var outputEl   = document.getElementById( 'jst-import-output' );
		var bulkTotal = 0, bulkDone = 0;

		// Drag-and-drop on dropzone.
		dropzone.addEventListener( 'dragover',  function(e) { e.preventDefault(); dropzone.classList.add( 'jst-drop-over' ); } );
		dropzone.addEventListener( 'dragleave', function()  { dropzone.classList.remove( 'jst-drop-over' ); } );
		dropzone.addEventListener( 'drop', function(e) {
			e.preventDefault();
			dropzone.classList.remove( 'jst-drop-over' );
			handleFiles( e.dataTransfer.files );
		} );
		fileInput.addEventListener( 'change', function() { handleFiles( fileInput.files ); } );

		function handleFiles( files ) {
			if ( ! files || ! files.length ) { return; }
			Array.from( files ).forEach( function( file ) {
				var reader = new FileReader();
				reader.onload = function( ev ) { addCard( file.name, ev.target.result ); };
				reader.readAsText( file );
			} );
			bulkBar.style.display = 'flex';
		}

		function extractPageContent( doc ) {
			var clone = doc.cloneNode( true );
			var body  = clone.body;
			if ( ! body ) { return ''; }
			var toRemove = [
				'header', 'footer',
				'[id*="mobile-drawer"],[class*="mobile-drawer"]',
				'[id*="mobile-menu"],[class*="mobile-menu"]',
				'[id*="drawer"],[class*="drawer"]',
				'[role="complementary"]',
				'#cta-sticky-mobile', '.cta-sticky-mobile',
				'[class*="sticky-cta"],[class*="cta-sticky"],[id*="sticky-cta"],[id*="cta-sticky"]',
				'[class*="mobile-bar"],[id*="mobile-bar"]',
				'script', 'noscript'
			];
			toRemove.forEach( function( sel ) {
				try { body.querySelectorAll( sel ).forEach( function( el ) { el.remove(); } ); } catch(e) {}
			} );
			// Strip HTML comment nodes left behind after element removal.
			var walker = clone.createTreeWalker( body, NodeFilter.SHOW_COMMENT, null, false );
			var commentNodes = [];
			var n;
			while ( ( n = walker.nextNode() ) ) { commentNodes.push( n ); }
			commentNodes.forEach( function( c ) { c.parentNode.removeChild( c ); } );
			// Wrap in Gutenberg HTML block so WP stores it as raw HTML, not classic editor.
			var inner = body.innerHTML.trim();
			return inner ? '<!-- wp:html -->\n' + inner + '\n<!-- /wp:html -->' : '';
		}

		function addCard( filename, raw ) {
			var doc     = ( new DOMParser() ).parseFromString( raw, 'text/html' );
			var title   = ( doc.querySelector( 'title' ) || {} ).textContent || filename.replace( /\.html?$/i, '' );
			var content = extractPageContent( doc );

			var card = document.createElement( 'div' );
			card.className = 'jst-import-card';

			// Build post type options.
			var ptOptions = Object.keys( jstPtMap ).map( function( k ) {
				return '<option value="' + k + '"' + ( k === 'page' ? ' selected' : '' ) + '>' + jstPtMap[ k ].label + '</option>';
			} ).join( '' );

			// Build template options.
			var tplOptions = Object.keys( jstTemplates ).map( function( k ) {
				var selected = ( k === 'template-full-width.php' ) ? ' selected' : '';
				return '<option value="' + k + '"' + selected + '>' + jstTemplates[ k ] + '</option>';
			} ).join( '' );

			card.innerHTML =
				'<div class="jst-import-card-head">' +
					'<span class="jst-file-name">' + filename + '</span>' +
				'</div>' +
				'<div class="jst-import-card-body">' +
					'<label class="jst-title-row">Page Title' +
						'<input type="text" class="jst-imp-title" value="' + title.replace( /"/g, '&quot;' ) + '">' +
					'</label>' +
					'<label>Post Type<select class="jst-imp-pt">' + ptOptions + '</select></label>' +
					'<label>Template<select class="jst-imp-tpl">' + tplOptions + '</select></label>' +
					'<label>Status<select class="jst-imp-status"><option value="publish" selected>Publish</option><option value="draft">Draft</option></select></label>' +
				'</div>' +
				'<div class="jst-import-card-foot">' +
					'<button type="button" class="button button-primary jst-imp-create-btn">Create Page</button>' +
				'</div>';

			card._jstContent = content;

			card.querySelector( '.jst-imp-create-btn' ).addEventListener( 'click', function() {
				createPage( card );
			} );

			// Detect new head CSS/scripts not already present in the saved
			// Header Scripts box — common when a variant page (e.g. a
			// service-area page) is built on the same base template plus a
			// handful of its own rules.
			var newHeaderContent = jstExtractHeadScripts( doc );
			var headerTextarea   = document.getElementById( 'jst_header_scripts' );
			if ( newHeaderContent && headerTextarea ) {
				var freshBlocks = jstComputeFreshBlocks( newHeaderContent, headerTextarea.value );
				if ( freshBlocks.length ) {
					var diffWrap = document.createElement( 'div' );
					diffWrap.style.cssText = 'margin:0 12px 12px;padding:8px 10px;background:#fff8e5;border:1px solid #f0d896;border-radius:3px;';
					diffWrap.innerHTML = '<strong style="font-size:11px;color:#7a5b00;">' + freshBlocks.length + ' new header rule' + ( freshBlocks.length !== 1 ? 's' : '' ) + ' detected in this file</strong>';
					var diffContainer = document.createElement( 'div' );
					diffWrap.appendChild( diffContainer );
					card.insertBefore( diffWrap, card.querySelector( '.jst-import-card-foot' ) );
				jstRenderFreshBlocksUI( freshBlocks, diffContainer, headerTextarea, 'Header Scripts', function( addition ) {
						card._jstPageHeaderCode = addition;
					}, 'Header Code' );
				}
			}

			// Same detection for footer scripts (bottom-of-body <script> tags).
			var newFooterContent = jstExtractFooterScripts( doc );
			var footerTextarea   = document.getElementById( 'jst_footer_scripts' );
			if ( newFooterContent && footerTextarea ) {
				var freshFooterBlocks = jstComputeFreshBlocks( newFooterContent, footerTextarea.value );
				if ( freshFooterBlocks.length ) {
					var footerDiffWrap = document.createElement( 'div' );
					footerDiffWrap.style.cssText = 'margin:0 12px 12px;padding:8px 10px;background:#fff8e5;border:1px solid #f0d896;border-radius:3px;';
					footerDiffWrap.innerHTML = '<strong style="font-size:11px;color:#7a5b00;">' + freshFooterBlocks.length + ' new footer script' + ( freshFooterBlocks.length !== 1 ? 's' : '' ) + ' detected in this file</strong>';
					var footerDiffContainer = document.createElement( 'div' );
					footerDiffWrap.appendChild( footerDiffContainer );
					card.insertBefore( footerDiffWrap, card.querySelector( '.jst-import-card-foot' ) );
					jstRenderFreshBlocksUI( freshFooterBlocks, footerDiffContainer, footerTextarea, 'Footer Scripts', function( addition ) {
						card._jstPageFooterCode = addition;
					}, 'Footer Code' );
				}
			}

			cardsEl.appendChild( card );
		}

		function outputRow( title, ok, data ) {
			// Clear placeholder on first result.
			var placeholder = outputEl.querySelector( '.jst-out-pending' );
			if ( placeholder ) { placeholder.remove(); }

			var row = document.createElement( 'div' );
			row.className = 'jst-out-row';

			if ( ok ) {
				var editHref = jstRestUrl.replace( /\/wp-json\/?$/, '' ) + '/wp-admin/post.php?post=' + data.id + '&action=edit';
				row.innerHTML =
					'<span class="jst-out-icon">✓</span>' +
					'<span class="jst-out-title">' + title + '</span>' +
					'<span class="jst-out-links">' +
						'<a href="' + ( data.link || '#' ) + '" target="_blank">View</a>' +
						'<a href="' + editHref + '" target="_blank">Edit</a>' +
					'</span>';
			} else {
				row.innerHTML =
					'<span class="jst-out-icon">✗</span>' +
					'<span class="jst-out-title">' + title + '</span>' +
					'<span class="jst-out-err">' + ( data.message || 'Error' ) + '</span>';
			}
			outputEl.appendChild( row );
		}

		function updateBulkStatus() {
			if ( bulkTotal === 0 ) { return; }
			bulkStatus.textContent = bulkDone + ' / ' + bulkTotal + ' done';
			if ( bulkDone === bulkTotal ) {
				bulkStatus.textContent = '✓ All ' + bulkTotal + ' page' + ( bulkTotal !== 1 ? 's' : '' ) + ' created';
			}
		}

		function createPage( card, isBulk ) {
			var btn     = card.querySelector( '.jst-imp-create-btn' );
			var title   = card.querySelector( '.jst-imp-title' ).value.trim();
			var pt      = card.querySelector( '.jst-imp-pt' ).value;
			var tpl     = card.querySelector( '.jst-imp-tpl' ).value;
			var status  = card.querySelector( '.jst-imp-status' ).value;
			var content = card._jstContent;

			var restBase = jstPtMap[ pt ] ? jstPtMap[ pt ].rest_base : 'pages';
			var endpoint = jstRestUrl + 'wp/v2/' + restBase;

			btn.disabled    = true;
			btn.textContent = 'Creating…';

			var payload = { title: title, content: content, status: status, template: tpl };
			if ( card._jstPageHeaderCode || card._jstPageFooterCode ) {
				payload.meta = {};
				if ( card._jstPageHeaderCode ) { payload.meta._jst_page_header_code = card._jstPageHeaderCode; }
				if ( card._jstPageFooterCode ) { payload.meta._jst_page_footer_code = card._jstPageFooterCode; }
			}

			fetch( endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': jstRestNonce },
				body: JSON.stringify( payload ),
			} )
			.then( function( res ) { return res.json().then( function( d ) { return { ok: res.ok, data: d }; } ); } )
			.then( function( r ) {
				if ( r.ok ) {
					btn.textContent = '✓ Done';
					outputRow( title, true, r.data );
				} else {
					btn.disabled    = false;
					btn.textContent = 'Retry';
					outputRow( title, false, r.data );
				}
				if ( isBulk ) { bulkDone++; updateBulkStatus(); }
			} )
			.catch( function( err ) {
				btn.disabled    = false;
				btn.textContent = 'Retry';
				outputRow( title, false, { message: err.message } );
				if ( isBulk ) { bulkDone++; updateBulkStatus(); }
			} );
		}

		document.getElementById( 'jst-import-create-all' ).addEventListener( 'click', function() {
			var cards = cardsEl.querySelectorAll( '.jst-import-card' );
			var pending = [];
			cards.forEach( function( card ) {
				if ( ! card.querySelector( '.jst-imp-create-btn' ).disabled ) { pending.push( card ); }
			} );
			if ( ! pending.length ) { return; }
			bulkTotal = pending.length;
			bulkDone  = 0;
			bulkStatus.textContent = '0 / ' + bulkTotal + ' done';
			pending.forEach( function( card ) { createPage( card, true ); } );
		} );
	} )();

	// ── Menu tab: plain-language outline — add/remove items, saves immediately ──
	( function() {
		var loadBtn        = document.getElementById( 'jst-menu-load-btn' );
		var statusEl       = document.getElementById( 'jst-menu-load-status' );
		var outlineEl      = document.getElementById( 'jst-menu-outline' );
		var addTopLevelRow = document.getElementById( 'jst-menu-add-toplevel-row' );
		var navArea        = document.getElementById( 'jst_navigation' );

		// doc: the live parsed nav fragment (single source of truth — every
		// action mutates this, then re-derives the outline from it fresh,
		// since cloneNode() doesn't carry over the _jstMatch tags below).
		// manualDropdowns: top-level dropdowns created via "Add Top-Level
		// Item" that don't have 2+ children yet, so normal detection
		// (which requires a repeated pattern) can't find them on its own.
		var jstMenuState = { doc: null, allItems: [], itemsById: {}, byPath: {}, manualDropdowns: [] };

		function jstMenuFragmentToDoc( str ) {
			return ( new DOMParser() ).parseFromString( '<!doctype html><body>' + str + '</body>', 'text/html' );
		}

		function jstMenuPathname( url ) {
			try {
				var u = new URL( url, window.location.href );
				return u.pathname.replace( /\/+$/, '' ) || '/';
			} catch ( e ) { return url; }
		}

		function jstMenuDecodeEntities( str ) {
			var ta = document.createElement( 'textarea' );
			ta.innerHTML = str;
			return ta.value;
		}

		function jstMenuEscapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		// Fetches published items for every post type in jstPtMap (already
		// public + REST-enabled, computed server-side — same map the Import
		// Templates tab uses for its post-type dropdown).
		function jstMenuFetchAllItems() {
			var types = Object.keys( jstPtMap );
			return Promise.all( types.map( function( pt ) {
				var restBase = jstPtMap[ pt ].rest_base;
				var url = jstRestUrl + 'wp/v2/' + restBase + '?per_page=100&status=publish&orderby=title&order=asc&_fields=id,link,title';
				return fetch( url, { headers: { 'X-WP-Nonce': jstRestNonce } } )
					.then( function( res ) { return res.ok ? res.json() : []; } )
					.then( function( list ) {
						return ( list || [] ).map( function( item ) {
							return {
								id: item.id,
								postType: pt,
								title: jstMenuDecodeEntities( item.title && item.title.rendered ? item.title.rendered : '(untitled)' ),
								link: item.link,
								pathname: jstMenuPathname( item.link ),
							};
						} );
					} )
					.catch( function() { return []; } );
			} ) ).then( function( results ) {
				return results.reduce( function( acc, list ) { return acc.concat( list ); }, [] );
			} );
		}

		// Tags each <a> in the nav doc whose href matches a real permalink
		// (compared by pathname, so relative hrefs match absolute REST links).
		// Re-run after every mutation — cloneNode() doesn't copy this tag.
		function jstMenuMatchAnchors( doc, byPath ) {
			return Array.from( doc.querySelectorAll( 'a[href]' ) ).filter( function( a ) {
				var href = a.getAttribute( 'href' );
				if ( ! href || href.charAt( 0 ) === '#' ) { return false; }
				var match = byPath[ jstMenuPathname( href ) ];
				if ( match ) { a._jstMatch = match; return true; }
				return false;
			} );
		}

		// True when `a` sits next to a sibling that itself holds multiple
		// links — the signature of a dropdown *toggle* (its sibling is the
		// dropdown panel), not a plain content item. Used to keep toggle
		// anchors (which may themselves be real, matchable links, e.g.
		// <a href="/services/">Services</a>) out of being clustered as if
		// they were list items — including when two different dropdowns'
		// toggles happen to share an identical class.
		function jstMenuLooksLikeToggle( a ) {
			var parent = a.parentElement;
			if ( ! parent ) { return false; }
			return Array.from( parent.children ).some( function( sib ) {
				return sib !== a && sib.querySelectorAll( 'a' ).length >= 2;
			} );
		}

		// Clusters content-linked anchors that share the same class — the
		// repeated-sibling pattern of a real dropdown list. Post type is
		// deliberately not part of the grouping key: an item added via
		// "+Add → All types" gets cloned with the exact same class as its
		// siblings, and needs to still cluster with them even though its
		// post type differs.
		function jstMenuClusterChildLists( matchedAnchors ) {
			var clusters = {};
			matchedAnchors.forEach( function( a ) {
				if ( jstMenuLooksLikeToggle( a ) ) { return; }
				var key = a.getAttribute( 'class' ) || '';
				( clusters[ key ] = clusters[ key ] || [] ).push( a );
			} );
			var lists = [];
			Object.keys( clusters ).forEach( function( key ) {
				if ( clusters[ key ].length < 2 ) { return; } // require an actual repeated pattern
				var list = jstMenuBuildChildList( clusters[ key ] );
				if ( list ) { lists.push( list ); }
			} );
			return lists;
		}

		// Post type shown for the group defaults to whichever type is most
		// common among its members — used only to pre-select the "+Add"
		// filter, not to constrain what can be added.
		function jstMenuMajorityPostType( anchors ) {
			var counts = {};
			anchors.forEach( function( a ) { var pt = a._jstMatch.postType; counts[ pt ] = ( counts[ pt ] || 0 ) + 1; } );
			var best = null, bestCount = 0;
			Object.keys( counts ).forEach( function( pt ) { if ( counts[ pt ] > bestCount ) { bestCount = counts[ pt ]; best = pt; } } );
			return best;
		}

		// Decides the clone unit: the <a> itself when matched anchors are
		// direct siblings (this theme's dropdowns), or a shared wrapper
		// (e.g. <li>) when each anchor has its own repeated parent instead.
		// Returns null when anchors sharing a class don't actually share a
		// real container (e.g. two unrelated links in different dropdowns
		// that happen to use the same classes, like "All Services" and
		// "All Service Areas") — that's not a real list, so the caller
		// drops it and its anchors fall through as standalone links instead
		// of being stitched into a bogus group.
		function jstMenuBuildChildList( anchors ) {
			var firstParent    = anchors[ 0 ].parentElement;
			var directSiblings = anchors.every( function( a ) { return a.parentElement === firstParent; } );

			var cloneUnit, container, itemNodes;
			if ( directSiblings ) {
				cloneUnit = 'anchor';
				container = firstParent;
				itemNodes = anchors.slice();
			} else {
				var wrappers = anchors.map( function( a ) { return a.parentElement; } );
				var wTag    = wrappers[ 0 ] ? wrappers[ 0 ].tagName : null;
				var wClass  = wrappers[ 0 ] ? ( wrappers[ 0 ].getAttribute( 'class' ) || '' ) : '';
				var wParent = wrappers[ 0 ] ? wrappers[ 0 ].parentElement : null;
				var consistent = wrappers.every( function( w ) {
					return w && w.tagName === wTag && ( w.getAttribute( 'class' ) || '' ) === wClass && w.parentElement === wParent;
				} );
				if ( ! consistent ) { return null; }
				cloneUnit = 'wrapper';
				container = wParent;
				itemNodes = wrappers;
			}

			var entries = anchors.map( function( a, i ) {
				return { node: itemNodes[ i ], id: a._jstMatch.id, anchor: a };
			} );

			return {
				postType:  jstMenuMajorityPostType( anchors ),
				cloneUnit: cloneUnit,
				container: container,
				entries:   entries,
			};
		}

		function jstMenuOverlap( idsA, idsB ) {
			var setA = {};
			idsA.forEach( function( id ) { setA[ id ] = true; } );
			var inter = idsB.filter( function( id ) { return setA[ id ]; } ).length;
			var unionSize = ( new Set( idsA.concat( idsB ) ) ).size;
			return unionSize === 0 ? 0 : inter / unionSize;
		}

		// Finds a child list's toggle by walking up its ancestors and, at each
		// level, checking that level's own preceding siblings for a
		// button/summary/link (either the sibling itself, e.g. a toggle <a>
		// right before the dropdown panel, or nested inside it, e.g. a <summary>
		// wrapping its own <a>). Deliberately a *tight* sibling-only search —
		// not a wide scan of everything before it in the document — so it
		// can't wander off and grab an unrelated, distant link (e.g. a utility
		// bar item) just because it happens to precede the container somewhere
		// far up the tree. No toggle found nearby ⇒ this isn't a real
		// dropdown — caller falls back to treating each anchor standalone.
		function jstMenuFindToggle( containerNode ) {
			var node = containerNode;
			for ( var depth = 0; depth < 6 && node; depth++ ) {
				var sib = node.previousElementSibling;
				while ( sib ) {
					if ( /^(BUTTON|SUMMARY|A)$/.test( sib.tagName ) ) {
						return { toggle: sib, wrapper: node.parentElement };
					}
					var nested = sib.querySelector( 'button, summary, a' );
					if ( nested ) {
						return { toggle: nested, wrapper: node.parentElement };
					}
					sib = sib.previousElementSibling;
				}
				node = node.parentElement;
			}
			return null;
		}

		function jstMenuToggleLabel( toggle ) {
			var t = ( toggle.textContent || '' ).replace( /\s+/g, ' ' ).trim();
			return t || '(untitled)';
		}

		// Pairs up dropdown records that are likely the same list rendered
		// twice (desktop nav + mobile <details> mirror) so one Add/Remove
		// action drives both — matched by linked-item overlap, not markup.
		function jstMenuPairDropdowns( records ) {
			// Score every same-postType pair up front, then assign highest-
			// confidence matches first — a "first reasonable match wins" pass
			// (processing records in order, each grabbing its own best partner)
			// can leave a clean 100%-overlap pair unmerged if an earlier record
			// happens to claim one of them first. Scoring globally avoids that.
			var candidates = [];
			for ( var i = 0; i < records.length; i++ ) {
				for ( var j = i + 1; j < records.length; j++ ) {
					if ( records[ i ].childList.postType !== records[ j ].childList.postType ) { continue; }
					var idsA = records[ i ].childList.entries.map( function( e ) { return e.id; } );
					var idsB = records[ j ].childList.entries.map( function( e ) { return e.id; } );
					var score = jstMenuOverlap( idsA, idsB );
					if ( score >= 0.8 ) { candidates.push( { i: i, j: j, score: score } ); }
				}
			}
			candidates.sort( function( a, b ) { return b.score - a.score; } );

			var partnerOf = {};
			var claimed   = {};
			candidates.forEach( function( c ) {
				if ( claimed[ c.i ] || claimed[ c.j ] ) { return; }
				claimed[ c.i ] = true;
				claimed[ c.j ] = true;
				partnerOf[ c.i ] = c.j;
			} );

			var used  = {};
			var pairs = [];
			records.forEach( function( r, i ) {
				if ( used[ i ] ) { return; }
				used[ i ] = true;
				var label = jstMenuToggleLabel( r.toggle );
				if ( partnerOf.hasOwnProperty( i ) ) {
					var j = partnerOf[ i ];
					used[ j ] = true;
					pairs.push( { label: label, postType: r.childList.postType, members: [ r.childList, records[ j ].childList ], wrapperNodes: [ r.wrapper, records[ j ].wrapper ], toggleNodes: [ r.toggle, records[ j ].toggle ] } );
				} else {
					pairs.push( { label: label, postType: r.childList.postType, members: [ r.childList ], wrapperNodes: [ r.wrapper ], toggleNodes: [ r.toggle ] } );
				}
			} );
			return pairs;
		}

		// Builds the plain outline: top-level dropdowns (with their children)
		// + standalone top-level links (anchors not absorbed into a dropdown).
		function jstMenuDetectTopLevel( doc, matchedAnchors ) {
			var childLists = jstMenuClusterChildLists( matchedAnchors );
			var records = [];
			var consumed = new Set();

			childLists.forEach( function( cl ) {
				var found = jstMenuFindToggle( cl.container );
				if ( found ) {
					records.push( { childList: cl, toggle: found.toggle, wrapper: found.wrapper } );
					cl.entries.forEach( function( e ) { consumed.add( e.anchor ); } );
					// The toggle itself may also be a real link (e.g. "Services"
					// pointing at /services/) — don't let it double up as its
					// own standalone item alongside the dropdown it belongs to.
					if ( 'A' === found.toggle.tagName ) { consumed.add( found.toggle ); }
					else { var innerA = found.toggle.querySelector( 'a' ); if ( innerA ) { consumed.add( innerA ); } }
				}
			} );

			var pairs = jstMenuPairDropdowns( records );

			var standalone = matchedAnchors.filter( function( a ) { return ! consumed.has( a ); } );
			var linkGroups = {};
			standalone.forEach( function( a ) {
				var id = a._jstMatch.id;
				( linkGroups[ id ] = linkGroups[ id ] || [] ).push( a );
			} );
			var linkItems = Object.keys( linkGroups ).map( function( id ) {
				var anchors = linkGroups[ id ];
				var first = anchors[ 0 ];
				return {
					kind: 'link',
					label: ( first.textContent || '' ).replace( /\s+/g, ' ' ).trim() || first._jstMatch.title,
					postType: first._jstMatch.postType,
					itemId: first._jstMatch.id,
					anchors: anchors,
				};
			} );

			var dropdownItems = pairs.map( function( p ) {
				return {
					kind: 'dropdown',
					label: p.label,
					postType: p.postType,
					members: p.members,
					wrapperNodes: p.wrapperNodes,
					toggleNodes: p.toggleNodes,
				};
			} );

			return dropdownItems.concat( linkItems );
		}

		// Merges freshly-detected top-level items with any manually-created
		// empty dropdowns that can't be auto-detected yet (see jstMenuState
		// comment above) — dropping a manual entry once it either graduates
		// (auto-detection now finds it, having gained 2+ children) or was
		// removed from the DOM entirely.
		function jstMenuGetTopLevel() {
			var matched = jstMenuMatchAnchors( jstMenuState.doc, jstMenuState.byPath );
			var auto = jstMenuDetectTopLevel( jstMenuState.doc, matched );

			var autoContainers = [];
			auto.forEach( function( it ) {
				if ( 'dropdown' === it.kind ) {
					it.members.forEach( function( m ) { autoContainers.push( m.container ); } );
				}
			} );

			jstMenuState.manualDropdowns = jstMenuState.manualDropdowns.filter( function( md ) {
				var stillInDom = md.wrapperNodes.some( function( w ) { return w && w.parentNode; } );
				if ( ! stillInDom ) { return false; }
				return md.members.every( function( m ) { return -1 === autoContainers.indexOf( m.container ); } );
			} );

			return auto.concat( jstMenuState.manualDropdowns );
		}

		function jstMenuBuildCloneNode( member, templateNode, item ) {
			var clone = templateNode.cloneNode( true );
			var a = 'anchor' === member.cloneUnit ? clone : clone.querySelector( 'a[href]' );
			if ( a ) {
				var currentHref = a.getAttribute( 'href' ) || '';
				var relative    = '/' === currentHref.charAt( 0 );
				var href        = item.link;
				try { if ( relative ) { href = new URL( item.link ).pathname; } } catch ( e ) {}
				a.setAttribute( 'href', href );
				a.textContent = item.title;
			}
			return clone;
		}

		// Maps a known descendant of `originalRoot` to the corresponding node
		// inside `clonedRoot` (a deep clone of originalRoot) by child-index
		// path — used after cloning a whole dropdown wrapper to find its
		// toggle/child-container inside the copy.
		function jstMenuFindClonedNode( originalRoot, clonedRoot, target ) {
			var path = [];
			var node = target;
			while ( node && node !== originalRoot ) {
				var parent = node.parentElement;
				if ( ! parent ) { return null; }
				path.unshift( Array.prototype.indexOf.call( parent.children, node ) );
				node = parent;
			}
			if ( node !== originalRoot ) { return null; }
			var result = clonedRoot;
			for ( var i = 0; i < path.length; i++ ) {
				result = result.children[ path[ i ] ];
				if ( ! result ) { return null; }
			}
			return result;
		}

		function jstMenuSave() {
			var html = jstMenuState.doc.body.innerHTML.trim();
			if ( navArea ) { navArea.value = html; }
			return fetch( jstRestUrl + 'jst/v1/options', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': jstRestNonce },
				body: JSON.stringify( { jst_navigation: html } ),
			} ).then( function( res ) {
				return res.json().then( function( d ) { return { ok: res.ok, data: d }; } );
			} );
		}

		function jstMenuRender() {
			var topLevel = jstMenuGetTopLevel();
			jstMenuRenderAddTopLevelControl( topLevel );
			outlineEl.innerHTML = '';
			if ( ! topLevel.length ) {
				outlineEl.innerHTML = '<p style="color:#646970;font-size:12px;">No menu items linking to real pages were found.</p>';
				return;
			}
			topLevel.forEach( function( item ) { outlineEl.appendChild( jstMenuBuildTopLevelRow( item ) ); } );
		}

		function jstMenuAddChildren( item, items, statusEl2 ) {
			var anyApplied  = false;
			var skippedEmpty = false;

			item.members.forEach( function( member ) {
				if ( ! member.entries.length ) { skippedEmpty = true; return; }
				var lastNode = member.entries[ member.entries.length - 1 ].node;
				var templateClone = lastNode.cloneNode( true );
				var insertAfter = lastNode;
				items.forEach( function( it ) {
					var clone = jstMenuBuildCloneNode( member, templateClone, it );
					if ( insertAfter.nextSibling ) { insertAfter.parentNode.insertBefore( clone, insertAfter.nextSibling ); }
					else { insertAfter.parentNode.appendChild( clone ); }
					member.entries.push( { node: clone, id: it.id, anchor: 'anchor' === member.cloneUnit ? clone : clone.querySelector( 'a[href]' ) } );
					insertAfter = clone;
				} );
				anyApplied = true;
			} );

			if ( ! anyApplied ) {
				statusEl2.textContent = 'Can’t add yet — this item has no existing entries to copy the style from.';
				return;
			}

			statusEl2.textContent = 'Saving…';
			jstMenuSave().then( function( r ) {
				statusEl2.textContent = r.ok ? ( '✓ Added' + ( skippedEmpty ? ' (one location had nothing to copy from, so it was skipped there)' : '' ) ) : ( 'Save failed: ' + ( r.data && r.data.message ? r.data.message : 'unknown error' ) );
				jstMenuRender();
			} ).catch( function( err ) {
				statusEl2.textContent = 'Save failed: ' + err.message;
			} );
		}

		function jstMenuRemoveChild( item, id ) {
			item.members.forEach( function( member ) {
				member.entries.forEach( function( e ) {
					if ( e.id === id && e.node.parentNode ) { e.node.parentNode.removeChild( e.node ); }
				} );
			} );
			jstMenuSave().then( function() { jstMenuRender(); } );
		}

		function jstMenuRemoveTopLevel( item ) {
			if ( 'dropdown' === item.kind ) {
				item.wrapperNodes.forEach( function( w ) { if ( w && w.parentNode ) { w.parentNode.removeChild( w ); } } );
			} else {
				item.anchors.forEach( function( a ) { if ( a.parentNode ) { a.parentNode.removeChild( a ); } } );
			}
			jstMenuSave().then( function() { jstMenuRender(); } );
		}

		function jstMenuOpenAddPanel( item, container, statusEl2 ) {
			var existing = container.querySelector( '.jst-menu-add-panel' );
			if ( existing ) { existing.remove(); return; }

			var primaryMember = item.members[ 0 ];
			var presentIds = primaryMember.entries.map( function( e ) { return e.id; } );

			var panel = document.createElement( 'div' );
			panel.className = 'jst-menu-add-panel';
			panel.style.cssText = 'background:#fff;border:1px solid #dcdcde;border-radius:3px;padding:6px;margin-top:6px;';

			var typeLabel = document.createElement( 'label' );
			typeLabel.style.cssText = 'display:flex;gap:6px;align-items:center;font-size:11px;color:#646970;margin-bottom:6px;';
			typeLabel.appendChild( document.createTextNode( 'Show:' ) );
			var typeSelect = document.createElement( 'select' );
			var sameTypeOpt = document.createElement( 'option' );
			sameTypeOpt.value = item.postType;
			sameTypeOpt.textContent = ( jstPtMap[ item.postType ] ? jstPtMap[ item.postType ].label : item.postType ) + ' (same as this list)';
			typeSelect.appendChild( sameTypeOpt );
			var allOpt = document.createElement( 'option' );
			allOpt.value = '';
			allOpt.textContent = 'All types';
			typeSelect.appendChild( allOpt );
			typeLabel.appendChild( typeSelect );
			panel.appendChild( typeLabel );

			var listEl = document.createElement( 'div' );
			listEl.style.cssText = 'max-height:200px;overflow-y:auto;';
			panel.appendChild( listEl );

			function renderList() {
				listEl.innerHTML = '';
				var wantType = typeSelect.value;
				var candidates = jstMenuState.allItems.filter( function( it ) {
					return ( ! wantType || it.postType === wantType ) && -1 === presentIds.indexOf( it.id );
				} );

				if ( ! candidates.length ) {
					listEl.innerHTML = '<p style="font-size:11px;color:#646970;margin:2px 0;">Nothing left to add here.</p>';
					return;
				}

				candidates.forEach( function( it ) {
					var row = document.createElement( 'label' );
					row.style.cssText = 'display:flex;gap:6px;align-items:center;padding:4px 0;font-size:12px;border-bottom:1px solid #f0f0f1;';
					var cb = document.createElement( 'input' );
					cb.type = 'checkbox';
					cb.dataset.itemId = it.id;
					row.appendChild( cb );
					var label = it.title + ( ! wantType && it.postType !== item.postType ? ' (' + ( jstPtMap[ it.postType ] ? jstPtMap[ it.postType ].label : it.postType ) + ')' : '' );
					row.appendChild( document.createTextNode( label ) );
					listEl.appendChild( row );
				} );
			}
			typeSelect.addEventListener( 'change', renderList );
			renderList();

			var confirmBtn = document.createElement( 'button' );
			confirmBtn.type = 'button';
			confirmBtn.className = 'button button-primary button-small';
			confirmBtn.textContent = 'Add Checked';
			confirmBtn.style.marginTop = '6px';
			panel.appendChild( confirmBtn );

			confirmBtn.addEventListener( 'click', function() {
				var checked = Array.from( listEl.querySelectorAll( 'input:checked' ) )
					.map( function( cb ) { return jstMenuState.itemsById[ parseInt( cb.dataset.itemId, 10 ) ]; } )
					.filter( Boolean );
				if ( ! checked.length ) { return; }
				jstMenuAddChildren( item, checked, statusEl2 );
			} );

			container.appendChild( panel );
		}

		function jstMenuBuildTopLevelRow( item ) {
			var row = document.createElement( 'div' );
			row.className = 'jst-opts-extracted';

			var head = document.createElement( 'div' );
			head.className = 'jst-opts-ext-header';
			var titleSpan = document.createElement( 'span' );
			titleSpan.textContent = item.label;
			head.appendChild( titleSpan );

			var removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button button-small';
			removeBtn.style.marginLeft = 'auto';
			removeBtn.textContent = '✕ Remove';
			removeBtn.addEventListener( 'click', function() {
				if ( ! window.confirm( 'Remove "' + item.label + '" from the menu?' ) ) { return; }
				jstMenuRemoveTopLevel( item );
			} );
			head.appendChild( removeBtn );
			row.appendChild( head );

			var body = document.createElement( 'div' );
			body.style.cssText = 'padding:8px 10px;';
			row.appendChild( body );

			if ( 'link' === item.kind ) {
				var p = document.createElement( 'p' );
				p.style.cssText = 'font-size:12px;color:#646970;margin:0;';
				var target = jstMenuState.itemsById[ item.itemId ];
				p.textContent = 'Links to: ' + ( target ? target.title : item.itemId );
				body.appendChild( p );
				return row;
			}

			var list = document.createElement( 'div' );
			list.style.cssText = 'margin-bottom:8px;';
			var primaryMember = item.members[ 0 ];
			primaryMember.entries.forEach( function( entry ) {
				var childRow = document.createElement( 'div' );
				childRow.className = 'jst-out-row';
				var name = document.createElement( 'span' );
				name.className = 'jst-out-title';
				// Show what's actually on the site (the link's own text) —
				// falls back to the page title only if the link is somehow
				// empty (e.g. icon-only), so it never shows a mismatched label.
				var ownText = entry.anchor ? ( entry.anchor.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';
				var known = jstMenuState.itemsById[ entry.id ];
				name.textContent = ownText || ( known ? known.title : '(unknown)' );
				childRow.appendChild( name );
				var xBtn = document.createElement( 'button' );
				xBtn.type = 'button';
				xBtn.className = 'button button-small';
				xBtn.textContent = '✕';
				xBtn.addEventListener( 'click', function() {
					if ( ! window.confirm( 'Remove "' + name.textContent + '" from ' + item.label + '?' ) ) { return; }
					jstMenuRemoveChild( item, entry.id );
				} );
				childRow.appendChild( xBtn );
				list.appendChild( childRow );
			} );
			if ( ! primaryMember.entries.length ) {
				list.innerHTML = '<p style="font-size:11px;color:#646970;margin:2px 0;">No items yet.</p>';
			}
			body.appendChild( list );

			var addWrap = document.createElement( 'div' );
			body.appendChild( addWrap );
			var addBtn = document.createElement( 'button' );
			addBtn.type = 'button';
			addBtn.className = 'button button-secondary button-small';
			addBtn.textContent = '+ Add';
			addWrap.appendChild( addBtn );
			var addStatus = document.createElement( 'span' );
			addStatus.style.cssText = 'font-size:11px;color:#646970;margin-left:8px;';
			addWrap.appendChild( addStatus );

			addBtn.addEventListener( 'click', function() { jstMenuOpenAddPanel( item, addWrap, addStatus ); } );

			return row;
		}

		function jstMenuRenderAddTopLevelControl( topLevel ) {
			addTopLevelRow.innerHTML = '';
			addTopLevelRow.style.display = topLevel.length ? 'block' : 'none';
			if ( ! topLevel.length ) { return; }

			var toggleBtn = document.createElement( 'button' );
			toggleBtn.type = 'button';
			toggleBtn.className = 'button button-secondary';
			toggleBtn.textContent = '+ Add Top-Level Item';
			addTopLevelRow.appendChild( toggleBtn );

			var panel = document.createElement( 'div' );
			panel.style.cssText = 'display:none;margin-top:8px;padding:10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;';
			addTopLevelRow.appendChild( panel );

			toggleBtn.addEventListener( 'click', function() {
				var show = 'none' === panel.style.display;
				panel.style.display = show ? 'block' : 'none';
				if ( show ) { buildPanel(); }
			} );

			function buildPanel() {
				panel.innerHTML = '';

				var pickLabel = document.createElement( 'label' );
				pickLabel.style.cssText = 'font-size:12px;display:flex;flex-direction:column;gap:4px;margin-bottom:8px;';
				pickLabel.appendChild( document.createTextNode( 'Copy the style of:' ) );
				var select = document.createElement( 'select' );
				topLevel.forEach( function( it, i ) {
					var opt = document.createElement( 'option' );
					opt.value = String( i );
					opt.textContent = it.label + ( 'dropdown' === it.kind ? ' (dropdown)' : ' (link)' );
					select.appendChild( opt );
				} );
				pickLabel.appendChild( select );
				panel.appendChild( pickLabel );

				var labelInput = document.createElement( 'input' );
				labelInput.type = 'text';
				labelInput.placeholder = 'New item label, e.g. Financing';
				labelInput.style.cssText = 'width:100%;margin-bottom:8px;box-sizing:border-box;';
				panel.appendChild( labelInput );

				var targetWrap = document.createElement( 'div' );
				panel.appendChild( targetWrap );

				function refreshTargetField() {
					targetWrap.innerHTML = '';
					var chosen = topLevel[ parseInt( select.value, 10 ) ];
					if ( 'link' !== chosen.kind ) { return; }
					var targetLabel = document.createElement( 'label' );
					targetLabel.style.cssText = 'font-size:12px;display:flex;flex-direction:column;gap:4px;margin-bottom:8px;';
					targetLabel.appendChild( document.createTextNode( 'Links to:' ) );
					var targetSelect = document.createElement( 'select' );
					targetSelect.className = 'jst-menu-toplevel-target';
					jstMenuState.allItems.forEach( function( it ) {
						var opt = document.createElement( 'option' );
						opt.value = String( it.id );
						opt.textContent = it.title + ' (' + ( jstPtMap[ it.postType ] ? jstPtMap[ it.postType ].label : it.postType ) + ')';
						targetSelect.appendChild( opt );
					} );
					targetLabel.appendChild( targetSelect );
					targetWrap.appendChild( targetLabel );
				}
				refreshTargetField();
				select.addEventListener( 'change', refreshTargetField );

				var addBtn = document.createElement( 'button' );
				addBtn.type = 'button';
				addBtn.className = 'button button-primary button-small';
				addBtn.textContent = 'Add';
				panel.appendChild( addBtn );

				var status = document.createElement( 'span' );
				status.style.cssText = 'font-size:11px;color:#646970;margin-left:8px;';
				panel.appendChild( status );

				addBtn.addEventListener( 'click', function() {
					var chosen = topLevel[ parseInt( select.value, 10 ) ];
					var label  = labelInput.value.trim();
					if ( ! label ) { status.textContent = 'Enter a label first.'; return; }

					if ( 'link' === chosen.kind ) {
						var targetSelect = targetWrap.querySelector( 'select' );
						var targetItem = targetSelect ? jstMenuState.itemsById[ parseInt( targetSelect.value, 10 ) ] : null;
						if ( ! targetItem ) { status.textContent = 'Pick a page first.'; return; }
						chosen.anchors.forEach( function( a ) {
							var clone = a.cloneNode( true );
							var currentHref = a.getAttribute( 'href' ) || '';
							var relative = '/' === currentHref.charAt( 0 );
							var href = targetItem.link;
							try { if ( relative ) { href = new URL( targetItem.link ).pathname; } } catch ( e ) {}
							clone.setAttribute( 'href', href );
							clone.textContent = label;
							a.parentNode.insertBefore( clone, a.nextSibling );
						} );
						status.textContent = 'Saving…';
						jstMenuSave().then( function( r ) {
							status.textContent = r.ok ? '✓ Added' : 'Save failed';
							jstMenuRender();
						} ).catch( function( err ) { status.textContent = 'Save failed: ' + err.message; } );
						return;
					}

					// Dropdown: clone the wrapper, relabel its toggle, empty its
					// child container so the new one starts with zero items —
					// tracked in jstMenuState.manualDropdowns until it has
					// enough children for normal detection to find it on its own.
					var newMembers = [];
					var newWrapperNodes = [];
					chosen.wrapperNodes.forEach( function( w, wi ) {
						var clone = w.cloneNode( true );
						var origToggle = chosen.toggleNodes[ wi ];
						var clonedToggle = jstMenuFindClonedNode( w, clone, origToggle );
						if ( clonedToggle ) { clonedToggle.textContent = label; }
						var member = chosen.members[ wi ];
						var clonedContainer = jstMenuFindClonedNode( w, clone, member.container );
						if ( clonedContainer ) { clonedContainer.innerHTML = ''; }
						w.parentNode.insertBefore( clone, w.nextSibling );
						newWrapperNodes.push( clone );
						newMembers.push( { postType: member.postType, cloneUnit: member.cloneUnit, container: clonedContainer || clone, entries: [] } );
					} );

					jstMenuState.manualDropdowns.push( {
						kind: 'dropdown',
						label: label,
						postType: chosen.postType,
						members: newMembers,
						wrapperNodes: newWrapperNodes,
						toggleNodes: newWrapperNodes.map( function( w ) { return w.querySelector( 'button, summary' ); } ),
					} );

					status.textContent = 'Saving…';
					jstMenuSave().then( function( r ) {
						status.textContent = r.ok ? '✓ Added' : 'Save failed';
						jstMenuRender();
					} ).catch( function( err ) { status.textContent = 'Save failed: ' + err.message; } );
				} );
			}
		}

		loadBtn.addEventListener( 'click', function() {
			var raw = navArea ? navArea.value.trim() : '';
			if ( ! raw ) { statusEl.textContent = 'Header Nav / Menu is empty — nothing to load.'; return; }

			statusEl.textContent = 'Loading…';
			loadBtn.disabled = true;

			var doc = jstMenuFragmentToDoc( raw );

			jstMenuFetchAllItems().then( function( allItems ) {
				jstMenuState.doc = doc;
				jstMenuState.allItems = allItems;
				jstMenuState.itemsById = {};
				allItems.forEach( function( it ) { jstMenuState.itemsById[ it.id ] = it; } );
				jstMenuState.byPath = {};
				allItems.forEach( function( it ) { jstMenuState.byPath[ it.pathname ] = it; } );
				jstMenuState.manualDropdowns = [];

				jstMenuRender();
				statusEl.textContent = '';
				loadBtn.disabled = false;
			} ).catch( function( err ) {
				statusEl.textContent = 'Load failed: ' + err.message;
				loadBtn.disabled = false;
			} );
		} );
	} )();
	</script>
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

		$global_invert   = get_option( 'jst_prose_invert', '' );
		$per_page_invert = is_singular() ? get_post_meta( get_the_ID(), '_jst_prose_invert', true ) : '';
		if ( $global_invert || $per_page_invert ) {
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

	$color_bridge = get_option( 'jst_color_bridge', '' );
	if ( $color_bridge ) {
		echo "\n<style id=\"jst-color-bridge\">\n" . $color_bridge . "\n</style>\n"; // phpcs:ignore -- intentional raw output, admin-trusted.
	}
}
add_action( 'wp_head', 'jst_output_header_scripts' );

/**
 * Output Navigation markup at wp_body_open.
 * Suppressed on pages with _jst_hide_global_nav set.
 */
function jst_output_navigation() {
	if ( is_singular() && get_post_meta( get_the_ID(), '_jst_hide_global_nav', true ) ) {
		return;
	}
	echo do_shortcode( get_option( 'jst_navigation', '' ) ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'wp_body_open', 'jst_output_navigation', 20 );

/**
 * [jst_menu] shortcode — renders the primary WP nav menu.
 * Usage: [jst_menu] or [jst_menu location="primary" ul_class="flex gap-8 list-none"]
 */
function jst_menu_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'location' => 'primary',
		'ul_class' => 'menu',
		'depth'    => 2,
	), $atts, 'jst_menu' );

	return wp_nav_menu( array(
		'theme_location' => $atts['location'],
		'menu_class'     => $atts['ul_class'],
		'container'      => false,
		'depth'          => (int) $atts['depth'],
		'echo'           => false,
		'fallback_cb'    => false,
	) );
}
add_shortcode( 'jst_menu', 'jst_menu_shortcode' );

/**
 * Output Footer HTML markup right before </body>, before footer scripts.
 */
function jst_output_footer() {
	if ( is_singular() && get_post_meta( get_the_ID(), '_jst_hide_global_footer', true ) ) {
		return;
	}
	echo do_shortcode( get_option( 'jst_footer', '' ) ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'jst_before_closing_body', 'jst_output_footer', 10 );

/**
 * Output Footer Scripts right before </body>, after Footer HTML.
 */
function jst_output_footer_scripts() {
	if ( is_singular() && get_post_meta( get_the_ID(), '_jst_hide_global_footer', true ) ) {
		return;
	}
	echo get_option( 'jst_footer_scripts', '' ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_action( 'jst_before_closing_body', 'jst_output_footer_scripts', 20 );

/**
 * ------------------------------------------------------------------
 * REST API: jst/v1/options
 * GET  → returns all JST theme option values
 * POST → updates one or more JST options; writes jst-custom.css file
 * Auth: standard WP REST auth (Application Passwords recommended)
 * ------------------------------------------------------------------
 */
function jst_rest_get_options( WP_REST_Request $request ) {
	$keys = array_merge(
		array_keys( jst_theme_options_fields() ),
		array( 'jst_custom_css', 'jst_disable_tailwind_prose', 'jst_prose_invert' )
	);
	$data = array();
	foreach ( $keys as $key ) {
		$data[ $key ] = get_option( $key, '' );
	}
	return rest_ensure_response( $data );
}

function jst_rest_update_options( WP_REST_Request $request ) {
	$allowed = array_merge(
		array_keys( jst_theme_options_fields() ),
		array( 'jst_custom_css', 'jst_disable_tailwind_prose', 'jst_prose_invert' )
	);
	$updated = array();

	foreach ( $allowed as $key ) {
		if ( ! $request->has_param( $key ) ) {
			continue;
		}
		$value = $request->get_param( $key );
		update_option( $key, $value );
		$updated[] = $key;

		// Write the CSS file when jst_custom_css is updated.
		if ( 'jst_custom_css' === $key ) {
			$upload_dir = wp_upload_dir();
			file_put_contents( $upload_dir['basedir'] . '/jst-custom.css', $value ); // phpcs:ignore
		}
	}

	return rest_ensure_response( array(
		'updated' => $updated,
		'count'   => count( $updated ),
	) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'jst/v1', '/options', array(
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'jst_rest_get_options',
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		),
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'jst_rest_update_options',
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		),
	) );
} );

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
			'jst_page_options',
			__( 'JST: Page Options', 'just-spectacular-theme' ),
			'jst_render_page_options_meta_box',
			$post_type,
			'side',
			'high'
		);

		add_meta_box(
			'jst_page_code',
			__( 'JST: Page Code', 'just-spectacular-theme' ),
			'jst_render_page_code_meta_box',
			$post_type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'jst_add_page_settings_meta_box' );

function jst_render_page_options_meta_box( $post ) {
	wp_nonce_field( 'jst_save_page_settings', 'jst_page_settings_nonce' );

	$width              = get_post_meta( $post->ID, '_jst_page_width', true );
	$disable_style      = get_post_meta( $post->ID, '_jst_disable_theme_style', true );
	$hide_post_meta     = get_post_meta( $post->ID, '_jst_hide_post_meta', true );
	$prose_invert       = get_post_meta( $post->ID, '_jst_prose_invert', true );
	$hide_global_nav    = get_post_meta( $post->ID, '_jst_hide_global_nav', true );
	$hide_global_footer = get_post_meta( $post->ID, '_jst_hide_global_footer', true );
	?>
	<style>
	.jst-tip {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 15px;
		height: 15px;
		border-radius: 50%;
		background: #c3c4c7;
		color: #fff;
		font-size: 10px;
		font-weight: 700;
		line-height: 1;
		cursor: pointer;
		vertical-align: middle;
		margin-left: 4px;
		flex-shrink: 0;
	}
	.jst-tip-text {
		display: none;
		font-size: 11px;
		color: #646970;
		line-height: 1.4;
		margin-top: 4px;
	}
	.jst-tip-text.is-open {
		display: block;
	}
	</style>
	<p>
		<label for="jst_page_width"><strong><?php esc_html_e( 'Width', 'just-spectacular-theme' ); ?></strong>
			<span class="jst-tip" data-target="jst-tip-width">?</span>
		</label><br>
		<input type="text" id="jst_page_width" name="jst_page_width" value="<?php echo esc_attr( $width ); ?>" placeholder="80rem" style="width:100%;" />
		<span id="jst-tip-width" class="jst-tip-text"><?php esc_html_e( 'Max content width. Accepts any CSS value (e.g. 80rem, 1200px, 100%). Defaults to 80rem (100% on Full Width) if blank.', 'just-spectacular-theme' ); ?></span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_prose_invert" value="1" <?php checked( $prose_invert, '1' ); ?> />
			<?php esc_html_e( 'Prose invert', 'just-spectacular-theme' ); ?>
			<span class="jst-tip" data-target="jst-tip-prose">?</span>
		</label>
		<span id="jst-tip-prose" class="jst-tip-text"><?php esc_html_e( 'Flips prose text/heading/link colors to light for dark background pages.', 'just-spectacular-theme' ); ?></span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_hide_post_meta" value="1" <?php checked( $hide_post_meta, '1' ); ?> />
			<?php esc_html_e( 'Hide post meta', 'just-spectacular-theme' ); ?>
			<span class="jst-tip" data-target="jst-tip-meta">?</span>
		</label>
		<span id="jst-tip-meta" class="jst-tip-text"><?php esc_html_e( 'Hides the date/author line on the Full Width — With Title template.', 'just-spectacular-theme' ); ?></span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_hide_global_nav" value="1" <?php checked( $hide_global_nav, '1' ); ?> />
			<?php esc_html_e( 'Hide global nav', 'just-spectacular-theme' ); ?>
			<span class="jst-tip" data-target="jst-tip-nav">?</span>
		</label>
		<span id="jst-tip-nav" class="jst-tip-text"><?php esc_html_e( 'Suppresses the global Header Nav / Menu (Theme Options) on this page.', 'just-spectacular-theme' ); ?></span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_hide_global_footer" value="1" <?php checked( $hide_global_footer, '1' ); ?> />
			<?php esc_html_e( 'Hide global footer', 'just-spectacular-theme' ); ?>
			<span class="jst-tip" data-target="jst-tip-footer">?</span>
		</label>
		<span id="jst-tip-footer" class="jst-tip-text"><?php esc_html_e( 'Suppresses the global Footer HTML and Footer Scripts (Theme Options) on this page.', 'just-spectacular-theme' ); ?></span>
	</p>
	<p>
		<label>
			<input type="checkbox" name="jst_disable_theme_style" value="1" <?php checked( $disable_style, '1' ); ?> />
			<?php esc_html_e( 'Disable theme style.css', 'just-spectacular-theme' ); ?>
			<span class="jst-tip" data-target="jst-tip-style">?</span>
		</label>
		<span id="jst-tip-style" class="jst-tip-text"><?php esc_html_e( 'Removes the theme stylesheet on this page — for fully custom-built pages.', 'just-spectacular-theme' ); ?></span>
	</p>
	<script>
	document.querySelectorAll( '.jst-tip[data-target]' ).forEach( function( btn ) {
		btn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var tip = document.getElementById( btn.dataset.target );
			if ( tip ) { tip.classList.toggle( 'is-open' ); }
		} );
	} );
	</script>
	<?php
}

function jst_render_page_code_meta_box( $post ) {
	$header_code = get_post_meta( $post->ID, '_jst_page_header_code', true );
	$footer_code = get_post_meta( $post->ID, '_jst_page_footer_code', true );
	?>
	<p>
		<label for="jst_page_header_code"><strong><?php esc_html_e( 'Page Header Code', 'just-spectacular-theme' ); ?></strong></label><br>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="style"><?php esc_html_e( 'Insert <style>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="script"><?php esc_html_e( 'Insert <script>', 'just-spectacular-theme' ); ?></button>
		<button type="button" class="button jst-quick-tag-btn" data-target="jst_page_header_code" data-tag="comment"><?php esc_html_e( 'Insert <!-- -->', 'just-spectacular-theme' ); ?></button>
		<br>
		<textarea id="jst_page_header_code" name="jst_page_header_code" rows="8" class="jst-metabox-field" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $header_code ); ?></textarea>
		<br>
		<span class="description">
			<?php esc_html_e( 'Outputs inside <head> — runs in addition to global Header Scripts (Theme Options), not instead of.', 'just-spectacular-theme' ); ?>
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
			<?php esc_html_e( 'Outputs before </body> — runs in addition to global Footer Scripts (Theme Options), not instead of.', 'just-spectacular-theme' ); ?>
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
	update_post_meta( $post_id, '_jst_hide_global_nav', isset( $_POST['jst_hide_global_nav'] ) ? '1' : '' );
	update_post_meta( $post_id, '_jst_hide_global_footer', isset( $_POST['jst_hide_global_footer'] ) ? '1' : '' );
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
 * Register _jst_page_header_code / _jst_page_footer_code for REST on
 * page and post — lets the Import Templates tab set per-page header/
 * footer code at creation time via the standard `meta` request param.
 */
function jst_register_page_code_meta() {
	foreach ( array( 'page', 'post' ) as $post_type ) {
		foreach ( array( '_jst_page_header_code', '_jst_page_footer_code' ) as $meta_key ) {
			register_post_meta(
				$post_type,
				$meta_key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'default'       => '',
					'auth_callback' => function() {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}
}
add_action( 'init', 'jst_register_page_code_meta' );

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

	$part_name    = get_post_meta( $post->ID, '_jst_part_name', true );
	$part_html    = get_post_meta( $post->ID, '_jst_part_html', true );
	$part_location = get_post_meta( $post->ID, '_jst_part_location', true ) ?: 'shortcode_only';
	$part_show_on  = get_post_meta( $post->ID, '_jst_part_show_on', true ) ?: 'all';
	$part_pages    = get_post_meta( $post->ID, '_jst_part_pages', true ) ?: array();
	if ( ! is_array( $part_pages ) ) {
		$part_pages = array();
	}

	$shortcode_preview = $part_name
		? '[jst_part name="' . esc_attr( $part_name ) . '"]'
		: __( '(set a Part Name below to generate the shortcode)', 'just-spectacular-theme' );

	// Fetch all pages/posts for the page selector.
	$all_pages = get_posts( array(
		'post_type'      => get_post_types( array( 'public' => true ) ),
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'exclude'        => array( $post->ID ),
	) );
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

	<hr style="margin:1.5rem 0;">

	<p>
		<label for="jst_part_location"><strong><?php esc_html_e( 'Location', 'just-spectacular-theme' ); ?></strong></label><br>
		<select id="jst_part_location" name="jst_part_location" style="min-width:240px;">
			<option value="shortcode_only" <?php selected( $part_location, 'shortcode_only' ); ?>><?php esc_html_e( 'Shortcode only', 'just-spectacular-theme' ); ?></option>
			<option value="after_body" <?php selected( $part_location, 'after_body' ); ?>><?php esc_html_e( 'After <body> (before global nav)', 'just-spectacular-theme' ); ?></option>
			<option value="before_body_end" <?php selected( $part_location, 'before_body_end' ); ?>><?php esc_html_e( 'Before </body> (before footer)', 'just-spectacular-theme' ); ?></option>
		</select>
		<br>
		<span class="description">
			<?php esc_html_e( 'Auto-output this part at a fixed location on the page — no shortcode needed. "Shortcode only" disables auto-output.', 'just-spectacular-theme' ); ?>
		</span>
	</p>

	<p id="jst_show_on_wrap">
		<label><strong><?php esc_html_e( 'Show on', 'just-spectacular-theme' ); ?></strong></label><br>
		<label style="margin-right:1rem;">
			<input type="radio" name="jst_part_show_on" value="all" <?php checked( $part_show_on, 'all' ); ?>>
			<?php esc_html_e( 'All pages', 'just-spectacular-theme' ); ?>
		</label>
		<label>
			<input type="radio" name="jst_part_show_on" value="specific" <?php checked( $part_show_on, 'specific' ); ?>>
			<?php esc_html_e( 'Specific pages', 'just-spectacular-theme' ); ?>
		</label>
		<br>
		<span class="description">
			<?php esc_html_e( 'Only applies when a Location is set above.', 'just-spectacular-theme' ); ?>
		</span>
	</p>

	<div id="jst_part_pages_wrap" style="<?php echo 'specific' === $part_show_on ? '' : 'display:none;'; ?>margin-left:1rem;max-height:200px;overflow-y:auto;border:1px solid #dcdcde;padding:8px;border-radius:3px;background:#fff;">
		<?php foreach ( $all_pages as $p ) : ?>
			<label style="display:block;padding:2px 0;">
				<input type="checkbox" name="jst_part_pages[]" value="<?php echo esc_attr( $p->ID ); ?>" <?php checked( in_array( (string) $p->ID, array_map( 'strval', $part_pages ), true ) ); ?>>
				<?php echo esc_html( $p->post_title ); ?>
				<span style="color:#999;font-size:11px;">(<?php echo esc_html( $p->post_type ); ?>)</span>
			</label>
		<?php endforeach; ?>
	</div>

	<script>
	( function() {
		var locationEl = document.getElementById( 'jst_part_location' );
		var showOnWrap = document.getElementById( 'jst_show_on_wrap' );
		var pagesWrap  = document.getElementById( 'jst_part_pages_wrap' );
		var radios     = document.querySelectorAll( 'input[name="jst_part_show_on"]' );

		function toggleShowOn() {
			var isAuto = locationEl.value !== 'shortcode_only';
			showOnWrap.style.display = isAuto ? '' : 'none';
			pagesWrap.style.display  = ( isAuto && document.querySelector( 'input[name="jst_part_show_on"]:checked' ).value === 'specific' ) ? '' : 'none';
		}

		function togglePages() {
			pagesWrap.style.display = this.value === 'specific' && locationEl.value !== 'shortcode_only' ? '' : 'none';
		}

		locationEl.addEventListener( 'change', toggleShowOn );
		radios.forEach( function( r ) { r.addEventListener( 'change', togglePages ); } );
		toggleShowOn();
	} )();
	</script>
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

	$allowed_locations = array( 'shortcode_only', 'after_body', 'before_body_end' );
	$location = isset( $_POST['jst_part_location'] ) ? sanitize_text_field( wp_unslash( $_POST['jst_part_location'] ) ) : 'shortcode_only';
	update_post_meta( $post_id, '_jst_part_location', in_array( $location, $allowed_locations, true ) ? $location : 'shortcode_only' );

	$show_on = isset( $_POST['jst_part_show_on'] ) && 'specific' === $_POST['jst_part_show_on'] ? 'specific' : 'all';
	update_post_meta( $post_id, '_jst_part_show_on', $show_on );

	$pages = isset( $_POST['jst_part_pages'] ) && is_array( $_POST['jst_part_pages'] )
		? array_map( 'absint', $_POST['jst_part_pages'] )
		: array();
	update_post_meta( $post_id, '_jst_part_pages', $pages );
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
	return do_shortcode( $html ); // phpcs:ignore -- intentional raw output, admin-trusted.
}
add_shortcode( 'jst_part', 'jst_part_shortcode' );

/**
 * Fetch all published jst_part posts set to a given location,
 * filtered by show_on setting, and output their HTML.
 */
function jst_output_parts_at_location( $location ) {
	$parts = get_posts( array(
		'post_type'      => 'jst_part',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'   => '_jst_part_location',
				'value' => $location,
			),
		),
		'no_found_rows'  => true,
	) );

	if ( empty( $parts ) ) {
		return;
	}

	$current_id = is_singular() ? get_the_ID() : 0;

	foreach ( $parts as $part ) {
		$show_on = get_post_meta( $part->ID, '_jst_part_show_on', true ) ?: 'all';

		if ( 'specific' === $show_on ) {
			$pages = get_post_meta( $part->ID, '_jst_part_pages', true );
			if ( ! is_array( $pages ) || ! in_array( $current_id, array_map( 'intval', $pages ), true ) ) {
				continue;
			}
		}

		$html = get_post_meta( $part->ID, '_jst_part_html', true );
		echo do_shortcode( $html ); // phpcs:ignore -- intentional raw output, admin-trusted.
	}
}

function jst_output_parts_after_body() {
	jst_output_parts_at_location( 'after_body' );
}
add_action( 'wp_body_open', 'jst_output_parts_after_body', 10 );

function jst_output_parts_before_body_end() {
	jst_output_parts_at_location( 'before_body_end' );
}
add_action( 'jst_before_closing_body', 'jst_output_parts_before_body_end', 5 );

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
 * Branding header on the Template Parts list screen.
 */
function jst_part_list_header() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-jst_part' !== $screen->id ) {
		return;
	}
	?>
	<div style="margin: 1rem 0 0.5rem;">
		<h2 style="margin:0 0 0.25rem;font-size:1.3rem;">JST Theme — Template Parts</h2>
		<p style="margin:0;color:#646970;">Reusable HTML snippets managed by Just Spectacular Theme. Insert via shortcode <code>[jst_part name="…"]</code> or set a Location to auto-output on specific pages.</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'jst_part_list_header' );

/**
 * ------------------------------------------------------------------
 * Template Parts: starter seeder + bulk edit page
 * ------------------------------------------------------------------
 */

/**
 * Seed empty named Template Parts on theme activation.
 * Only creates a part if no jst_part with that _jst_part_name exists yet.
 */
function jst_seed_starter_parts() {
	$starters = array( 'testimonials', 'services', 'locations', 'cta', 'trust-strip', 'faq' );

	foreach ( $starters as $name ) {
		$existing = get_posts( array(
			'post_type'      => 'jst_part',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => '_jst_part_name', 'value' => $name ) ),
		) );

		if ( ! empty( $existing ) ) {
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'   => 'jst_part',
			'post_title'  => ucwords( str_replace( '-', ' ', $name ) ),
			'post_status' => 'publish',
		) );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_jst_part_name', $name );
			update_post_meta( $post_id, '_jst_part_html', '' );
			update_post_meta( $post_id, '_jst_part_location', 'shortcode_only' );
			update_post_meta( $post_id, '_jst_part_show_on', 'all' );
		}
	}
}
add_action( 'after_switch_theme', 'jst_seed_starter_parts' );

/**
 * Register "Bulk Edit" submenu under Template Parts.
 */
function jst_register_parts_bulk_edit_page() {
	add_submenu_page(
		'edit.php?post_type=jst_part',
		__( 'Bulk Edit Parts', 'just-spectacular-theme' ),
		__( 'Bulk Edit', 'just-spectacular-theme' ),
		'edit_posts',
		'jst-parts-bulk-edit',
		'jst_render_parts_bulk_edit_page'
	);
}
add_action( 'admin_menu', 'jst_register_parts_bulk_edit_page' );

function jst_render_parts_bulk_edit_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	// Handle save — existing parts + new parts created via import.
	if ( isset( $_POST['jst_bulk_parts_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['jst_bulk_parts_nonce'] ), 'jst_save_bulk_parts' ) ) {

		// Update existing parts.
		$submitted = isset( $_POST['jst_part_html'] ) && is_array( $_POST['jst_part_html'] ) ? $_POST['jst_part_html'] : array();
		foreach ( $submitted as $post_id => $html ) {
			$post_id = absint( $post_id );
			if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
				update_post_meta( $post_id, '_jst_part_html', wp_unslash( $html ) ); // phpcs:ignore -- admin-trusted raw HTML.
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			}
		}

		// Create new parts from import (section IDs that had no matching part).
		$new_parts = isset( $_POST['jst_new_part'] ) && is_array( $_POST['jst_new_part'] ) ? $_POST['jst_new_part'] : array();
		foreach ( $new_parts as $part_name => $html ) {
			$part_name = sanitize_title( wp_unslash( $part_name ) );
			if ( ! $part_name || ! $html ) {
				continue;
			}
			$post_id = wp_insert_post( array(
				'post_type'   => 'jst_part',
				'post_title'  => ucwords( str_replace( '-', ' ', $part_name ) ),
				'post_status' => 'publish',
			) );
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_jst_part_name', $part_name );
				update_post_meta( $post_id, '_jst_part_html', wp_unslash( $html ) ); // phpcs:ignore -- admin-trusted raw HTML.
				update_post_meta( $post_id, '_jst_part_location', 'shortcode_only' );
				update_post_meta( $post_id, '_jst_part_show_on', 'all' );
			}
		}

		echo '<div class="updated"><p>' . esc_html__( 'Template Parts saved.', 'just-spectacular-theme' ) . '</p></div>';
	}

	$parts = get_posts( array(
		'post_type'      => 'jst_part',
		'post_status'    => array( 'publish', 'draft' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );

	// Build a name→id map for JS to use during import matching.
	$part_name_map = array();
	foreach ( $parts as $part ) {
		$n = get_post_meta( $part->ID, '_jst_part_name', true );
		if ( $n ) {
			$part_name_map[ $n ] = $part->ID;
		}
	}
	?>
	<style>
	#jst-bulk-sticky {
		position: sticky;
		top: 32px;
		z-index: 100;
		background: #fff;
		border-bottom: 1px solid #dcdcde;
		padding: 10px 0 10px 14px;
		margin-bottom: 1.5rem;
		display: flex;
		align-items: center;
		gap: 1rem;
	}
	#jst-bulk-sticky strong { font-size: 13px; color: #1d2327; margin-right: 8px; }

	/* Two-column layout */
	#jst-bulk-columns {
		display: flex;
		gap: 20px;
		align-items: flex-start;
	}
	#jst-parts-col {
		flex: 1;
		min-width: 0;
	}
	#jst-import-col {
		width: 340px;
		flex-shrink: 0;
		position: sticky;
		top: 80px; /* below the sticky bar */
		max-height: calc(100vh - 100px);
		overflow-y: auto;
	}

	/* Import panel (always visible in right column) */
	#jst-import-panel {
		background: #f6f7f7;
		border: 1px solid #dcdcde;
		border-radius: 4px;
		padding: 16px;
	}
	#jst-import-panel h3 { margin: 0 0 8px; font-size: 13px; font-weight: 600; }
	#jst-import-html { width: 100%; font-family: monospace; font-size: 11px; resize: vertical; box-sizing: border-box; }
	#jst-scan-results { margin-top: 10px; }

	/* Section row */
	.jst-scan-section {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 3px;
		margin-bottom: 6px;
		font-size: 12px;
		overflow: hidden;
	}
	.jst-scan-section-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 10px;
		cursor: pointer;
		user-select: none;
	}
	.jst-scan-section-header:hover { background: #f9f9f9; }
	.jst-scan-section-header code { font-size: 11px; background: #f0f0f1; padding: 2px 4px; border-radius: 3px; }
	.jst-scan-badge {
		font-size: 10px;
		font-weight: 700;
		padding: 2px 5px;
		border-radius: 3px;
		text-transform: uppercase;
		margin-left: auto;
		flex-shrink: 0;
	}
	.jst-scan-badge.new { background: #d1e7dd; color: #0a3622; }
	.jst-scan-badge.overwrite { background: #fff3cd; color: #664d03; }
	.jst-scan-badge.empty { background: #e2e3e5; color: #41464b; }
	.jst-scan-expand { font-size: 10px; color: #646970; flex-shrink: 0; }

	/* Child element rows */
	.jst-scan-children {
		display: none;
		border-top: 1px solid #f0f0f1;
		padding: 4px 10px 8px 30px;
		background: #fafafa;
	}
	.jst-scan-children.is-open { display: block; }
	.jst-scan-child {
		display: flex;
		align-items: baseline;
		gap: 6px;
		padding: 3px 0;
		font-size: 11px;
		border-bottom: 1px solid #f0f0f1;
	}
	.jst-scan-child:last-child { border-bottom: none; }
	.jst-scan-child code { font-size: 10px; background: #f0f0f1; padding: 1px 3px; border-radius: 3px; white-space: nowrap; }
	.jst-scan-child .jst-child-preview { color: #646970; font-size: 10px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

	#jst-import-actions { margin-top: 10px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

	/* Parts list */
	.jst-bulk-part {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 4px;
		margin-bottom: 1.5rem;
		padding: 16px;
	}
	.jst-bulk-part h3 { margin: 0 0 4px; font-size: 14px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
	.jst-bulk-part code { font-size: 11px; background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-weight: 400; }
	.jst-bulk-part textarea { width: 100%; font-family: monospace; font-size: 12px; margin-top: 8px; resize: vertical; }
	.jst-part-actions { margin-left: auto; display: flex; gap: 6px; align-items: center; }
	.jst-delete-part { font-size: 11px; font-weight: 400; color: #b32d2e; text-decoration: none; }
	.jst-delete-part:hover { color: #8a1f1f; text-decoration: underline; }
	</style>

	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk Edit — Template Parts', 'just-spectacular-theme' ); ?></h1>
		<form method="post" action="" id="jst-bulk-form">
			<?php wp_nonce_field( 'jst_save_bulk_parts', 'jst_bulk_parts_nonce' ); ?>

			<div id="jst-bulk-sticky">
				<strong><?php esc_html_e( 'JST Template Parts', 'just-spectacular-theme' ); ?></strong>
				<?php submit_button( __( 'Save All Parts', 'just-spectacular-theme' ), 'primary', 'submit', false ); ?>
			</div>

			<div id="jst-bulk-columns">

				<!-- Left: parts list -->
				<div id="jst-parts-col">
					<?php if ( empty( $parts ) ) : ?>
						<p><?php esc_html_e( 'No Template Parts found. Use Import on the right to create them from a template, or add one manually.', 'just-spectacular-theme' ); ?></p>
					<?php else : ?>
						<?php foreach ( $parts as $part ) :
							$name       = get_post_meta( $part->ID, '_jst_part_name', true );
							$html       = get_post_meta( $part->ID, '_jst_part_html', true );
							$shortcode  = $name ? '[jst_part name="' . esc_attr( $name ) . '"]' : '';
							$delete_url = get_delete_post_link( $part->ID );
						?>
						<div class="jst-bulk-part" data-part-name="<?php echo esc_attr( $name ); ?>">
							<h3>
								<?php echo esc_html( $part->post_title ); ?>
								<?php if ( $shortcode ) : ?>
									<code><?php echo esc_html( $shortcode ); ?></code>
									<button type="button" class="button button-small jst-copy-btn" data-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy', 'just-spectacular-theme' ); ?></button>
								<?php endif; ?>
								<span class="jst-part-actions">
									<a href="<?php echo esc_url( get_edit_post_link( $part->ID ) ); ?>" style="font-size:11px;"><?php esc_html_e( 'Settings →', 'just-spectacular-theme' ); ?></a>
									<?php if ( $delete_url ) : ?>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="jst-delete-part" onclick="return confirm('Move this Template Part to Trash?');"><?php esc_html_e( 'Delete', 'just-spectacular-theme' ); ?></a>
									<?php endif; ?>
								</span>
							</h3>
							<textarea name="jst_part_html[<?php echo esc_attr( $part->ID ); ?>]" rows="12" class="jst-metabox-field"><?php echo $html; // phpcs:ignore -- intentionally unescaped raw HTML. ?></textarea>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php submit_button( __( 'Save All Parts', 'just-spectacular-theme' ) ); ?>
				</div>

				<!-- Right: import panel (sticky) -->
				<div id="jst-import-col">
					<div id="jst-import-panel">
						<h3><?php esc_html_e( 'Import from HTML Template', 'just-spectacular-theme' ); ?></h3>
						<p style="margin:0 0 8px;color:#646970;font-size:11px;"><?php esc_html_e( 'Upload a file or paste HTML. Scanner finds <section id="…"> blocks — expand each to pick individual child elements.', 'just-spectacular-theme' ); ?></p>
						<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
							<label class="button button-small" style="cursor:pointer;">
								<?php esc_html_e( 'Upload HTML File', 'just-spectacular-theme' ); ?>
								<input type="file" id="jst-import-file" accept=".html,text/html" style="display:none;">
							</label>
							<span style="font-size:11px;color:#646970;"><?php esc_html_e( 'or paste ↓', 'just-spectacular-theme' ); ?></span>
						</div>
						<textarea id="jst-import-html" rows="5" placeholder="Paste full HTML here…"></textarea>
						<div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
							<button type="button" id="jst-scan-btn" class="button button-primary button-small"><?php esc_html_e( 'Scan', 'just-spectacular-theme' ); ?></button>
							<span id="jst-scan-status" style="font-size:11px;color:#646970;"></span>
						</div>
						<div id="jst-scan-results"></div>
						<div id="jst-import-actions" style="display:none;">
							<button type="button" id="jst-apply-btn" class="button button-primary button-small"><?php esc_html_e( 'Apply Selected', 'just-spectacular-theme' ); ?></button>
							<label style="font-size:11px;"><input type="checkbox" id="jst-check-all" checked> <?php esc_html_e( 'All', 'just-spectacular-theme' ); ?></label>
							<span id="jst-apply-status" style="font-size:11px;color:#646970;"></span>
						</div>
					</div>
				</div>

			</div><!-- #jst-bulk-columns -->
		</form>
	</div>

	<script>
	( function() {
		// Part name → { textarea, hasContent } map built from the rendered cards.
		var partMap = {};
		document.querySelectorAll( '.jst-bulk-part[data-part-name]' ).forEach( function( el ) {
			var name = el.dataset.partName;
			var ta   = el.querySelector( 'textarea' );
			if ( name && ta ) {
				partMap[ name ] = { textarea: ta, hasContent: ta.value.trim().length > 0 };
			}
		} );

		// No toggle needed — import panel is always visible in the right column.

		// File upload → populate textarea and auto-scan.
		document.getElementById( 'jst-import-file' ).addEventListener( 'change', function( e ) {
			var file = e.target.files[0];
			if ( ! file ) { return; }
			var reader = new FileReader();
			reader.onload = function( ev ) {
				document.getElementById( 'jst-import-html' ).value = ev.target.result;
				runScan();
			};
			reader.readAsText( file );
		} );

		document.getElementById( 'jst-scan-btn' ).addEventListener( 'click', runScan );

		// Returns a short label for a child element: tag + first id/class + text preview.
		function childLabel( el ) {
			var label = el.tagName.toLowerCase();
			if ( el.id )        { label += '#' + el.id; }
			else if ( el.className && typeof el.className === 'string' ) {
				var cls = el.className.trim().split( /\s+/ )[0];
				if ( cls ) { label += '.' + cls; }
			}
			return label;
		}

		function childPreview( el ) {
			var t = el.textContent.replace( /\s+/g, ' ' ).trim();
			return t.length > 80 ? t.slice( 0, 80 ) + '…' : t;
		}

		// Scan state — kept in closure so Apply can read it.
		var scannedSections = [];

		function runScan() {
			var raw     = document.getElementById( 'jst-import-html' ).value.trim();
			var status  = document.getElementById( 'jst-scan-status' );
			var results = document.getElementById( 'jst-scan-results' );
			var actions = document.getElementById( 'jst-import-actions' );

			results.innerHTML   = '';
			actions.style.display = 'none';
			scannedSections     = [];

			if ( ! raw ) { status.textContent = 'No HTML yet.'; return; }

			var doc      = ( new DOMParser() ).parseFromString( raw, 'text/html' );
			var sections = Array.from( doc.querySelectorAll( 'section[id]' ) );

			if ( ! sections.length ) { status.textContent = 'No <section id="…"> elements found.'; return; }

			status.textContent = sections.length + ' section' + ( sections.length !== 1 ? 's' : '' ) + ' found — expand to pick child elements.';

			sections.forEach( function( sec ) {
				var id       = sec.id;
				var match    = partMap[ id ] || null;
				var badge, badgeClass;

				if ( match ) {
					badge = match.hasContent ? 'Will overwrite' : 'Empty — fill';
					badgeClass = match.hasContent ? 'overwrite' : 'empty';
				} else {
					badge = 'New part'; badgeClass = 'new';
				}

				// Collect meaningful children — unwrap single generic-div containers
				// (e.g. div.section-wrap) so we get the real headline/grid/etc.
				var children = Array.from( sec.children );
				while (
					children.length === 1 &&
					children[0].tagName.toLowerCase() === 'div' &&
					children[0].children.length > 0
				) {
					children = Array.from( children[0].children );
				}

				// Store section entry with per-child element refs (actual DOM nodes from parsed doc).
				var entry = { id: id, sec: sec, match: match, children: children, childChecks: [], wrapCheck: null };
				scannedSections.push( entry );

				// --- Section header row ---
				var wrap   = document.createElement( 'div' );
				wrap.className = 'jst-scan-section';

				var header = document.createElement( 'div' );
				header.className = 'jst-scan-section-header';

				var secCheck = document.createElement( 'input' );
				secCheck.type = 'checkbox';
				secCheck.className = 'jst-scan-check';
				secCheck.checked = ( badgeClass !== 'overwrite' );

				var codeEl = document.createElement( 'code' );
				codeEl.textContent = id;

				var expandEl = document.createElement( 'span' );
				expandEl.className = 'jst-scan-expand';
				expandEl.textContent = children.length + ' element' + ( children.length !== 1 ? 's' : '' ) + ' ▾';

				var badgeEl = document.createElement( 'span' );
				badgeEl.className = 'jst-scan-badge ' + badgeClass;
				badgeEl.textContent = badge;

				header.appendChild( secCheck );
				header.appendChild( codeEl );
				if ( ! match ) {
					var newNote = document.createElement( 'span' );
					newNote.style.cssText = 'font-size:11px;color:#646970;';
					newNote.textContent = '→ will create new part';
					header.appendChild( newNote );
				}
				header.appendChild( expandEl );
				header.appendChild( badgeEl );
				wrap.appendChild( header );

				// --- Children list ---
				var childList = document.createElement( 'div' );
				childList.className = 'jst-scan-children';

				children.forEach( function( child, ci ) {
					var row = document.createElement( 'div' );
					row.className = 'jst-scan-child';

					var cb = document.createElement( 'input' );
					cb.type = 'checkbox';
					cb.checked = secCheck.checked;
					entry.childChecks.push( cb );

					var lbl = document.createElement( 'code' );
					lbl.textContent = childLabel( child );

					var preview = document.createElement( 'span' );
					preview.className = 'jst-child-preview';
					preview.textContent = childPreview( child );

					row.appendChild( cb );
					row.appendChild( lbl );
					row.appendChild( preview );
					childList.appendChild( row );
				} );

				// --- Wrap toggle footer inside children panel ---
				var wrapRow = document.createElement( 'div' );
				wrapRow.style.cssText = 'padding:6px 10px 6px 30px;border-top:1px solid #f0f0f1;display:flex;align-items:center;gap:6px;font-size:11px;color:#646970;background:#f6f7f7;';
				var wrapCb = document.createElement( 'input' );
				wrapCb.type    = 'checkbox';
				wrapCb.checked = false;
				entry.wrapCheck = wrapCb;
				var wrapLbl = document.createElement( 'label' );
				wrapLbl.style.cssText = 'font-size:11px;color:#646970;cursor:pointer;';
				wrapLbl.textContent = 'Include <section> wrapper';
				wrapLbl.prepend( wrapCb );
				wrapRow.appendChild( wrapLbl );
				childList.appendChild( wrapRow );

				wrap.appendChild( childList );
				results.appendChild( wrap );

				// Toggle children panel on header click.
				header.addEventListener( 'click', function( e ) {
					if ( e.target === secCheck ) { return; } // let checkbox handle itself
					childList.classList.toggle( 'is-open' );
					expandEl.textContent = children.length + ' element' + ( children.length !== 1 ? 's' : '' ) + ( childList.classList.contains( 'is-open' ) ? ' ▴' : ' ▾' );
				} );

				// Section checkbox drives child checkboxes.
				secCheck.addEventListener( 'change', function() {
					entry.childChecks.forEach( function( cb ) { cb.checked = secCheck.checked; } );
				} );

				// If any child unchecked → uncheck section master; if all re-checked → check it.
				entry.childChecks.forEach( function( cb ) {
					cb.addEventListener( 'change', function() {
						var allChecked = entry.childChecks.every( function( c ) { return c.checked; } );
						var anyChecked = entry.childChecks.some(  function( c ) { return c.checked; } );
						secCheck.indeterminate = anyChecked && ! allChecked;
						secCheck.checked = allChecked;
					} );
				} );
			} );

			actions.style.display = 'flex';

			// Select all / none.
			document.getElementById( 'jst-check-all' ).onchange = function() {
				var checked = this.checked;
				results.querySelectorAll( 'input[type="checkbox"]' ).forEach( function( cb ) {
					cb.checked = checked;
					cb.indeterminate = false;
				} );
				scannedSections.forEach( function( entry ) {
					entry.childChecks.forEach( function( cb ) { cb.checked = checked; } );
				} );
			};

			// Apply.
			document.getElementById( 'jst-apply-btn' ).onclick = function() {
				var applyStatus = document.getElementById( 'jst-apply-status' );
				var form        = document.getElementById( 'jst-bulk-form' );
				var applied = 0;

				scannedSections.forEach( function( entry ) {
					// Collect only checked children; skip whole section if none checked.
					var checkedChildren = entry.children.filter( function( ch, i ) {
						return entry.childChecks[i] && entry.childChecks[i].checked;
					} );
					if ( ! checkedChildren.length ) { return; }

					// Build output: children only, or wrapped in <section> if the toggle is on.
					var innerHtml = checkedChildren.map( function( ch ) { return ch.outerHTML; } ).join( '\n' );
					var finalHtml;
					if ( entry.wrapCheck && entry.wrapCheck.checked ) {
						var outerOpen = entry.sec.outerHTML.match( /^(<section[^>]*>)/i );
						var openTag   = outerOpen ? outerOpen[1] : '<section id="' + entry.id + '">';
						finalHtml = openTag + '\n' + innerHtml + '\n</section>';
					} else {
						finalHtml = innerHtml;
					}

					if ( entry.match ) {
						entry.match.textarea.value = finalHtml;
					} else {
						var existing = form.querySelector( 'input[name="jst_new_part[' + entry.id + ']"]' );
						if ( existing ) { existing.remove(); }
						var hidden = document.createElement( 'input' );
						hidden.type  = 'hidden';
						hidden.name  = 'jst_new_part[' + entry.id + ']';
						hidden.value = finalHtml;
						form.appendChild( hidden );
					}
					applied++;
				} );

				applyStatus.textContent = applied + ' part' + ( applied !== 1 ? 's' : '' ) + ' applied — hit Save All to commit.';
			};
		}
	} )();
	</script>
	<?php
}

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

/**
 * ------------------------------------------------------------------
 * Winden integration: register a custom crawler so Winden's Tailwind
 * class scanner sees HTML stored in wp_options (Theme Options fields)
 * and postmeta (Page Code, Template Parts) — sources it does not scan
 * natively. Uses Winden's official `winden_register_crawlers` filter
 * (App/Caching/Crawlers/HookCrawler.php), so no "dummy post" bait is
 * needed.
 * ------------------------------------------------------------------
 */

class JST_Winden_Crawler {

	/**
	 * Extract class="..." / className="..." tokens from a blob of raw HTML.
	 * Mirrors the minimum Winden's own StringParser needs: a flat list of
	 * individual class-name strings.
	 */
	private function extract_classes_from_html( $html ) {
		if ( ! $html || ! is_string( $html ) ) {
			return array();
		}

		$classes = array();

		if ( preg_match_all( '/\bclass(?:Name)?\s*=\s*["\']([^"\']*)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $class_attr ) {
				foreach ( preg_split( '/\s+/', trim( $class_attr ) ) as $class ) {
					if ( '' !== $class ) {
						$classes[] = $class;
					}
				}
			}
		}

		return $classes;
	}

	/**
	 * Required by Winden's HookCrawler contract: return a flat array of
	 * Tailwind class strings found across all JST-managed HTML sources.
	 */
	public function classes() {
		$classes = array();

		// Theme Options fields stored in wp_options.
		foreach ( array_keys( jst_theme_options_fields() ) as $field_id ) {
			$classes = array_merge( $classes, $this->extract_classes_from_html( get_option( $field_id, '' ) ) );
		}

		// Per-page Header/Footer Code, stored in postmeta on any post/page.
		$paged_posts = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_jst_page_header_code', 'compare' => 'EXISTS' ),
				array( 'key' => '_jst_page_footer_code', 'compare' => 'EXISTS' ),
			),
		) );

		foreach ( $paged_posts as $post_id ) {
			$classes = array_merge(
				$classes,
				$this->extract_classes_from_html( get_post_meta( $post_id, '_jst_page_header_code', true ) ),
				$this->extract_classes_from_html( get_post_meta( $post_id, '_jst_page_footer_code', true ) )
			);
		}

		// Template Parts HTML, stored in postmeta on jst_part posts.
		$parts = get_posts( array(
			'post_type'      => 'jst_part',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $parts as $part_id ) {
			$classes = array_merge( $classes, $this->extract_classes_from_html( get_post_meta( $part_id, '_jst_part_html', true ) ) );
		}

		// Post/page content — needed so the typography plugin sees actual
		// prose elements (<h2>, <p>, <ul> etc.) and generates their styles.
		$content_posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( $content_posts as $post_id ) {
			$classes = array_merge( $classes, $this->extract_classes_from_html( get_post_field( 'post_content', $post_id ) ) );
		}

		return array_values( array_unique( $classes ) );
	}
}

function jst_register_winden_crawler( $crawlers ) {
	$crawlers[] = new JST_Winden_Crawler();
	return $crawlers;
}
add_filter( 'winden_register_crawlers', 'jst_register_winden_crawler' );

/**
 * ------------------------------------------------------------------
 * Winden integration: "Compile Tailwind CSS" admin bar button.
 *
 * Winden only ever writes output.css from two places: (a) Dev Mode's
 * client-side live compiler on the front end, which never persists to
 * disk, or (b) Winden's own dashboard "Save" button, whose screen
 * always loads Winden's full compiler JS regardless of the sitewide
 * Dev Mode setting. There is no "compile on save, no FOUC" path built
 * into Winden itself — with Dev Mode off, nothing auto-compiles
 * anywhere on the site, JST content or otherwise.
 *
 * This replicates (b): admin-wide (every wp-admin screen, not just
 * JST's own), we enqueue Winden's real compiler assets — the same
 * ones its own dashboard loads — via Winden's own
 * ProvidersHelpers::framework_scripts(), then add one "Compile
 * Tailwind CSS" node to the admin toolbar. Clicking it:
 *   1. Forces a full crawl (post_id=0 — required so Winden's
 *      HookCrawler runs and our custom crawler's classes, see
 *      JST_Winden_Crawler above, get included; passing the current
 *      post's ID would take Winden's fast single-post path instead,
 *      which skips HookCrawler entirely).
 *   2. Runs Winden's own compile() (via window.WindenCompilerCore),
 *      which compiles client-side using Winden's already-loaded
 *      compiler and POSTs the result to Winden's own save-cache
 *      endpoint, writing output.css — identical to what happens when
 *      you click "Save" in Winden's dashboard.
 * No custom CSS compiler logic here; this only orchestrates Winden's
 * own pipeline from a place Winden itself doesn't reach.
 * ------------------------------------------------------------------
 */

/**
 * Enqueue Winden's real compiler assets wherever the admin toolbar is
 * visible — both wp-admin and the front end for logged-in users with
 * the toolbar enabled. Never loads for anonymous visitors.
 *
 * IMPORTANT: this deliberately does NOT call Winden's own
 * ProvidersHelpers::framework_scripts() — that bundles in
 * tailwindcss-watcher.js, a live MutationObserver-based DOM
 * scanner/compiler that actively injects compiled CSS (including
 * Tailwind's Preflight reset) into whatever page it's running on.
 * Loading that admin-wide broke native wp-admin control styling
 * (e.g. other plugins' toggle switches) and caused a FOUC on every
 * admin screen. We only need the inert compiler engine + config
 * globals — replicated here manually, minus the watcher.
 */
function jst_enqueue_winden_compiler_assets( $hook = '' ) {
	if ( ! current_user_can( 'edit_posts' ) || ! is_admin_bar_showing() ) {
		return;
	}
	// Don't interfere with Winden's own admin pages — it manages its assets there.
	if ( $hook && strpos( $hook, 'winden' ) !== false ) {
		return;
	}

	if ( ! class_exists( '\Winden\App\Assets\Providers\ProvidersHelpers' ) ) {
		return;
	}

	$compiler_handle = 'winden-compiler-module';
	if ( ! wp_script_is( $compiler_handle, 'enqueued' ) ) {
		wp_enqueue_script(
			$compiler_handle,
			WINDTACS_PLUGIN_URL . 'build/compiler/tailwindcss-compiler.js',
			array(),
			defined( 'WINDTACS_VERSION' ) ? WINDTACS_VERSION : false,
			true
		);
	}

	$compiler_options = \Winden\App\Assets\Providers\ProvidersHelpers::get_compiler_options();
	wp_register_script( 'tailwind-compiler-options', '', array( $compiler_handle ), defined( 'WINDTACS_VERSION' ) ? WINDTACS_VERSION : false, true );
	wp_enqueue_script( 'tailwind-compiler-options' );
	wp_add_inline_script( 'tailwind-compiler-options', 'window.tailwind_compiler_options = ' . wp_json_encode( $compiler_options ) );

	wp_enqueue_script(
		'winden-compiler-core',
		WINDTACS_PLUGIN_URL . 'assets/winden-compiler-core.js',
		array( $compiler_handle ),
		defined( 'WINDTACS_VERSION' ) ? WINDTACS_VERSION : false,
		true
	);

	// winden-compiler-core.js only needs { ajaxUrl, nonce } on this
	// global — compile-trigger.js normally supplies it via
	// wp_localize_script, but we're calling the core module directly.
	wp_add_inline_script(
		'winden-compiler-core',
		'window.windenAutoCompile = window.windenAutoCompile || {};'
		. 'window.windenAutoCompile.ajaxUrl = ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';'
		. 'window.windenAutoCompile.nonce = ' . wp_json_encode( wp_create_nonce( 'winden_nonce' ) ) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'jst_enqueue_winden_compiler_assets' );
add_action( 'wp_enqueue_scripts', 'jst_enqueue_winden_compiler_assets' );

/**
 * Add the "Compile Tailwind CSS" node to the admin toolbar — shown
 * wherever the toolbar itself is shown (admin + front end).
 */
function jst_add_winden_compile_admin_bar_node( $wp_admin_bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	if ( ! class_exists( '\Winden\App\Assets\Providers\ProvidersHelpers' ) ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'jst-winden-compile',
			'title' => 'Compile Tailwind CSS',
			'href'  => '#',
			'meta'  => array( 'title' => 'Full crawl + compile via Winden — writes output.css immediately, no Dev Mode needed.' ),
		)
	);
}
add_action( 'admin_bar_menu', 'jst_add_winden_compile_admin_bar_node', 100 );

/**
 * Sort pages/posts list by date modified when enabled in Theme Options.
 */
if ( get_option( 'jst_sort_by_modified', '' ) ) {
	add_action( 'pre_get_posts', function( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = get_current_screen();
		// Applies to any post-type list screen (page, post, and all CPTs).
		if ( ! $screen || 'edit' !== $screen->base || empty( $screen->post_type ) ) {
			return;
		}
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );
	} );
}

/**
 * Click handler for the admin bar node: full crawl, then Winden's own
 * compile-and-save. Output on both admin and front-end footers since
 * the node itself can appear in either.
 */
function jst_winden_compile_button_script() {
	if ( ! current_user_can( 'edit_posts' ) || ! is_admin_bar_showing() ) {
		return;
	}

	if ( ! class_exists( '\Winden\App\Assets\Providers\ProvidersHelpers' ) ) {
		return;
	}
	?>
	<script>
	( function() {
		function init() {
			var link = document.querySelector( '#wp-admin-bar-jst-winden-compile > .ab-item' );
			if ( ! link ) {
				return;
			}

			var originalText = link.textContent;

			function setLabel( text ) {
				link.textContent = text;
			}

			link.addEventListener( 'click', function( e ) {
				e.preventDefault();
				if ( link.dataset.busy ) {
					return;
				}
				link.dataset.busy = '1';
				setLabel( 'Crawling…' );

				fetch( window.windenAutoCompile.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams( {
						action: 'winden_trigger_recompile',
						post_id: '0', // Full crawl — required for HookCrawler (our custom sources) to run.
						_nonce: window.windenAutoCompile.nonce
					} )
				} )
					.then( function( res ) { return res.json(); } )
					.then( function( crawlResult ) {
						if ( ! crawlResult || ! crawlResult.success ) {
							throw new Error( 'Crawl failed' );
						}
						setLabel( 'Compiling…' );

						function waitForCore( tries ) {
							if ( window.WindenCompilerCore && window.tailwindify ) {
								return Promise.resolve();
							}
							if ( tries <= 0 ) {
								throw new Error( 'Winden compiler did not load' );
							}
							return new Promise( function( resolve ) {
								setTimeout( resolve, 100 );
							} ).then( function() {
								return waitForCore( tries - 1 );
							} );
						}

						return waitForCore( 50 ).then( function() {
							var compile = window.WindenCompilerCore.createCompileFunction( {
								onCSSReload: function() {
									// No-op: this is an admin-only trigger, nothing on this
									// screen needs the compiled CSS injected live.
								}
							} );
							return compile();
						} );
					} )
					.then( function() {
						setLabel( 'Compiled ✓' );
					} )
					.catch( function( err ) {
						console.error( '[JST/Winden] Compile failed:', err );
						setLabel( 'Compile failed' );
					} )
					.finally( function() {
						setTimeout( function() {
							delete link.dataset.busy;
							setLabel( originalText );
						}, 3000 );
					} );
			} );
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', init );
		} else {
			init();
		}
	} )();
	</script>
	<?php
}
add_action( 'admin_footer', 'jst_winden_compile_button_script' );
add_action( 'wp_footer', 'jst_winden_compile_button_script' );
