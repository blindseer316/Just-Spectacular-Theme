<?php
/**
 * Template Name: Full Width — With Title
 *
 * Full-bleed template with no side container/margins. Shows the page/post
 * title and optionally the post meta (date, author). Useful for full-width
 * blog posts or articles where content controls its own inner width.
 * Toggle meta visibility via Page Settings > Hide Post Meta.
 */

get_header();

$jst_width    = jst_get_page_width( null, '100%' );
$jst_hide_meta = get_post_meta( get_the_ID(), '_jst_hide_post_meta', true );
?>
<main class="jst-main" style="max-width: <?php echo esc_attr( $jst_width ); ?>; margin-left: auto; margin-right: auto; padding-left: 0; padding-right: 0;">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<?php
			$jst_article_class = ( get_option( 'jst_prose_invert', '' ) || get_post_meta( get_the_ID(), '_jst_prose_invert', true ) ) ? 'prose-invert' : '';
			?>
			<article <?php post_class( $jst_article_class ); ?>>
			<h1 class="jst-single-title"><?php the_title(); ?></h1>

			<?php if ( ! $jst_hide_meta ) : ?>
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
			<?php endif; ?>

			<div class="<?php echo jst_content_class( 'jst-single-content' ); ?>">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
