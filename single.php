<?php
/**
 * Single post template.
 */

get_header();
?>
<main class="jst-main">
	<div class="jst-container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php
				$jst_article_class = get_post_meta( get_the_ID(), '_jst_prose_invert', true ) || get_option( 'jst_prose_invert', '' ) ? 'prose-invert' : '';
				?>
				<article <?php post_class( $jst_article_class ); ?>>
				<h1 class="jst-single-title"><?php the_title(); ?></h1>
				<p class="jst-single-meta">
					<?php
					printf(
						/* translators: 1: post date, 2: post author */
						esc_html__( 'Posted on %1$s by %2$s', 'just-spectacular-theme' ),
						esc_html( get_the_date() ),
						esc_html( get_the_author() )
					);
					?>
				</p>

				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'large', array( 'class' => 'jst-single-thumb' ) ); ?>
				<?php endif; ?>

				<div class="<?php echo jst_content_class( 'jst-single-content' ); ?>">
					<?php the_content(); ?>
				</div>
			</article>

			<?php
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;
			?>
			<?php
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
