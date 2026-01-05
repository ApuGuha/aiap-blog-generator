<?php
/**
 * Plugin Name: AI Blog Generator
 * Description: Generates blogs automatically using AI and creates WordPress posts.
 * Version: 1.0
 * Author: Aparna Guha
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'admin/class-ai-blog-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-blog-generator.php';
add_action('plugins_loaded', function() {
    new AI_Blog_Admin();
});
