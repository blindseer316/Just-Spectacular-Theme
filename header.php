<?php
/**
 * Theme header.
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
wp_body_open();
jst_default_nav_fallback();
?>
