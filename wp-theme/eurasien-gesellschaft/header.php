<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip" href="#main"><span class="de">Zum Inhalt springen</span><span class="en" hidden>Skip to content</span></a>

<?php get_template_part( 'template-parts/header', 'app' ); ?>

<main id="main">
