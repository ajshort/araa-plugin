<?php
/**
 * @wordpress-plugin
 * Plugin Name: ARAA
 * Description: Custom functionality for the Australian Robotics and Automation Association site
 * Author: Andrew Short
 * Author URI: mailto:andrewjshort@gmail.com
 */

add_action('wp_enqueue_scripts', function() {
    wp_register_style('araa', plugins_url('/assets/style.css' , __FILE__));
});

add_shortcode('araa-register-form', function() {
    ob_start();
    require __DIR__ . '/register.php';
    return ob_get_clean();
});
