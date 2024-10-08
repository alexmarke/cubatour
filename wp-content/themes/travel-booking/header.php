<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Travel_Booking
 */

    /**
     * Doctype Hook
     * 
     * @hooked travel_booking_doctype
    */
    do_action( 'travel_booking_doctype' );   
?>
<head itemscope itemtype="https://schema.org/WebSite">

<?php     
    /**
     * Before wp_head
     * 
     * @hooked travel_booking_head
    */
    do_action( 'travel_booking_before_wp_head' );
    
    wp_head(); 
?>

</head>

<body <?php body_class(); ?> itemscope itemtype="https://schema.org/WebPage">
	
<?php
wp_body_open(); 
    /**
     * Before Header
     * 
     * @hooked travel_booking_page_start - 20 
    */
    do_action( 'travel_booking_before_header' );
    
    /**
     * Header
     * 
     * @hooked travel_booking_header - 20     
    */
    do_action( 'travel_booking_header' );

     /**
     * Banner section
     * 
     * @hooked travel_booking_render_banner_section -10
    */
    do_action( 'travel_booking_banner_section' );
    
    /**
     * Before Content
     * 
     * @hooked travel_booking_container_start - 20
     * @hooked travel_booking_breadcrumb - 30
     * @hooked travel_booking_page_header - 40
    */
    do_action( 'travel_booking_before_content' );
    
    /**
     * Content
     * 
     * @hooked travel_booking_content_start - 10
    */
    do_action( 'travel_booking_content' );