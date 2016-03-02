<?php
/**
 * @wordpress-plugin
 * Plugin Name: ARAA
 * Description: Custom functionality for the Australian Robotics and Automation Association site
 * Author: Andrew Short
 * Author URI: mailto:andrewjshort@gmail.com
 */

require_once __DIR__ . '/options.php';
require_once __DIR__ . '/register.php';

add_action('wp_enqueue_scripts', function() {
    wp_register_style('araa', plugins_url('/assets/style.css' , __FILE__));
    wp_register_script('araa-register', plugins_url('/assets/register.js' , __FILE__));
});
