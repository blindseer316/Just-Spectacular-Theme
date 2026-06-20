<?php
/**
 * Template Name: Plain
 *
 * Width-constrained container with a plain (non-hero) title — no
 * breadcrumbs, no banner styling. Ideal for terms/privacy/legal pages.
 */

get_header();
?>
<main class="jst-main">
	<div class="jst-container" style="max-width: <?php echo esc_attr( jst_get_page_width() ); ?>;">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<h1 class="jst-page-title"><?php the_title(); ?></h1>
			<div class="<?php echo jst_content_class( 'jst-single-content' ); ?>">
				<?php the_content(); ?>
			</div>
			<?php
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
