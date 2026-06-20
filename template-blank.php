<?php
/**
 * Template Name: Blank Canvas
 *
 * No header/footer wrapper — outputs only the page content.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'jst-body' ); ?>>
<?php
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
?>
<?php wp_footer(); ?>
<?php do_action( 'jst_before_closing_body' ); ?>
</body>
</html>
