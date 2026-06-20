<?php
/**
 * Default grid layout.
 *
 * Used for both the blog posts page and category/archive fallback,
 * since this theme has no home.php / category.php / archive.php.
 */

get_header();
jst_index_hero();
?>
<main class="jst-main">
	<div class="jst-container">

		<?php if ( have_posts() ) : ?>
			<div class="jst-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'jst-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'medium_large', array( 'class' => 'jst-card__thumb' ) ); ?>
							</a>
						<?php endif; ?>
						<div class="jst-card__body">
							<h2 class="jst-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<p class="jst-card__meta">
								<a href="<?php echo esc_url( get_day_link( get_the_time( 'Y' ), get_the_time( 'm' ), get_the_time( 'd' ) ) ); ?>"><?php echo esc_html( get_the_date() ); ?></a>
								<span class="jst-card__meta-sep">&middot;</span>
								<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
								<?php if ( get_the_category() ) : ?>
									<span class="jst-card__meta-sep">&middot;</span>
									<?php the_category( ', ' ); ?>
								<?php endif; ?>
								<?php if ( get_the_tags() ) : ?>
									<span class="jst-card__meta-sep">&middot;</span>
									<?php the_tags( '', ', ', '' ); ?>
								<?php endif; ?>
							</p>
							<p class="jst-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<div class="jst-pagination">
				<?php
				echo paginate_links(
					array(
						'prev_text' => __( '&laquo; Previous', 'just-spectacular-theme' ),
						'next_text' => __( 'Next &raquo;', 'just-spectacular-theme' ),
					)
				);
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing found.', 'just-spectacular-theme' ); ?></p>
		<?php endif; ?>

	</div>
</main>
<?php
get_footer();
