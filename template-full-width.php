<?php
/**
 * Template Name: Full Width
 *
 * Full-bleed page template — no side container/margins, no title.
 * Uses the global header/footer. Width is driven entirely by the
 * per-page Width field (defaults to 100%); intended for service
 * pages, landing pages, etc. built with their own custom markup.
 */

get_header();

$jst_width = jst_get_page_width( null, '100%' );
?>
<main class="jst-main" style="max-width: <?php echo esc_attr( $jst_width ); ?>; margin-left: auto; margin-right: auto; padding-left: 0; padding-right: 0; padding-top: 0; padding-bottom: 0;">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
