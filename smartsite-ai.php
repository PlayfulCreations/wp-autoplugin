<?php
/*
Plugin Name: SmartSite AI
Description: A cutting-edge WordPress AI page builder plugin designed to revolutionize website development. Harnesses artificial intelligence to simplify design, generate dynamic content, and automatically optimize layouts.
Version: 1.0.0
Author: WP-Autoplugin
Author URI: https://wp-autoplugin.com
Text Domain: smartsite-ai
Domain Path: /languages
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SMARTSITE_AI_VERSION', '1.0.0');
define('SMARTSITE_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMARTSITE_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMARTSITE_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main SmartSite AI Plugin Class
 */
class SmartSite_AI {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_smartsite_ai_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_smartsite_ai_generate_layout', array($this, 'ajax_generate_layout'));
        add_action('wp_ajax_smartsite_ai_optimize_page', array($this, 'ajax_optimize_page'));
        add_action('wp_ajax_smartsite_ai_save_component', array($this, 'ajax_save_component'));
        add_action('wp_ajax_smartsite_ai_load_template', array($this, 'ajax_load_template'));
        add_action('wp_head', array($this, 'add_frontend_styles'));
        
        // Add shortcode support
        add_shortcode('smartsite_ai_component', array($this, 'render_component_shortcode'));
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('smartsite-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
        $this->create_database_tables();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Components table
        $components_table = $wpdb->prefix . 'smartsite_ai_components';
        $sql = "CREATE TABLE $components_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(100) NOT NULL,
            content longtext NOT NULL,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Templates table
        $templates_table = $wpdb->prefix . 'smartsite_ai_templates';
        $sql2 = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category varchar(100) NOT NULL,
            layout longtext NOT NULL,
            preview_image varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'smartsite_ai_analytics';
        $sql3 = "CREATE TABLE $analytics_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value text NOT NULL,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
        
        // Insert default templates
        $this->insert_default_templates();
    }
    
    /**
     * Insert default templates
     */
    private function insert_default_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'smartsite_ai_templates';
        
        // Check if templates already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $templates_table");
        if ($existing > 0) {
            return;
        }
        
        $default_templates = array(
            array(
                'name' => 'Business Landing Page',
                'category' => 'business',
                'layout' => json_encode(array(
                    'sections' => array(
                        array('type' => 'hero', 'title' => 'Welcome to Our Business', 'subtitle' => 'We provide exceptional services'),
                        array('type' => 'features', 'items' => 3),
                        array('type' => 'testimonials', 'items' => 2),
                        array('type' => 'contact', 'form' => true)
                    )
                ))
            ),
            array(
                'name' => 'Portfolio Showcase',
                'category' => 'portfolio',
                'layout' => json_encode(array(
                    'sections' => array(
                        array('type' => 'hero', 'title' => 'Creative Portfolio', 'subtitle' => 'Showcasing our best work'),
                        array('type' => 'gallery', 'columns' => 3),
                        array('type' => 'about', 'content' => 'About the creator'),
                        array('type' => 'contact', 'form' => true)
                    )
                ))
            ),
            array(
                'name' => 'Blog Homepage',
                'category' => 'blog',
                'layout' => json_encode(array(
                    'sections' => array(
                        array('type' => 'hero', 'title' => 'Latest Articles', 'subtitle' => 'Stay updated with our content'),
                        array('type' => 'posts', 'layout' => 'grid', 'count' => 6),
                        array('type' => 'newsletter', 'title' => 'Subscribe to our newsletter')
                    )
                ))
            )
        );
        
        foreach ($default_templates as $template) {
            $wpdb->insert($templates_table, $template);
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('SmartSite AI', 'smartsite-ai'),
            __('SmartSite AI', 'smartsite-ai'),
            'manage_options',
            'smartsite-ai',
            array($this, 'admin_page'),
            'dashicons-admin-customizer',
            30
        );
        
        add_submenu_page(
            'smartsite-ai',
            __('Page Builder', 'smartsite-ai'),
            __('Page Builder', 'smartsite-ai'),
            'manage_options',
            'smartsite-ai-builder',
            array($this, 'builder_page')
        );
        
        add_submenu_page(
            'smartsite-ai',
            __('Templates', 'smartsite-ai'),
            __('Templates', 'smartsite-ai'),
            'manage_options',
            'smartsite-ai-templates',
            array($this, 'templates_page')
        );
        
        add_submenu_page(
            'smartsite-ai',
            __('Analytics', 'smartsite-ai'),
            __('Analytics', 'smartsite-ai'),
            'manage_options',
            'smartsite-ai-analytics',
            array($this, 'analytics_page')
        );
        
        add_submenu_page(
            'smartsite-ai',
            __('Settings', 'smartsite-ai'),
            __('Settings', 'smartsite-ai'),
            'manage_options',
            'smartsite-ai-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'smartsite-ai') === false) {
            return;
        }
        
        wp_enqueue_script('smartsite-ai-admin', SMARTSITE_AI_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), SMARTSITE_AI_VERSION, true);
        wp_enqueue_style('smartsite-ai-admin', SMARTSITE_AI_PLUGIN_URL . 'assets/css/admin.css', array('wp-color-picker'), SMARTSITE_AI_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('smartsite-ai-admin', 'smartsite_ai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smartsite_ai_nonce'),
            'strings' => array(
                'generating' => __('Generating...', 'smartsite-ai'),
                'optimizing' => __('Optimizing...', 'smartsite-ai'),
                'saving' => __('Saving...', 'smartsite-ai'),
                'error' => __('An error occurred. Please try again.', 'smartsite-ai'),
                'success' => __('Operation completed successfully!', 'smartsite-ai')
            )
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('smartsite-ai-frontend', SMARTSITE_AI_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SMARTSITE_AI_VERSION, true);
        wp_enqueue_style('smartsite-ai-frontend', SMARTSITE_AI_PLUGIN_URL . 'assets/css/frontend.css', array(), SMARTSITE_AI_VERSION);
    }
    
    /**
     * Add frontend styles to head
     */
    public function add_frontend_styles() {
        echo '<style id="smartsite-ai-dynamic-styles">';
        echo $this->get_dynamic_styles();
        echo '</style>';
    }
    
    /**
     * Get dynamic styles
     */
    private function get_dynamic_styles() {
        return '
        .smartsite-ai-component {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .smartsite-ai-hero {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .smartsite-ai-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .smartsite-ai-hero p {
            font-size: 1.2em;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .smartsite-ai-cta {
            display: inline-block;
            padding: 15px 30px;
            background: #ff6b6b;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .smartsite-ai-cta:hover {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,107,107,0.4);
        }
        
        .smartsite-ai-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .smartsite-ai-feature {
            text-align: center;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .smartsite-ai-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .smartsite-ai-feature-icon {
            font-size: 3em;
            margin-bottom: 20px;
            color: #667eea;
        }
        
        .smartsite-ai-testimonial {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        
        .smartsite-ai-contact-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .smartsite-ai-form-group {
            margin-bottom: 20px;
        }
        
        .smartsite-ai-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .smartsite-ai-form-group input,
        .smartsite-ai-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .smartsite-ai-form-group input:focus,
        .smartsite-ai-form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }
        
        @media (max-width: 768px) {
            .smartsite-ai-hero h1 {
                font-size: 2em;
            }
            
            .smartsite-ai-features {
                grid-template-columns: 1fr;
            }
        }
        ';
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap smartsite-ai-admin">
            <h1><?php _e('SmartSite AI Dashboard', 'smartsite-ai'); ?></h1>
            
            <div class="smartsite-ai-dashboard">
                <div class="smartsite-ai-welcome-panel">
                    <h2><?php _e('Welcome to SmartSite AI', 'smartsite-ai'); ?></h2>
                    <p><?php _e('Your cutting-edge WordPress AI page builder plugin designed to revolutionize website development.', 'smartsite-ai'); ?></p>
                    
                    <div class="smartsite-ai-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=smartsite-ai-builder'); ?>" class="button button-primary button-hero">
                            <?php _e('Start Building', 'smartsite-ai'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=smartsite-ai-templates'); ?>" class="button button-secondary">
                            <?php _e('Browse Templates', 'smartsite-ai'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="smartsite-ai-features-overview">
                    <h3><?php _e('Key Features', 'smartsite-ai'); ?></h3>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <div class="feature-icon">🤖</div>
                            <h4><?php _e('AI-Powered Content Generation', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Generate engaging, tailored content that aligns with your brand.', 'smartsite-ai'); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎨</div>
                            <h4><?php _e('Intuitive Design Assistance', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Create visually appealing layouts with AI-powered design tools.', 'smartsite-ai'); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">💬</div>
                            <h4><?php _e('Natural Language Prompts', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Describe your desired elements in plain text and watch them come to life.', 'smartsite-ai'); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">⚡</div>
                            <h4><?php _e('Real-Time Component Builder', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Build interactive components and entire pages in real time.', 'smartsite-ai'); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">📈</div>
                            <h4><?php _e('AI-Driven Optimization', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Automatically optimize for SEO, performance, and user experience.', 'smartsite-ai'); ?></p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🔗</div>
                            <h4><?php _e('Plugin Integration', 'smartsite-ai'); ?></h4>
                            <p><?php _e('Seamlessly integrate with popular WordPress plugins.', 'smartsite-ai'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="smartsite-ai-recent-activity">
                    <h3><?php _e('Recent Activity', 'smartsite-ai'); ?></h3>
                    <div class="activity-list">
                        <p><?php _e('No recent activity. Start building to see your progress here!', 'smartsite-ai'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .smartsite-ai-admin {
            background: #f1f1f1;
            margin: 0 -20px;
            padding: 20px;
        }
        
        .smartsite-ai-dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .smartsite-ai-welcome-panel {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .smartsite-ai-quick-actions {
            margin-top: 20px;
        }
        
        .smartsite-ai-quick-actions .button {
            margin: 0 10px;
        }
        
        .smartsite-ai-features-overview {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .feature-item {
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .smartsite-ai-recent-activity {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        </style>
        <?php
    }
    
    /**
     * Page builder interface
     */
    public function builder_page() {
        ?>
        <div class="wrap smartsite-ai-builder">
            <h1><?php _e('SmartSite AI Page Builder', 'smartsite-ai'); ?></h1>
            
            <div class="smartsite-ai-builder-interface">
                <div class="builder-sidebar">
                    <div class="sidebar-section">
                        <h3><?php _e('Natural Language Input', 'smartsite-ai'); ?></h3>
                        <textarea id="ai-prompt" placeholder="<?php _e('Describe what you want to create, e.g., "Create a hero section with a call-to-action button and an image"', 'smartsite-ai'); ?>"></textarea>
                        <button id="generate-from-prompt" class="button button-primary"><?php _e('Generate', 'smartsite-ai'); ?></button>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3><?php _e('Components', 'smartsite-ai'); ?></h3>
                        <div class="component-list">
                            <div class="component-item" data-type="hero">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php _e('Hero Section', 'smartsite-ai'); ?>
                            </div>
                            <div class="component-item" data-type="features">
                                <span class="dashicons dashicons-grid-view"></span>
                                <?php _e('Features Grid', 'smartsite-ai'); ?>
                            </div>
                            <div class="component-item" data-type="testimonials">
                                <span class="dashicons dashicons-format-quote"></span>
                                <?php _e('Testimonials', 'smartsite-ai'); ?>
                            </div>
                            <div class="component-item" data-type="contact">
                                <span class="dashicons dashicons-email"></span>
                                <?php _e('Contact Form', 'smartsite-ai'); ?>
                            </div>
                            <div class="component-item" data-type="gallery">
                                <span class="dashicons dashicons-format-gallery"></span>
                                <?php _e('Image Gallery', 'smartsite-ai'); ?>
                            </div>
                            <div class="component-item" data-type="blog">
                                <span class="dashicons dashicons-admin-post"></span>
                                <?php _e('Blog Posts', 'smartsite-ai'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3><?php _e('AI Tools', 'smartsite-ai'); ?></h3>
                        <button id="optimize-page" class="button"><?php _e('Optimize Page', 'smartsite-ai'); ?></button>
                        <button id="generate-content" class="button"><?php _e('Generate Content', 'smartsite-ai'); ?></button>
                        <button id="suggest-improvements" class="button"><?php _e('Suggest Improvements', 'smartsite-ai'); ?></button>
                    </div>
                </div>
                
                <div class="builder-canvas">
                    <div class="canvas-header">
                        <div class="canvas-controls">
                            <button id="preview-desktop" class="button active"><?php _e('Desktop', 'smartsite-ai'); ?></button>
                            <button id="preview-tablet" class="button"><?php _e('Tablet', 'smartsite-ai'); ?></button>
                            <button id="preview-mobile" class="button"><?php _e('Mobile', 'smartsite-ai'); ?></button>
                        </div>
                        <div class="canvas-actions">
                            <button id="save-page" class="button button-primary"><?php _e('Save Page', 'smartsite-ai'); ?></button>
                            <button id="publish-page" class="button button-secondary"><?php _e('Publish', 'smartsite-ai'); ?></button>
                        </div>
                    </div>
                    
                    <div id="page-canvas" class="page-canvas">
                        <div class="canvas-placeholder">
                            <h2><?php _e('Start Building Your Page', 'smartsite-ai'); ?></h2>
                            <p><?php _e('Use natural language prompts or drag components from the sidebar to begin.', 'smartsite-ai'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="builder-properties">
                    <h3><?php _e('Properties', 'smartsite-ai'); ?></h3>
                    <div id="component-properties">
                        <p><?php _e('Select a component to edit its properties.', 'smartsite-ai'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .smartsite-ai-builder-interface {
            display: flex;
            height: calc(100vh - 100px);
            gap: 20px;
        }
        
        .builder-sidebar {
            width: 300px;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        #ai-prompt {
            width: 100%;
            height: 100px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .component-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .component-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .component-item:hover {
            background: #f0f0f0;
            border-color: #0073aa;
        }
        
        .builder-canvas {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .canvas-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .canvas-controls button {
            margin-right: 10px;
        }
        
        .canvas-controls button.active {
            background: #0073aa;
            color: white;
        }
        
        .page-canvas {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f9f9f9;
        }
        
        .canvas-placeholder {
            text-align: center;
            padding: 100px 20px;
            color: #666;
        }
        
        .builder-properties {
            width: 300px;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .builder-properties h3 {
            margin-bottom: 15px;
            color: #333;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Natural language prompt generation
            $('#generate-from-prompt').on('click', function() {
                var prompt = $('#ai-prompt').val();
                if (!prompt) {
                    alert('<?php _e('Please enter a description of what you want to create.', 'smartsite-ai'); ?>');
                    return;
                }
                
                $(this).text('<?php _e('Generating...', 'smartsite-ai'); ?>').prop('disabled', true);
                
                $.ajax({
                    url: smartsite_ai_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smartsite_ai_generate_layout',
                        prompt: prompt,
                        nonce: smartsite_ai_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#page-canvas').html(response.data.html);
                            $('.canvas-placeholder').hide();
                        } else {
                            alert(response.data || smartsite_ai_ajax.strings.error);
                        }
                    },
                    error: function() {
                        alert(smartsite_ai_ajax.strings.error);
                    },
                    complete: function() {
                        $('#generate-from-prompt').text('<?php _e('Generate', 'smartsite-ai'); ?>').prop('disabled', false);
                    }
                });
            });
            
            // Component drag and drop
            $('.component-item').on('click', function() {
                var componentType = $(this).data('type');
                addComponent(componentType);
            });
            
            function addComponent(type) {
                $.ajax({
                    url: smartsite_ai_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smartsite_ai_generate_content',
                        component_type: type,
                        nonce: smartsite_ai_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if ($('.canvas-placeholder').is(':visible')) {
                                $('#page-canvas').html('');
                                $('.canvas-placeholder').hide();
                            }
                            $('#page-canvas').append(response.data.html);
                        }
                    }
                });
            }
            
            // Responsive preview controls
            $('.canvas-controls button').on('click', function() {
                $('.canvas-controls button').removeClass('active');
                $(this).addClass('active');
                
                var device = $(this).attr('id').replace('preview-', '');
                $('#page-canvas').removeClass('desktop tablet mobile').addClass(device);
            });
            
            // Page optimization
            $('#optimize-page').on('click', function() {
                $(this).text('<?php _e('Optimizing...', 'smartsite-ai'); ?>').prop('disabled', true);
                
                $.ajax({
                    url: smartsite_ai_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smartsite_ai_optimize_page',
                        content: $('#page-canvas').html(),
                        nonce: smartsite_ai_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Page optimized successfully! Check the suggestions in the properties panel.', 'smartsite-ai'); ?>');
                            $('#component-properties').html(response.data.suggestions);
                        }
                    },
                    complete: function() {
                        $('#optimize-page').text('<?php _e('Optimize Page', 'smartsite-ai'); ?>').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Templates page
     */
    public function templates_page() {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'smartsite_ai_templates';
        $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY created_at DESC");
        ?>
        <div class="wrap smartsite-ai-templates">
            <h1><?php _e('SmartSite AI Templates', 'smartsite-ai'); ?></h1>
            
            <div class="templates-grid">
                <?php foreach ($templates as $template): ?>
                <div class="template-card" data-template-id="<?php echo $template->id; ?>">
                    <div class="template-preview">
                        <?php if ($template->preview_image): ?>
                            <img src="<?php echo esc_url($template->preview_image); ?>" alt="<?php echo esc_attr($template->name); ?>">
                        <?php else: ?>
                            <div class="template-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="template-info">
                        <h3><?php echo esc_html($template->name); ?></h3>
                        <p class="template-category"><?php echo esc_html(ucfirst($template->category)); ?></p>
                        <div class="template-actions">
                            <button class="button button-primary use-template" data-template-id="<?php echo $template->id; ?>">
                                <?php _e('Use Template', 'smartsite-ai'); ?>
                            </button>
                            <button class="button preview-template" data-template-id="<?php echo $template->id; ?>">
                                <?php _e('Preview', 'smartsite-ai'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="template-categories">
                <h2><?php _e('Browse by Category', 'smartsite-ai'); ?></h2>
                <div class="category-filters">
                    <button class="button category-filter active" data-category="all"><?php _e('All', 'smartsite-ai'); ?></button>
                    <button class="button category-filter" data-category="business"><?php _e('Business', 'smartsite-ai'); ?></button>
                    <button class="button category-filter" data-category="portfolio"><?php _e('Portfolio', 'smartsite-ai'); ?></button>
                    <button class="button category-filter" data-category="blog"><?php _e('Blog', 'smartsite-ai'); ?></button>
                    <button class="button category-filter" data-category="ecommerce"><?php _e('E-commerce', 'smartsite-ai'); ?></button>
                </div>
            </div>
        </div>
        
        <style>
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .template-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .template-card:hover {
            transform: translateY(-5px);
        }
        
        .template-preview {
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .template-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .template-placeholder {
            font-size: 3em;
            color: #ccc;
        }
        
        .template-info {
            padding: 20px;
        }
        
        .template-info h3 {
            margin: 0 0 10px 0;
        }
        
        .template-category {
            color: #666;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .template-actions {
            display: flex;
            gap: 10px;
        }
        
        .category-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .category-filter.active {
            background: #0073aa;
            color: white;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Use template
            $('.use-template').on('click', function() {
                var templateId = $(this).data('template-id');
                
                $.ajax({
                    url: smartsite_ai_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smartsite_ai_load_template',
                        template_id: templateId,
                        nonce: smartsite_ai_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=smartsite-ai-builder'); ?>';
                        }
                    }
                });
            });
            
            // Category filtering
            $('.category-filter').on('click', function() {
                $('.category-filter').removeClass('active');
                $(this).addClass('active');
                
                var category = $(this).data('category');
                
                if (category === 'all') {
                    $('.template-card').show();
                } else {
                    $('.template-card').hide();
                    $('.template-card[data-category="' + category + '"]').show();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Analytics page
     */
    public function analytics_page() {
        ?>
        <div class="wrap smartsite-ai-analytics">
            <h1><?php _e('SmartSite AI Analytics', 'smartsite-ai'); ?></h1>
            
            <div class="analytics-dashboard">
                <div class="analytics-cards">
                    <div class="analytics-card">
                        <h3><?php _e('Pages Created', 'smartsite-ai'); ?></h3>
                        <div class="metric-value">12</div>
                        <div class="metric-change positive">+3 this week</div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('AI Generations', 'smartsite-ai'); ?></h3>
                        <div class="metric-value">47</div>
                        <div class="metric-change positive">+12 this week</div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Optimization Score', 'smartsite-ai'); ?></h3>
                        <div class="metric-value">94%</div>
                        <div class="metric-change positive">+2% this week</div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Performance Score', 'smartsite-ai'); ?></h3>
                        <div class="metric-value">87%</div>
                        <div class="metric-change neutral">No change</div>
                    </div>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h3><?php _e('Usage Over Time', 'smartsite-ai'); ?></h3>
                        <div class="chart-placeholder">
                            <p><?php _e('Chart visualization would appear here with actual usage data.', 'smartsite-ai'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Most Used Components', 'smartsite-ai'); ?></h3>
                        <div class="component-stats">
                            <div class="stat-item">
                                <span class="component-name"><?php _e('Hero Sections', 'smartsite-ai'); ?></span>
                                <span class="usage-bar"><span style="width: 80%"></span></span>
                                <span class="usage-count">24</span>
                            </div>
                            <div class="stat-item">
                                <span class="component-name"><?php _e('Feature Grids', 'smartsite-ai'); ?></span>
                                <span class="usage-bar"><span style="width: 65%"></span></span>
                                <span class="usage-count">18</span>
                            </div>
                            <div class="stat-item">
                                <span class="component-name"><?php _e('Contact Forms', 'smartsite-ai'); ?></span>
                                <span class="usage-bar"><span style="width: 45%"></span></span>
                                <span class="usage-count">12</span>
                            </div>
                            <div class="stat-item">
                                <span class="component-name"><?php _e('Testimonials', 'smartsite-ai'); ?></span>
                                <span class="usage-bar"><span style="width: 30%"></span></span>
                                <span class="usage-count">8</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-recommendations">
                    <h3><?php _e('AI Recommendations', 'smartsite-ai'); ?></h3>
                    <div class="recommendation-list">
                        <div class="recommendation-item">
                            <span class="dashicons dashicons-lightbulb"></span>
                            <div class="recommendation-content">
                                <h4><?php _e('Improve Page Speed', 'smartsite-ai'); ?></h4>
                                <p><?php _e('Consider optimizing images and reducing the number of plugins to improve loading times.', 'smartsite-ai'); ?></p>
                            </div>
                        </div>
                        <div class="recommendation-item">
                            <span class="dashicons dashicons-search"></span>
                            <div class="recommendation-content">
                                <h4><?php _e('SEO Enhancement', 'smartsite-ai'); ?></h4>
                                <p><?php _e('Add meta descriptions to your pages to improve search engine visibility.', 'smartsite-ai'); ?></p>
                            </div>
                        </div>
                        <div class="recommendation-item">
                            <span class="dashicons dashicons-smartphone"></span>
                            <div class="recommendation-content">
                                <h4><?php _e('Mobile Optimization', 'smartsite-ai'); ?></h4>
                                <p><?php _e('Some components could be better optimized for mobile devices.', 'smartsite-ai'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .analytics-dashboard {
            max-width: 1200px;
        }
        
        .analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .analytics-card h3 {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .metric-change {
            font-size: 0.9em;
        }
        
        .metric-change.positive {
            color: #4CAF50;
        }
        
        .metric-change.negative {
            color: #f44336;
        }
        
        .metric-change.neutral {
            color: #666;
        }
        
        .analytics-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-placeholder {
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #666;
        }
        
        .component-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .component-name {
            flex: 0 0 120px;
            font-size: 0.9em;
        }
        
        .usage-bar {
            flex: 1;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .usage-bar span {
            display: block;
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        
        .usage-count {
            flex: 0 0 30px;
            text-align: right;
            font-weight: bold;
            color: #666;
        }
        
        .analytics-recommendations {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .recommendation-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .recommendation-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .recommendation-item .dashicons {
            color: #0073aa;
            margin-top: 2px;
        }
        
        .recommendation-content h4 {
            margin: 0 0 5px 0;
        }
        
        .recommendation-content p {
            margin: 0;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('smartsite_ai_api_key', sanitize_text_field($_POST['api_key']));
            update_option('smartsite_ai_optimization_level', sanitize_text_field($_POST['optimization_level']));
            update_option('smartsite_ai_auto_optimize', isset($_POST['auto_optimize']));
            update_option('smartsite_ai_collaboration_enabled', isset($_POST['collaboration_enabled']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'smartsite-ai') . '</p></div>';
        }
        
        $api_key = get_option('smartsite_ai_api_key', '');
        $optimization_level = get_option('smartsite_ai_optimization_level', 'balanced');
        $auto_optimize = get_option('smartsite_ai_auto_optimize', false);
        $collaboration_enabled = get_option('smartsite_ai_collaboration_enabled', false);
        ?>
        <div class="wrap smartsite-ai-settings">
            <h1><?php _e('SmartSite AI Settings', 'smartsite-ai'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('smartsite_ai_settings', 'smartsite_ai_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('AI API Key', 'smartsite-ai'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your AI service API key for content generation and optimization features.', 'smartsite-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="optimization_level"><?php _e('Optimization Level', 'smartsite-ai'); ?></label>
                        </th>
                        <td>
                            <select id="optimization_level" name="optimization_level">
                                <option value="basic" <?php selected($optimization_level, 'basic'); ?>><?php _e('Basic', 'smartsite-ai'); ?></option>
                                <option value="balanced" <?php selected($optimization_level, 'balanced'); ?>><?php _e('Balanced', 'smartsite-ai'); ?></option>
                                <option value="aggressive" <?php selected($optimization_level, 'aggressive'); ?>><?php _e('Aggressive', 'smartsite-ai'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose how aggressively the AI should optimize your pages.', 'smartsite-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-Optimization', 'smartsite-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_optimize" value="1" <?php checked($auto_optimize); ?> />
                                <?php _e('Automatically optimize pages after creation', 'smartsite-ai'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, pages will be automatically optimized using AI recommendations.', 'smartsite-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Collaboration Tools', 'smartsite-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="collaboration_enabled" value="1" <?php checked($collaboration_enabled); ?> />
                                <?php _e('Enable collaboration features', 'smartsite-ai'); ?>
                            </label>
                            <p class="description"><?php _e('Allow team members to collaborate on page building projects.', 'smartsite-ai'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Integration Settings', 'smartsite-ai'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Integrations', 'smartsite-ai'); ?></th>
                        <td>
                            <div class="integration-list">
                                <label>
                                    <input type="checkbox" name="integrate_woocommerce" value="1" <?php checked(is_plugin_active('woocommerce/woocommerce.php')); ?> />
                                    <?php _e('WooCommerce Integration', 'smartsite-ai'); ?>
                                    <?php if (!is_plugin_active('woocommerce/woocommerce.php')): ?>
                                        <span class="description"><?php _e('(WooCommerce not detected)', 'smartsite-ai'); ?></span>
                                    <?php endif; ?>
                                </label>
                                <br><br>
                                <label>
                                    <input type="checkbox" name="integrate_yoast" value="1" <?php checked(is_plugin_active('wordpress-seo/wp-seo.php')); ?> />
                                    <?php _e('Yoast SEO Integration', 'smartsite-ai'); ?>
                                    <?php if (!is_plugin_active('wordpress-seo/wp-seo.php')): ?>
                                        <span class="description"><?php _e('(Yoast SEO not detected)', 'smartsite-ai'); ?></span>
                                    <?php endif; ?>
                                </label>
                                <br><br>
                                <label>
                                    <input type="checkbox" name="integrate_elementor" value="1" <?php checked(is_plugin_active('elementor/elementor.php')); ?> />
                                    <?php _e('Elementor Integration', 'smartsite-ai'); ?>
                                    <?php if (!is_plugin_active('elementor/elementor.php')): ?>
                                        <span class="description"><?php _e('(Elementor not detected)', 'smartsite-ai'); ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="smartsite-ai-help-section">
                <h2><?php _e('Getting Started', 'smartsite-ai'); ?></h2>
                <div class="help-cards">
                    <div class="help-card">
                        <h3><?php _e('1. Setup and Installation', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Configure your API key and optimization preferences above.', 'smartsite-ai'); ?></p>
                    </div>
                    <div class="help-card">
                        <h3><?php _e('2. Initial Configuration', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Visit the Page Builder to input your site details and preferences.', 'smartsite-ai'); ?></p>
                    </div>
                    <div class="help-card">
                        <h3><?php _e('3. Design and Content Creation', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Use natural language prompts to create sections and customize layouts.', 'smartsite-ai'); ?></p>
                    </div>
                    <div class="help-card">
                        <h3><?php _e('4. Optimization', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Run AI optimization tools to improve SEO and performance.', 'smartsite-ai'); ?></p>
                    </div>
                    <div class="help-card">
                        <h3><?php _e('5. Integration and Testing', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Connect with other plugins and test across devices.', 'smartsite-ai'); ?></p>
                    </div>
                    <div class="help-card">
                        <h3><?php _e('6. Launch and Maintenance', 'smartsite-ai'); ?></h3>
                        <p><?php _e('Publish your website and monitor performance with built-in analytics.', 'smartsite-ai'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .smartsite-ai-help-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .help-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .help-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .help-card h3 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .integration-list label {
            display: block;
            margin-bottom: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Generate content based on component type
     */
    public function ajax_generate_content() {
        check_ajax_referer('smartsite_ai_nonce', 'nonce');
        
        $component_type = sanitize_text_field($_POST['component_type']);
        
        $components = array(
            'hero' => array(
                'html' => '<div class="smartsite-ai-component smartsite-ai-hero">
                    <h1>Welcome to Our Amazing Service</h1>
                    <p>Discover the power of AI-driven web design and take your online presence to the next level.</p>
                    <a href="#" class="smartsite-ai-cta">Get Started Today</a>
                </div>',
                'settings' => array('background_color' => '#667eea', 'text_color' => '#ffffff')
            ),
            'features' => array(
                'html' => '<div class="smartsite-ai-component smartsite-ai-features">
                    <div class="smartsite-ai-feature">
                        <div class="smartsite-ai-feature-icon">🚀</div>
                        <h3>Lightning Fast</h3>
                        <p>Optimized for speed and performance across all devices.</p>
                    </div>
                    <div class="smartsite-ai-feature">
                        <div class="smartsite-ai-feature-icon">🎨</div>
                        <h3>Beautiful Design</h3>
                        <p>Stunning layouts created with AI-powered design assistance.</p>
                    </div>
                    <div class="smartsite-ai-feature">
                        <div class="smartsite-ai-feature-icon">🔧</div>
                        <h3>Easy to Use</h3>
                        <p>Intuitive interface that anyone can master in minutes.</p>
                    </div>
                </div>',
                'settings' => array('columns' => 3, 'spacing' => 'normal')
            ),
            'testimonials' => array(
                'html' => '<div class="smartsite-ai-component">
                    <div class="smartsite-ai-testimonial">
                        <p>"SmartSite AI transformed our website development process. The AI-powered features saved us countless hours and delivered exceptional results."</p>
                        <cite>- Sarah Johnson, Marketing Director</cite>
                    </div>
                    <div class="smartsite-ai-testimonial">
                        <p>"The natural language prompts make it so easy to create exactly what we envision. This tool is a game-changer for our team."</p>
                        <cite>- Mike Chen, Web Developer</cite>
                    </div>
                </div>',
                'settings' => array('layout' => 'grid', 'show_avatars' => false)
            ),
            'contact' => array(
                'html' => '<div class="smartsite-ai-component">
                    <h2 style="text-align: center; margin-bottom: 30px;">Get In Touch</h2>
                    <form class="smartsite-ai-contact-form">
                        <div class="smartsite-ai-form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="smartsite-ai-form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="smartsite-ai-form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        <div class="smartsite-ai-form-group">
                            <button type="submit" class="smartsite-ai-cta">Send Message</button>
                        </div>
                    </form>
                </div>',
                'settings' => array('form_style' => 'modern', 'required_fields' => array('name', 'email', 'message'))
            ),
            'gallery' => array(
                'html' => '<div class="smartsite-ai-component">
                    <h2 style="text-align: center; margin-bottom: 30px;">Our Gallery</h2>
                    <div class="smartsite-ai-features">
                        <div class="smartsite-ai-feature">
                            <div style="height: 200px; background: linear-gradient(45deg, #ff6b6b, #ffa500); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>Project Alpha</h4>
                            <p>A stunning example of modern web design.</p>
                        </div>
                        <div class="smartsite-ai-feature">
                            <div style="height: 200px; background: linear-gradient(45deg, #4ecdc4, #44a08d); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>Project Beta</h4>
                            <p>Innovative solutions for complex challenges.</p>
                        </div>
                        <div class="smartsite-ai-feature">
                            <div style="height: 200px; background: linear-gradient(45deg, #667eea, #764ba2); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>Project Gamma</h4>
                            <p>Creative excellence in every detail.</p>
                        </div>
                    </div>
                </div>',
                'settings' => array('columns' => 3, 'lightbox' => true)
            ),
            'blog' => array(
                'html' => '<div class="smartsite-ai-component">
                    <h2 style="text-align: center; margin-bottom: 30px;">Latest Articles</h2>
                    <div class="smartsite-ai-features">
                        <div class="smartsite-ai-feature">
                            <div style="height: 150px; background: linear-gradient(45deg, #ff9a9e, #fecfef); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>The Future of AI in Web Design</h4>
                            <p>Exploring how artificial intelligence is revolutionizing the way we create websites...</p>
                            <a href="#" class="smartsite-ai-cta" style="font-size: 0.9em; padding: 8px 16px;">Read More</a>
                        </div>
                        <div class="smartsite-ai-feature">
                            <div style="height: 150px; background: linear-gradient(45deg, #a8edea, #fed6e3); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>Best Practices for Page Optimization</h4>
                            <p>Learn the essential techniques for creating fast, SEO-friendly websites...</p>
                            <a href="#" class="smartsite-ai-cta" style="font-size: 0.9em; padding: 8px 16px;">Read More</a>
                        </div>
                        <div class="smartsite-ai-feature">
                            <div style="height: 150px; background: linear-gradient(45deg, #ffecd2, #fcb69f); border-radius: 8px; margin-bottom: 15px;"></div>
                            <h4>User Experience Design Trends</h4>
                            <p>Stay ahead of the curve with the latest UX design trends and methodologies...</p>
                            <a href="#" class="smartsite-ai-cta" style="font-size: 0.9em; padding: 8px 16px;">Read More</a>
                        </div>
                    </div>
                </div>',
                'settings' => array('posts_per_page' => 3, 'show_excerpts' => true)
            )
        );
        
        if (isset($components[$component_type])) {
            wp_send_json_success($components[$component_type]);
        } else {
            wp_send_json_error(__('Component type not found.', 'smartsite-ai'));
        }
    }
    
    /**
     * AJAX: Generate layout from natural language prompt
     */
    public function ajax_generate_layout() {
        check_ajax_referer('smartsite_ai_nonce', 'nonce');
        
        $prompt = sanitize_text_field($_POST['prompt']);
        
        // Simulate AI processing - in a real implementation, this would call an AI service
        $html = $this->process_natural_language_prompt($prompt);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Process natural language prompt and generate HTML
     */
    private function process_natural_language_prompt($prompt) {
        $prompt_lower = strtolower($prompt);
        $html = '';
        
        // Simple keyword matching - in a real implementation, this would use NLP
        if (strpos($prompt_lower, 'hero') !== false || strpos($prompt_lower, 'header') !== false) {
            $html .= '<div class="smartsite-ai-component smartsite-ai-hero">
                <h1>AI-Generated Hero Section</h1>
                <p>This hero section was created based on your natural language prompt.</p>
                <a href="#" class="smartsite-ai-cta">Call to Action</a>
            </div>';
        }
        
        if (strpos($prompt_lower, 'feature') !== false || strpos($prompt_lower, 'service') !== false) {
            $html .= '<div class="smartsite-ai-component smartsite-ai-features">
                <div class="smartsite-ai-feature">
                    <div class="smartsite-ai-feature-icon">⭐</div>
                    <h3>Feature One</h3>
                    <p>Description of your first feature or service.</p>
                </div>
                <div class="smartsite-ai-feature">
                    <div class="smartsite-ai-feature-icon">🎯</div>
                    <h3>Feature Two</h3>
                    <p>Description of your second feature or service.</p>
                </div>
                <div class="smartsite-ai-feature">
                    <div class="smartsite-ai-feature-icon">🚀</div>
                    <h3>Feature Three</h3>
                    <p>Description of your third feature or service.</p>
                </div>
            </div>';
        }
        
        if (strpos($prompt_lower, 'contact') !== false || strpos($prompt_lower, 'form') !== false) {
            $html .= '<div class="smartsite-ai-component">
                <h2 style="text-align: center; margin-bottom: 30px;">Contact Us</h2>
                <form class="smartsite-ai-contact-form">
                    <div class="smartsite-ai-form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="smartsite-ai-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="smartsite-ai-form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <div class="smartsite-ai-form-group">
                        <button type="submit" class="smartsite-ai-cta">Send Message</button>
                    </div>
                </form>
            </div>';
        }
        
        if (strpos($prompt_lower, 'testimonial') !== false || strpos($prompt_lower, 'review') !== false) {
            $html .= '<div class="smartsite-ai-component">
                <div class="smartsite-ai-testimonial">
                    <p>"This is an AI-generated testimonial based on your prompt. It demonstrates the power of natural language processing."</p>
                    <cite>- Happy Customer</cite>
                </div>
            </div>';
        }
        
        // Default fallback
        if (empty($html)) {
            $html = '<div class="smartsite-ai-component smartsite-ai-hero">
                <h1>AI-Generated Content</h1>
                <p>Based on your prompt: "' . esc_html($prompt) . '"</p>
                <p>The AI has interpreted your request and created this content. You can further customize it using the properties panel.</p>
            </div>';
        }
        
        return $html;
    }
    
    /**
     * AJAX: Optimize page content
     */
    public function ajax_optimize_page() {
        check_ajax_referer('smartsite_ai_nonce', 'nonce');
        
        $content = wp_kses_post($_POST['content']);
        
        // Simulate AI optimization analysis
        $suggestions = array(
            'seo' => array(
                'title' => __('SEO Improvements', 'smartsite-ai'),
                'items' => array(
                    __('Add meta description to improve search visibility', 'smartsite-ai'),
                    __('Include more relevant keywords in headings', 'smartsite-ai'),
                    __('Optimize image alt texts for better accessibility', 'smartsite-ai')
                )
            ),
            'performance' => array(
                'title' => __('Performance Optimizations', 'smartsite-ai'),
                'items' => array(
                    __('Compress images to reduce loading time', 'smartsite-ai'),
                    __('Minimize CSS and JavaScript files', 'smartsite-ai'),
                    __('Enable browser caching for static resources', 'smartsite-ai')
                )
            ),
            'ux' => array(
                'title' => __('User Experience Enhancements', 'smartsite-ai'),
                'items' => array(
                    __('Improve mobile responsiveness', 'smartsite-ai'),
                    __('Add loading animations for better perceived performance', 'smartsite-ai'),
                    __('Enhance color contrast for better accessibility', 'smartsite-ai')
                )
            )
        );
        
        $suggestions_html = '<div class="optimization-suggestions">';
        foreach ($suggestions as $category => $data) {
            $suggestions_html .= '<h4>' . $data['title'] . '</h4>';
            $suggestions_html .= '<ul>';
            foreach ($data['items'] as $item) {
                $suggestions_html .= '<li>' . $item . '</li>';
            }
            $suggestions_html .= '</ul>';
        }
        $suggestions_html .= '</div>';
        
        wp_send_json_success(array('suggestions' => $suggestions_html));
    }
    
    /**
     * AJAX: Save component
     */
    public function ajax_save_component() {
        check_ajax_referer('smartsite_ai_nonce', 'nonce');
        
        global $wpdb;
        $components_table = $wpdb->prefix . 'smartsite_ai_components';
        
        $name = sanitize_text_field($_POST['name']);
        $type = sanitize_text_field($_POST['type']);
        $content = wp_kses_post($_POST['content']);
        $settings = sanitize_text_field($_POST['settings']);
        
        $result = $wpdb->insert(
            $components_table,
            array(
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'settings' => $settings
            )
        );
        
        if ($result) {
            wp_send_json_success(array('id' => $wpdb->insert_id));
        } else {
            wp_send_json_error(__('Failed to save component.', 'smartsite-ai'));
        }
    }
    
    /**
     * AJAX: Load template
     */
    public function ajax_load_template() {
        check_ajax_referer('smartsite_ai_nonce', 'nonce');
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'smartsite_ai_templates';
        
        $template_id = intval($_POST['template_id']);
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));
        
        if ($template) {
            // Store template data in session for the builder
            set_transient('smartsite_ai_active_template_' . get_current_user_id(), $template->layout, HOUR_IN_SECONDS);
            wp_send_json_success(array('template' => $template));
        } else {
            wp_send_json_error(__('Template not found.', 'smartsite-ai'));
        }
    }
    
    /**
     * Render component shortcode
     */
    public function render_component_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'type' => 'hero'
        ), $atts);
        
        global $wpdb;
        $components_table = $wpdb->prefix . 'smartsite_ai_components';
        
        if (!empty($atts['id'])) {
            $component = $wpdb->get_row($wpdb->prepare("SELECT * FROM $components_table WHERE id = %d", $atts['id']));
            if ($component) {
                return $component->content;
            }
        }
        
        // Return default component based on type
        $defaults = array(
            'hero' => '<div class="smartsite-ai-component smartsite-ai-hero">
                <h1>Welcome to Our Website</h1>
                <p>Discover amazing content and services.</p>
                <a href="#" class="smartsite-ai-cta">Learn More</a>
            </div>'
        );
        
        return isset($defaults[$atts['type']]) ? $defaults[$atts['type']] : '';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        
        // Set default options
        add_option('smartsite_ai_optimization_level', 'balanced');
        add_option('smartsite_ai_auto_optimize', false);
        add_option('smartsite_ai_collaboration_enabled', false);
        
        // Create default page
        $default_page = array(
            'post_title' => __('SmartSite AI Demo Page', 'smartsite-ai'),
            'post_content' => '[smartsite_ai_component type="hero"]',
            'post_status' => 'draft',
            'post_type' => 'page'
        );
        
        wp_insert_post($default_page);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_smartsite_ai_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_smartsite_ai_%'");
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
SmartSite_AI::get_instance();

// Add inline CSS and JS for the frontend
add_action('wp_head', function() {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Add smooth scrolling for CTA buttons
        document.querySelectorAll(".smartsite-ai-cta").forEach(function(button) {
            button.addEventListener("click", function(e) {
                if (this.getAttribute("href") === "#") {
                    e.preventDefault();
                }
            });
        });
        
        // Add form submission handling
        document.querySelectorAll(".smartsite-ai-contact-form").forEach(function(form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                alert("Thank you for your message! This is a demo form.");
            });
        });
        
        // Add hover effects
        document.querySelectorAll(".smartsite-ai-feature").forEach(function(feature) {
            feature.addEventListener("mouseenter", function() {
                this.style.transform = "translateY(-5px)";
            });
            feature.addEventListener("mouseleave", function() {
                this.style.transform = "translateY(0)";
            });
        });
    });
    </script>';
});