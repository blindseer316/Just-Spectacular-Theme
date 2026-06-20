<?php
/**
 * Default page template (used when no other template is selected).
 * Hero-style title band + width-constrained container.
 */

get_header();

while ( have_posts() ) :
	the_post();
	jst_page_hero();
	?>
	<main class="jst-main">
		<div class="jst-container" style="max-width: <?php echo esc_attr( jst_get_page_width() ); ?>;">
			<div class="<?php echo jst_content_class( 'jst-single-content' ); ?>">
				<?php the_content(); ?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
