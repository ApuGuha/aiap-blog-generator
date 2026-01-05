<?php
if (!defined('ABSPATH')) exit;

class AI_Blog_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_post_aibg_generate_post', [$this, 'handle_form']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu() {
        add_menu_page(
            'AI Blog Generator',
            'AI Blog Generator',
            'manage_options',
            'ai-blog-generator',
            [$this, 'admin_page'],
            'dashicons-edit',
            6
        );
    }

    public function enqueue_styles($hook) {
        if ($hook === 'toplevel_page_ai-blog-generator') {
            wp_enqueue_style('aibg-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css');
        }
    }

    public function register_settings() {
        register_setting('aibg_settings_group', 'aibg_ai_api_key');
    }

    public function admin_page() { 
        $api_key = get_option('aibg_ai_api_key', '');
        ?>
        <div class="wrap aibg-wrap">
            <h1>AI Blog Generator</h1>
            <h2>AI Settings</h2>
            <form method="post" action="options.php">
                <?php
                    settings_fields('aibg_settings_group');
                    do_settings_sections('aibg_settings_group');
                ?>
                <label for="aibg_ai_api_key">AI API Key:</label>
                <input type="text" id="aibg_ai_api_key" name="aibg_ai_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:100%;" required>
                <?php submit_button('Save API Key'); ?>
            </form>

            <hr>
            <h2>Generate Blog</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="aibg_generate_post">
                <?php wp_nonce_field('aibg_generate_post_nonce'); ?>
                
                <label for="aibg_topic">Enter Blog Topic:</label>
                <input type="text" id="aibg_topic" name="aibg_topic" required>
                
                <label for="aibg_status">Post Status:</label>
                <select id="aibg_status" name="aibg_status">
                    <option value="draft">Draft</option>
                    <option value="publish">Publish</option>
                </select>
                
                <button type="submit" class="button button-primary">Generate Blog</button>
            </form>

            <?php if(isset($_GET['success'])): ?>
                <div class="notice notice-success"><p>Blog generated successfully!</p></div>
            <?php elseif(isset($_GET['error'])): ?>
                <div class="notice notice-error"><p>There was an error generating the blog.</p></div>
            <?php endif; ?>
        </div>
    <?php }

    public function handle_form() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'aibg_generate_post_nonce')) {
            wp_die('Nonce verification failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $topic = sanitize_text_field($_POST['aibg_topic']);
        $status = sanitize_text_field($_POST['aibg_status']);

        $ai_generator = new AI_Blog_Generator();
        $blog_data = $ai_generator->generate_blog($topic);

        if ($blog_data) {
            $post_id = wp_insert_post([
                'post_title'   => wp_strip_all_tags($blog_data['title']),
                'post_content' => $blog_data['content'],
                'post_status'  => $status,
                'tags_input'   => $blog_data['tags'],
                'post_type'    => 'post'
            ]);

            if ($post_id) {
                wp_redirect(admin_url('admin.php?page=ai-blog-generator&success=1'));
                exit;
            }
        }

        wp_redirect(admin_url('admin.php?page=ai-blog-generator&error=1'));
        exit;
    }
}
