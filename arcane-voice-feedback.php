<?php
/**
 * Plugin Name: Arcane Voice Feedback
 * Plugin URI: https://example.com/arcane-voice-feedback
 * Description: A customizable voice feedback recorder block with editable styling, text, and button options
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: arcane-voice-feedback
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Arcane_Voice_Feedback {
    
    private $plugin_slug = 'arcane-voice-feedback';
    private $version = '1.2.0';
    private $github_username = 'Clark1hunter'; // Change this to your GitHub username
    private $github_repo = 'Wordpress_Plugins'; // Change this to your GitHub repo name
    
    public function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('wp_ajax_avf_save_recording', array($this, 'save_recording'));
        add_action('wp_ajax_nopriv_avf_save_recording', array($this, 'save_recording'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_avf_delete_recording', array($this, 'delete_recording'));
        add_action('wp_ajax_avf_get_recordings', array($this, 'get_recordings'));
        
        // Add auto-update hooks
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }
    
    // Check for plugin updates
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_slug = plugin_basename(__FILE__);
        
        // Get remote version
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->version, $remote_version, '<')) {
            $plugin_data = array(
                'slug' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
                'package' => "https://github.com/{$this->github_username}/{$this->github_repo}/archive/refs/tags/v{$remote_version}.zip",
                'tested' => get_bloginfo('version'),
                'requires_php' => '7.4',
                'compatibility' => new stdClass(),
            );
            
            $transient->response[$plugin_slug] = (object) $plugin_data;
        }
        
        return $transient;
    }
    
    // Get remote version from GitHub
    private function get_remote_version() {
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (isset($data->tag_name)) {
            return str_replace('v', '', $data->tag_name);
        }
        
        return false;
    }
    
    // Provide plugin information for update screen
    public function plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $remote_version = $this->get_remote_version();
        
        $info = array(
            'name' => 'Arcane Voice Feedback',
            'slug' => $this->plugin_slug,
            'version' => $remote_version ?: $this->version,
            'author' => '<a href="https://github.com/' . $this->github_username . '">Your Name</a>',
            'homepage' => "https://github.com/{$this->github_username}/{$this->github_repo}",
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'download_link' => "https://github.com/{$this->github_username}/{$this->github_repo}/archive/refs/tags/v{$remote_version}.zip",
            'sections' => array(
                'description' => 'A fully customizable voice feedback recorder plugin for WordPress with floating widgets, waveform visualization, and comprehensive admin controls.',
                'installation' => 'Upload the plugin to your plugins directory and activate it.',
                'changelog' => $this->get_changelog(),
            ),
        );
        
        return (object) $info;
    }
    
    // Get changelog from GitHub
    private function get_changelog() {
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return 'Unable to fetch changelog.';
        }
        
        $body = wp_remote_retrieve_body($response);
        $releases = json_decode($body);
        
        if (!is_array($releases)) {
            return 'Unable to fetch changelog.';
        }
        
        $changelog = '<h4>Recent Updates</h4>';
        
        foreach (array_slice($releases, 0, 5) as $release) {
            $version = str_replace('v', '', $release->tag_name);
            $changelog .= '<h5>Version ' . esc_html($version) . '</h5>';
            $changelog .= '<p>' . wp_kses_post($release->body) . '</p>';
        }
        
        return $changelog;
    }
    
    // After installation, fix the plugin folder name
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $plugin_slug = $this->plugin_slug;
        $install_directory = plugin_dir_path(__FILE__);
        
        // Move the plugin to the correct directory if needed
        $proper_destination = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        if ($result['destination'] !== $proper_destination) {
            $wp_filesystem->move($result['destination'], $proper_destination);
            $result['destination'] = $proper_destination;
        }
        
        return $result;
    }
    
    public function register_block() {
        // Register the block
        register_block_type('arcane/voice-feedback', array(
            'editor_script' => 'arcane-voice-feedback-editor',
            'editor_style' => 'arcane-voice-feedback-editor-style',
            'style' => 'arcane-voice-feedback-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'recordButtonText' => array(
                    'type' => 'string',
                    'default' => 'Record Voice Feedback'
                ),
                'reviewButtonText' => array(
                    'type' => 'string',
                    'default' => 'Review'
                ),
                'sendButtonText' => array(
                    'type' => 'string',
                    'default' => 'Send'
                ),
                'rerecordButtonText' => array(
                    'type' => 'string',
                    'default' => 'Re-record'
                ),
                'width' => array(
                    'type' => 'string',
                    'default' => '400px'
                ),
                'height' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'borderColor' => array(
                    'type' => 'string',
                    'default' => '#cccccc'
                ),
                'borderWidth' => array(
                    'type' => 'string',
                    'default' => '2px'
                ),
                'fieldColor' => array(
                    'type' => 'string',
                    'default' => '#ffffff'
                ),
                'buttonColor' => array(
                    'type' => 'string',
                    'default' => '#0073aa'
                ),
                'buttonTextColor' => array(
                    'type' => 'string',
                    'default' => '#ffffff'
                ),
                'recordButtonImage' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'reviewButtonImage' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'sendButtonImage' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'rerecordButtonImage' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'borderRadius' => array(
                    'type' => 'string',
                    'default' => '5px'
                ),
                'padding' => array(
                    'type' => 'string',
                    'default' => '20px'
                ),
                'fontSize' => array(
                    'type' => 'string',
                    'default' => '16px'
                ),
                'recordButtonWidth' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'recordButtonHeight' => array(
                    'type' => 'string',
                    'default' => '40px'
                ),
                'reviewButtonWidth' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'reviewButtonHeight' => array(
                    'type' => 'string',
                    'default' => '40px'
                ),
                'sendButtonWidth' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'sendButtonHeight' => array(
                    'type' => 'string',
                    'default' => '40px'
                ),
                'rerecordButtonWidth' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'rerecordButtonHeight' => array(
                    'type' => 'string',
                    'default' => '40px'
                ),
                'showTextUnderImages' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'stopButtonText' => array(
                    'type' => 'string',
                    'default' => 'Stop Recording'
                ),
                'stopButtonWidth' => array(
                    'type' => 'string',
                    'default' => 'auto'
                ),
                'stopButtonHeight' => array(
                    'type' => 'string',
                    'default' => '40px'
                ),
                'stopButtonColor' => array(
                    'type' => 'string',
                    'default' => '#dc3545'
                ),
                'stopButtonTextColor' => array(
                    'type' => 'string',
                    'default' => '#ffffff'
                ),
                'stopButtonImage' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'floatingMode' => array(
                    'type' => 'boolean',
                    'default' => false
                ),
                'floatingPosition' => array(
                    'type' => 'string',
                    'default' => 'bottom-right'
                ),
                'recordButtonIcon' => array(
                    'type' => 'string',
                    'default' => 'microphone'
                ),
                'showIconText' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showWaveform' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'waveformColor' => array(
                    'type' => 'string',
                    'default' => '#0073aa'
                ),
                'displayOnPages' => array(
                    'type' => 'string',
                    'default' => 'all'
                ),
                'specificPages' => array(
                    'type' => 'array',
                    'default' => array()
                )
            )
        ));
        
        // Enqueue editor assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    public function enqueue_editor_assets() {
        $js_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-editor.js';
        
        // Make sure the file exists
        if (!file_exists($js_file)) {
            avf_create_editor_script();
        }
        
        wp_enqueue_script(
            'arcane-voice-feedback-editor',
            plugins_url('arcane-voice-feedback-editor.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            '1.2.0' // Version for cache busting
        );
        
        // Pass admin URL to script
        wp_localize_script('arcane-voice-feedback-editor', 'avfData', array(
            'ajaxUrl' => admin_url('admin-ajax.php')
        ));
    }
    
    public function enqueue_frontend_assets() {
        $css_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-style.css';
        $js_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-frontend.js';
        
        // Make sure files exist
        if (!file_exists($css_file)) {
            avf_create_style();
        }
        if (!file_exists($js_file)) {
            avf_create_frontend_script();
        }
        
        wp_enqueue_style(
            'arcane-voice-feedback-style',
            plugins_url('arcane-voice-feedback-style.css', __FILE__),
            array(),
            '1.2.0' // Version for cache busting
        );
        
        wp_enqueue_script(
            'arcane-voice-feedback-frontend',
            plugins_url('arcane-voice-feedback-frontend.js', __FILE__),
            array(),
            '1.1.0', // Version for cache busting
            true
        );
        
        wp_localize_script('arcane-voice-feedback-frontend', 'avfData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('avf_save_recording')
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Arcane Voice Feedback',
            'Arcane Voice Feedback',
            'manage_options',
            'arcane-voice-feedback',
            array($this, 'render_feedback_page'),
            'dashicons-microphone',
            30
        );
        
        add_submenu_page(
            'arcane-voice-feedback',
            'Feedback Messages',
            'Feedback',
            'manage_options',
            'arcane-voice-feedback',
            array($this, 'render_feedback_page')
        );
        
        add_submenu_page(
            'arcane-voice-feedback',
            'Settings',
            'Settings',
            'manage_options',
            'arcane-voice-feedback-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('avf_settings', 'avf_global_pages', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_pages_setting')
        ));
        
        register_setting('avf_settings', 'avf_max_recording_length', array(
            'type' => 'integer',
            'default' => 300,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('avf_settings', 'avf_global_display_mode', array(
            'type' => 'string',
            'default' => 'all',
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }
    
    public function sanitize_pages_setting($value) {
        if (!is_array($value)) {
            return array();
        }
        return array_map('absint', $value);
    }
    
    public function render_feedback_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Enqueue inline admin styles
        add_action('admin_head', array($this, 'output_admin_css'));
        
        // Enqueue admin script
        wp_enqueue_script('jquery');
        add_action('admin_footer', array($this, 'output_admin_js'));
        
        $upload_dir = wp_upload_dir();
        $feedback_dir = $upload_dir['basedir'] . '/voice-feedback';
        $feedback_url = $upload_dir['baseurl'] . '/voice-feedback';
        
        $recordings = array();
        if (file_exists($feedback_dir)) {
            $files = glob($feedback_dir . '/*.webm');
            foreach ($files as $file) {
                $filename = basename($file);
                $recordings[] = array(
                    'filename' => $filename,
                    'url' => $feedback_url . '/' . $filename,
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'size' => size_format(filesize($file))
                );
            }
            // Sort by date, newest first
            usort($recordings, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }
        
        // Render the page inline
        ?>
        <div class="wrap avf-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="avf-admin-content">
                <?php if (empty($recordings)): ?>
                    <div class="avf-no-recordings">
                        <p><?php _e('No voice feedback recordings yet.', 'arcane-voice-feedback'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="avf-recordings-count">
                        <p><?php printf(__('Total Recordings: %d', 'arcane-voice-feedback'), count($recordings)); ?></p>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'arcane-voice-feedback'); ?></th>
                                <th><?php _e('Audio', 'arcane-voice-feedback'); ?></th>
                                <th><?php _e('Size', 'arcane-voice-feedback'); ?></th>
                                <th><?php _e('Actions', 'arcane-voice-feedback'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recordings as $recording): ?>
                                <tr data-filename="<?php echo esc_attr($recording['filename']); ?>">
                                    <td><?php echo esc_html($recording['date']); ?></td>
                                    <td>
                                        <audio controls preload="none">
                                            <source src="<?php echo esc_url($recording['url']); ?>" type="audio/webm">
                                            <?php _e('Your browser does not support the audio element.', 'arcane-voice-feedback'); ?>
                                        </audio>
                                    </td>
                                    <td><?php echo esc_html($recording['size']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($recording['url']); ?>" 
                                           download="<?php echo esc_attr($recording['filename']); ?>" 
                                           class="button button-secondary avf-download-btn">
                                            <?php _e('Download', 'arcane-voice-feedback'); ?>
                                        </a>
                                        <button class="button button-danger avf-delete-btn" 
                                                data-filename="<?php echo esc_attr($recording['filename']); ?>">
                                            <?php _e('Delete', 'arcane-voice-feedback'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="avf-bulk-actions">
                        <button class="button button-danger avf-delete-all-btn">
                            <?php _e('Delete All Recordings', 'arcane-voice-feedback'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function output_admin_css() {
        ?>
        <style>
        .avf-admin-wrap {
            max-width: 1200px;
        }

        .avf-admin-content {
            background: #fff;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .avf-no-recordings {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .avf-no-recordings p {
            font-size: 16px;
        }

        .avf-recordings-count {
            margin-bottom: 20px;
            padding: 10px;
            background: #f0f0f1;
            border-left: 4px solid #0073aa;
        }

        .avf-recordings-count p {
            margin: 0;
            font-weight: bold;
            color: #0073aa;
        }

        .wp-list-table audio {
            width: 100%;
            max-width: 300px;
        }

        .button-danger {
            background: #dc3232;
            border-color: #ba2525;
            color: #fff;
        }

        .button-danger:hover {
            background: #ba2525;
            border-color: #a02222;
            color: #fff;
        }

        .avf-download-btn {
            margin-right: 5px;
        }

        .avf-bulk-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .avf-delete-all-btn {
            font-weight: bold;
        }

        /* Settings page styles */
        .avf-pages-checklist {
            margin-top: 10px;
        }

        .form-table th {
            width: 200px;
        }

        /* Responsive */
        @media screen and (max-width: 782px) {
            .wp-list-table audio {
                max-width: 200px;
            }
            
            .wp-list-table td {
                display: block;
                width: 100%;
                text-align: left !important;
                padding: 10px;
            }
            
            .wp-list-table thead {
                display: none;
            }
            
            .wp-list-table tr {
                border-bottom: 2px solid #ddd;
                margin-bottom: 10px;
            }
            
            .wp-list-table td:before {
                content: attr(data-label);
                font-weight: bold;
                display: block;
                margin-bottom: 5px;
                color: #0073aa;
            }
        }
        </style>
        <?php
    }
    
    public function output_admin_js() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('avf_admin_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            var avfAdmin = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>'
            };
            
            // Delete single recording
            $('.avf-delete-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete this recording?')) {
                    return;
                }
                
                var button = $(this);
                var filename = button.data('filename');
                var row = button.closest('tr');
                
                button.prop('disabled', true).text('Deleting...');
                
                $.ajax({
                    url: avfAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'avf_delete_recording',
                        nonce: avfAdmin.nonce,
                        filename: filename
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(400, function() {
                                $(this).remove();
                                
                                // Check if table is empty
                                if ($('.wp-list-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            alert('Error: ' + response.data.message);
                            button.prop('disabled', false).text('Delete');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                        button.prop('disabled', false).text('Delete');
                    }
                });
            });
            
            // Delete all recordings
            $('.avf-delete-all-btn').on('click', function() {
                if (!confirm('Are you sure you want to delete ALL recordings? This action cannot be undone.')) {
                    return;
                }
                
                var button = $(this);
                var deleteButtons = $('.avf-delete-btn');
                var totalCount = deleteButtons.length;
                var deletedCount = 0;
                
                button.prop('disabled', true).text('Deleting all...');
                
                deleteButtons.each(function() {
                    var filename = $(this).data('filename');
                    var row = $(this).closest('tr');
                    
                    $.ajax({
                        url: avfAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'avf_delete_recording',
                            nonce: avfAdmin.nonce,
                            filename: filename
                        },
                        success: function(response) {
                            deletedCount++;
                            row.fadeOut(400, function() {
                                $(this).remove();
                            });
                            
                            if (deletedCount === totalCount) {
                                setTimeout(function() {
                                    location.reload();
                                }, 500);
                            }
                        },
                        error: function() {
                            deletedCount++;
                            if (deletedCount === totalCount) {
                                alert('Some recordings could not be deleted. Please refresh the page.');
                                button.prop('disabled', false).text('Delete All Recordings');
                            }
                        }
                    });
                });
            });
        });
        </script>
        <?php
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get all pages
        $pages = get_pages(array(
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));
        
        $current_pages = get_option('avf_global_pages', array());
        $max_length = get_option('avf_max_recording_length', 300);
        $display_mode = get_option('avf_global_display_mode', 'all');
        
        // Render the page inline
        ?>
        <div class="wrap avf-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('avf_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Display Mode', 'arcane-voice-feedback'); ?></label>
                        </th>
                        <td>
                            <select name="avf_global_display_mode" id="avf_global_display_mode">
                                <option value="all" <?php selected($display_mode, 'all'); ?>>
                                    <?php _e('All Pages', 'arcane-voice-feedback'); ?>
                                </option>
                                <option value="homepage" <?php selected($display_mode, 'homepage'); ?>>
                                    <?php _e('Homepage Only', 'arcane-voice-feedback'); ?>
                                </option>
                                <option value="specific" <?php selected($display_mode, 'specific'); ?>>
                                    <?php _e('Specific Pages', 'arcane-voice-feedback'); ?>
                                </option>
                                <option value="block" <?php selected($display_mode, 'block'); ?>>
                                    <?php _e('Block Settings Only', 'arcane-voice-feedback'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Choose where the voice feedback widget should appear globally. "Block Settings Only" will use only the settings from individual blocks.', 'arcane-voice-feedback'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr id="avf_pages_row" style="<?php echo $display_mode === 'specific' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label><?php _e('Select Pages', 'arcane-voice-feedback'); ?></label>
                        </th>
                        <td>
                            <div class="avf-pages-checklist">
                                <?php if (empty($pages)): ?>
                                    <p><?php _e('No pages found.', 'arcane-voice-feedback'); ?></p>
                                <?php else: ?>
                                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                        <?php foreach ($pages as $page): ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" 
                                                       name="avf_global_pages[]" 
                                                       value="<?php echo esc_attr($page->ID); ?>"
                                                       <?php checked(in_array($page->ID, $current_pages)); ?>>
                                                <?php echo esc_html($page->post_title); ?>
                                                <span style="color: #666; font-size: 12px;">(ID: <?php echo $page->ID; ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="description">
                                <?php _e('Select the pages where you want the voice feedback widget to appear.', 'arcane-voice-feedback'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="avf_max_recording_length"><?php _e('Maximum Recording Length', 'arcane-voice-feedback'); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   name="avf_max_recording_length" 
                                   id="avf_max_recording_length" 
                                   min="30" 
                                   max="600" 
                                   step="30"
                                   value="<?php echo esc_attr($max_length); ?>"
                                   class="avf-slider">
                            <span id="avf_length_display" class="avf-slider-value">
                                <?php echo esc_html($max_length); ?> <?php _e('seconds', 'arcane-voice-feedback'); ?>
                                (<?php echo esc_html(gmdate('i:s', $max_length)); ?>)
                            </span>
                            <p class="description">
                                <?php _e('Set the maximum length for voice recordings (30 seconds to 10 minutes).', 'arcane-voice-feedback'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'arcane-voice-feedback')); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Update slider display
            $('#avf_max_recording_length').on('input', function() {
                var seconds = $(this).val();
                var minutes = Math.floor(seconds / 60);
                var secs = seconds % 60;
                var timeString = (minutes < 10 ? '0' : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
                $('#avf_length_display').text(seconds + ' seconds (' + timeString + ')');
            });
            
            // Show/hide pages selector based on display mode
            $('#avf_global_display_mode').on('change', function() {
                if ($(this).val() === 'specific') {
                    $('#avf_pages_row').show();
                } else {
                    $('#avf_pages_row').hide();
                }
            });
        });
        </script>

        <style>
        .avf-slider {
            width: 300px;
            vertical-align: middle;
        }
        .avf-slider-value {
            display: inline-block;
            margin-left: 15px;
            font-weight: bold;
            color: #0073aa;
        }
        .avf-pages-checklist label {
            cursor: pointer;
            padding: 5px;
        }
        .avf-pages-checklist label:hover {
            background: #f0f0f1;
        }
        </style>
        <?php
    }
    
    public function delete_recording() {
        check_ajax_referer('avf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $filename = sanitize_file_name($_POST['filename']);
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/voice-feedback/' . $filename;
        
        if (file_exists($filepath) && unlink($filepath)) {
            wp_send_json_success(array('message' => 'Recording deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete recording'));
        }
    }
    
    public function get_recordings() {
        check_ajax_referer('avf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $upload_dir = wp_upload_dir();
        $feedback_dir = $upload_dir['basedir'] . '/voice-feedback';
        $feedback_url = $upload_dir['baseurl'] . '/voice-feedback';
        
        $recordings = array();
        if (file_exists($feedback_dir)) {
            $files = glob($feedback_dir . '/*.webm');
            foreach ($files as $file) {
                $filename = basename($file);
                $recordings[] = array(
                    'filename' => $filename,
                    'url' => $feedback_url . '/' . $filename,
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'size' => size_format(filesize($file))
                );
            }
            usort($recordings, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }
        
        wp_send_json_success(array('recordings' => $recordings));
    }
    
    public function should_display_widget($attributes) {
        // Check global settings first
        $global_display_mode = get_option('avf_global_display_mode', 'all');
        $global_pages = get_option('avf_global_pages', array());
        
        // If global mode is set to block, only use block settings
        if ($global_display_mode === 'block') {
            if ($attributes['displayOnPages'] === 'all') {
                return true;
            }
            
            if ($attributes['displayOnPages'] === 'specific' && !empty($attributes['specificPages'])) {
                $current_page_id = get_the_ID();
                return in_array($current_page_id, $attributes['specificPages']);
            }
            
            if ($attributes['displayOnPages'] === 'homepage') {
                return is_front_page() || is_home();
            }
            
            return true; // Default to showing if block mode
        }
        
        // Otherwise apply global settings
        if ($global_display_mode === 'specific' && !empty($global_pages)) {
            $current_page_id = get_the_ID();
            if (!in_array($current_page_id, $global_pages)) {
                return false;
            }
        } elseif ($global_display_mode === 'homepage') {
            if (!is_front_page() && !is_home()) {
                return false;
            }
        }
        
        // Then check block-specific settings if they override
        if ($attributes['displayOnPages'] === 'all') {
            return true;
        }
        
        if ($attributes['displayOnPages'] === 'specific' && !empty($attributes['specificPages'])) {
            $current_page_id = get_the_ID();
            return in_array($current_page_id, $attributes['specificPages']);
        }
        
        if ($attributes['displayOnPages'] === 'homepage') {
            return is_front_page() || is_home();
        }
        
        return true;
    }
    
    public function get_icon_svg($icon_type) {
        $icons = array(
            'microphone' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="22"></line></svg>',
            'mic-classic' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" x2="12" y1="19" y2="23"></line></svg>',
            'voice' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h3l3-9 6 18 3-9h3"></path></svg>',
            'radio' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"></circle><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path></svg>',
            'speaker' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>',
            'record' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="8"></circle></svg>',
            'none' => ''
        );
        
        return isset($icons[$icon_type]) ? $icons[$icon_type] : $icons['microphone'];
    }
    
    public function render_block($attributes) {
        // Check if widget should display on this page
        if (!$this->should_display_widget($attributes)) {
            return '';
        }
        
        $unique_id = 'avf-' . uniqid();
        
        $styles = sprintf(
            'width: %s; background-color: %s; border: %s solid %s; border-radius: %s; padding: %s; font-size: %s;',
            esc_attr($attributes['width']),
            esc_attr($attributes['fieldColor']),
            esc_attr($attributes['borderWidth']),
            esc_attr($attributes['borderColor']),
            esc_attr($attributes['borderRadius']),
            esc_attr($attributes['padding']),
            esc_attr($attributes['fontSize'])
        );
        
        if ($attributes['height'] !== 'auto') {
            $styles .= ' height: ' . esc_attr($attributes['height']) . ';';
        }
        
        $record_button_style = sprintf(
            'background-color: %s; color: %s; width: %s; height: %s;',
            esc_attr($attributes['buttonColor']),
            esc_attr($attributes['buttonTextColor']),
            esc_attr($attributes['recordButtonWidth']),
            esc_attr($attributes['recordButtonHeight'])
        );
        
        $review_button_style = sprintf(
            'background-color: %s; color: %s; width: %s; height: %s;',
            esc_attr($attributes['buttonColor']),
            esc_attr($attributes['buttonTextColor']),
            esc_attr($attributes['reviewButtonWidth']),
            esc_attr($attributes['reviewButtonHeight'])
        );
        
        $send_button_style = sprintf(
            'background-color: %s; color: %s; width: %s; height: %s;',
            esc_attr($attributes['buttonColor']),
            esc_attr($attributes['buttonTextColor']),
            esc_attr($attributes['sendButtonWidth']),
            esc_attr($attributes['sendButtonHeight'])
        );
        
        $rerecord_button_style = sprintf(
            'background-color: %s; color: %s; width: %s; height: %s;',
            esc_attr($attributes['buttonColor']),
            esc_attr($attributes['buttonTextColor']),
            esc_attr($attributes['rerecordButtonWidth']),
            esc_attr($attributes['rerecordButtonHeight'])
        );
        
        $stop_button_style = sprintf(
            'background-color: %s; color: %s; width: %s; height: %s;',
            esc_attr($attributes['stopButtonColor']),
            esc_attr($attributes['stopButtonTextColor']),
            esc_attr($attributes['stopButtonWidth']),
            esc_attr($attributes['stopButtonHeight'])
        );
        
        $floating_class = $attributes['floatingMode'] ? ' avf-floating avf-floating-' . esc_attr($attributes['floatingPosition']) : '';
        $collapsed_class = $attributes['floatingMode'] ? ' avf-collapsed' : '';
        $max_length = get_option('avf_max_recording_length', 300);
        
        ob_start();
        ?>
        <div class="arcane-voice-feedback<?php echo $floating_class . $collapsed_class; ?>" id="<?php echo esc_attr($unique_id); ?>" style="<?php echo $styles; ?>"
             data-record-text="<?php echo esc_attr($attributes['recordButtonText']); ?>"
             data-review-text="<?php echo esc_attr($attributes['reviewButtonText']); ?>"
             data-send-text="<?php echo esc_attr($attributes['sendButtonText']); ?>"
             data-rerecord-text="<?php echo esc_attr($attributes['rerecordButtonText']); ?>"
             data-record-image="<?php echo esc_attr($attributes['recordButtonImage']); ?>"
             data-review-image="<?php echo esc_attr($attributes['reviewButtonImage']); ?>"
             data-send-image="<?php echo esc_attr($attributes['sendButtonImage']); ?>"
             data-rerecord-image="<?php echo esc_attr($attributes['rerecordButtonImage']); ?>"
             data-show-text-under-images="<?php echo $attributes['showTextUnderImages'] ? '1' : '0'; ?>"
             data-record-button-style="<?php echo esc_attr($record_button_style); ?>"
             data-review-button-style="<?php echo esc_attr($review_button_style); ?>"
             data-send-button-style="<?php echo esc_attr($send_button_style); ?>"
             data-rerecord-button-style="<?php echo esc_attr($rerecord_button_style); ?>"
             data-stop-button-style="<?php echo esc_attr($stop_button_style); ?>"
             data-stop-button-text="<?php echo esc_attr($attributes['stopButtonText']); ?>"
             data-stop-button-image="<?php echo esc_attr($attributes['stopButtonImage']); ?>"
             data-floating-mode="<?php echo $attributes['floatingMode'] ? '1' : '0'; ?>"
             data-show-waveform="<?php echo $attributes['showWaveform'] ? '1' : '0'; ?>"
             data-waveform-color="<?php echo esc_attr($attributes['waveformColor']); ?>"
             data-icon-type="<?php echo esc_attr($attributes['recordButtonIcon']); ?>"
             data-show-icon-text="<?php echo $attributes['showIconText'] ? '1' : '0'; ?>"
             data-max-length="<?php echo esc_attr($max_length); ?>">
            
            <div class="avf-recording-interface">
                <?php if ($attributes['floatingMode']): ?>
                    <button class="avf-toggle-button avf-button-wrapper" style="<?php echo $record_button_style; ?>">
                        <?php if (!empty($attributes['recordButtonImage'])): ?>
                            <img src="<?php echo esc_url($attributes['recordButtonImage']); ?>" alt="<?php echo esc_attr($attributes['recordButtonText']); ?>" />
                            <?php if ($attributes['showTextUnderImages']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['recordButtonText']); ?></span>
                            <?php endif; ?>
                        <?php elseif ($attributes['recordButtonIcon'] !== 'none'): ?>
                            <span class="avf-icon"><?php echo $this->get_icon_svg($attributes['recordButtonIcon']); ?></span>
                            <?php if ($attributes['showIconText']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['recordButtonText']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo esc_html($attributes['recordButtonText']); ?>
                        <?php endif; ?>
                    </button>
                    
                    <div class="avf-expanded-content" style="display: none;">
                <?php endif; ?>
                
                <button class="avf-record-button avf-button-wrapper" style="<?php echo $record_button_style; ?><?php echo $attributes['floatingMode'] ? ' display: none;' : ''; ?>">
                    <?php if (!empty($attributes['recordButtonImage'])): ?>
                        <img src="<?php echo esc_url($attributes['recordButtonImage']); ?>" alt="<?php echo esc_attr($attributes['recordButtonText']); ?>" />
                        <?php if ($attributes['showTextUnderImages']): ?>
                            <span class="avf-button-text"><?php echo esc_html($attributes['recordButtonText']); ?></span>
                        <?php endif; ?>
                    <?php elseif ($attributes['recordButtonIcon'] !== 'none'): ?>
                        <span class="avf-icon"><?php echo $this->get_icon_svg($attributes['recordButtonIcon']); ?></span>
                        <?php if ($attributes['showIconText']): ?>
                            <span class="avf-button-text"><?php echo esc_html($attributes['recordButtonText']); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo esc_html($attributes['recordButtonText']); ?>
                    <?php endif; ?>
                </button>
                
                <div class="avf-recording-status" style="display: none;">
                    <div class="avf-recording-header">
                        <span class="avf-recording-indicator">● Recording...</span>
                        <span class="avf-recording-timer">0:00</span>
                    </div>
                    <?php if ($attributes['showWaveform']): ?>
                        <canvas class="avf-waveform" width="300" height="60"></canvas>
                    <?php endif; ?>
                    <button class="avf-stop-button avf-button-wrapper" style="<?php echo $stop_button_style; ?>">
                        <?php if (!empty($attributes['stopButtonImage'])): ?>
                            <img src="<?php echo esc_url($attributes['stopButtonImage']); ?>" alt="<?php echo esc_attr($attributes['stopButtonText']); ?>" />
                            <?php if ($attributes['showTextUnderImages']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['stopButtonText']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo esc_html($attributes['stopButtonText']); ?>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="avf-audio-player" style="display: none;">
                    <audio controls></audio>
                </div>
                
                <div class="avf-action-buttons" style="display: none;">
                    <button class="avf-review-button avf-button-wrapper" style="<?php echo $review_button_style; ?>">
                        <?php if (!empty($attributes['reviewButtonImage'])): ?>
                            <img src="<?php echo esc_url($attributes['reviewButtonImage']); ?>" alt="<?php echo esc_attr($attributes['reviewButtonText']); ?>" />
                            <?php if ($attributes['showTextUnderImages']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['reviewButtonText']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo esc_html($attributes['reviewButtonText']); ?>
                        <?php endif; ?>
                    </button>
                    
                    <button class="avf-send-button avf-button-wrapper" style="<?php echo $send_button_style; ?>">
                        <?php if (!empty($attributes['sendButtonImage'])): ?>
                            <img src="<?php echo esc_url($attributes['sendButtonImage']); ?>" alt="<?php echo esc_attr($attributes['sendButtonText']); ?>" />
                            <?php if ($attributes['showTextUnderImages']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['sendButtonText']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo esc_html($attributes['sendButtonText']); ?>
                        <?php endif; ?>
                    </button>
                    
                    <button class="avf-rerecord-button avf-button-wrapper" style="<?php echo $rerecord_button_style; ?>">
                        <?php if (!empty($attributes['rerecordButtonImage'])): ?>
                            <img src="<?php echo esc_url($attributes['rerecordButtonImage']); ?>" alt="<?php echo esc_attr($attributes['rerecordButtonText']); ?>" />
                            <?php if ($attributes['showTextUnderImages']): ?>
                                <span class="avf-button-text"><?php echo esc_html($attributes['rerecordButtonText']); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo esc_html($attributes['rerecordButtonText']); ?>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="avf-message" style="display: none;"></div>
                
                <?php if ($attributes['floatingMode']): ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function save_recording() {
        check_ajax_referer('avf_save_recording', 'nonce');
        
        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'No audio file received or upload error.'));
        }
        
        $upload_dir = wp_upload_dir();
        $avf_dir = $upload_dir['basedir'] . '/voice-feedback';
        
        if (!file_exists($avf_dir)) {
            wp_mkdir_p($avf_dir);
        }
        
        $filename = 'voice-feedback-' . time() . '-' . uniqid() . '.webm';
        $filepath = $avf_dir . '/' . $filename;
        
        if (move_uploaded_file($_FILES['audio']['tmp_name'], $filepath)) {
            // You can add code here to send email notifications, store in database, etc.
            wp_send_json_success(array(
                'message' => 'Voice feedback saved successfully!',
                'filename' => $filename
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save audio file.'));
        }
    }
}

// Initialize the plugin
new Arcane_Voice_Feedback();

// Create the JavaScript file for the block editor
function avf_create_editor_script() {
    $script_content = <<<'JAVASCRIPT'
(function(blocks, element, editor, components, i18n) {
    const el = element.createElement;
    const { registerBlockType } = blocks;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = editor;
    const { PanelBody, TextControl, ColorPicker, Button, ToggleControl } = components;
    const { __ } = i18n;
    registerBlockType('arcane/voice-feedback', {
        title: __('Arcane Voice Feedback', 'arcane-voice-feedback'),
        icon: 'microphone',
        category: 'common',
        supports: {
            html: false,
            multiple: true,
            reusable: true
        },
        attributes: {
            recordButtonText: { type: 'string', default: 'Record Voice Feedback' },
            reviewButtonText: { type: 'string', default: 'Review' },
            sendButtonText: { type: 'string', default: 'Send' },
            rerecordButtonText: { type: 'string', default: 'Re-record' },
            width: { type: 'string', default: '400px' },
            height: { type: 'string', default: 'auto' },
            borderColor: { type: 'string', default: '#cccccc' },
            borderWidth: { type: 'string', default: '2px' },
            fieldColor: { type: 'string', default: '#ffffff' },
            buttonColor: { type: 'string', default: '#0073aa' },
            buttonTextColor: { type: 'string', default: '#ffffff' },
            recordButtonImage: { type: 'string', default: '' },
            reviewButtonImage: { type: 'string', default: '' },
            sendButtonImage: { type: 'string', default: '' },
            rerecordButtonImage: { type: 'string', default: '' },
            borderRadius: { type: 'string', default: '5px' },
            padding: { type: 'string', default: '20px' },
            fontSize: { type: 'string', default: '16px' },
            recordButtonWidth: { type: 'string', default: 'auto' },
            recordButtonHeight: { type: 'string', default: '40px' },
            reviewButtonWidth: { type: 'string', default: 'auto' },
            reviewButtonHeight: { type: 'string', default: '40px' },
            sendButtonWidth: { type: 'string', default: 'auto' },
            sendButtonHeight: { type: 'string', default: '40px' },
            rerecordButtonWidth: { type: 'string', default: 'auto' },
            rerecordButtonHeight: { type: 'string', default: '40px' },
            showTextUnderImages: { type: 'boolean', default: true },
            stopButtonText: { type: 'string', default: 'Stop Recording' },
            stopButtonWidth: { type: 'string', default: 'auto' },
            stopButtonHeight: { type: 'string', default: '40px' },
            stopButtonColor: { type: 'string', default: '#dc3545' },
            stopButtonTextColor: { type: 'string', default: '#ffffff' },
            stopButtonImage: { type: 'string', default: '' },
            floatingMode: { type: 'boolean', default: false },
            floatingPosition: { type: 'string', default: 'bottom-right' },
            recordButtonIcon: { type: 'string', default: 'microphone' },
            showIconText: { type: 'boolean', default: true },
            showWaveform: { type: 'boolean', default: true },
            waveformColor: { type: 'string', default: '#0073aa' },
            displayOnPages: { type: 'string', default: 'all' },
            specificPages: { type: 'array', default: [] }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            
            const containerStyle = {
                width: attributes.width,
                height: attributes.height !== 'auto' ? attributes.height : 'auto',
                backgroundColor: attributes.fieldColor,
                border: attributes.borderWidth + ' solid ' + attributes.borderColor,
                borderRadius: attributes.borderRadius,
                padding: attributes.padding,
                fontSize: attributes.fontSize
            };
            
            const recordButtonStyle = {
                backgroundColor: attributes.buttonColor,
                color: attributes.buttonTextColor,
                border: 'none',
                padding: '10px 20px',
                cursor: 'pointer',
                borderRadius: '4px',
                width: attributes.recordButtonWidth,
                height: attributes.recordButtonHeight,
                margin: '5px',
                fontSize: 'inherit',
                display: 'inline-flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center'
            };
            
            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Button Text Settings', 'arcane-voice-feedback'), initialOpen: true },
                        el(TextControl, {
                            label: __('Record Button Text', 'arcane-voice-feedback'),
                            value: attributes.recordButtonText,
                            onChange: (value) => setAttributes({ recordButtonText: value })
                        }),
                        el(TextControl, {
                            label: __('Review Button Text', 'arcane-voice-feedback'),
                            value: attributes.reviewButtonText,
                            onChange: (value) => setAttributes({ reviewButtonText: value })
                        }),
                        el(TextControl, {
                            label: __('Send Button Text', 'arcane-voice-feedback'),
                            value: attributes.sendButtonText,
                            onChange: (value) => setAttributes({ sendButtonText: value })
                        }),
                        el(TextControl, {
                            label: __('Re-record Button Text', 'arcane-voice-feedback'),
                            value: attributes.rerecordButtonText,
                            onChange: (value) => setAttributes({ rerecordButtonText: value })
                        }),
                        el(TextControl, {
                            label: __('Stop Recording Button Text', 'arcane-voice-feedback'),
                            value: attributes.stopButtonText,
                            onChange: (value) => setAttributes({ stopButtonText: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Display & Positioning', 'arcane-voice-feedback'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Enable Floating Mode', 'arcane-voice-feedback'),
                            help: __('Widget appears as a floating button that expands when clicked', 'arcane-voice-feedback'),
                            checked: attributes.floatingMode,
                            onChange: (value) => setAttributes({ floatingMode: value })
                        }),
                        attributes.floatingMode && el('div', { style: { marginTop: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } },
                                __('Floating Position', 'arcane-voice-feedback')
                            ),
                            el('select', {
                                value: attributes.floatingPosition,
                                onChange: (e) => setAttributes({ floatingPosition: e.target.value }),
                                style: { width: '100%', padding: '8px' }
                            },
                                el('option', { value: 'top-left' }, __('Top Left', 'arcane-voice-feedback')),
                                el('option', { value: 'top-center' }, __('Top Center', 'arcane-voice-feedback')),
                                el('option', { value: 'top-right' }, __('Top Right', 'arcane-voice-feedback')),
                                el('option', { value: 'right-center' }, __('Right Center', 'arcane-voice-feedback')),
                                el('option', { value: 'bottom-right' }, __('Bottom Right', 'arcane-voice-feedback')),
                                el('option', { value: 'bottom-center' }, __('Bottom Center', 'arcane-voice-feedback')),
                                el('option', { value: 'bottom-left' }, __('Bottom Left', 'arcane-voice-feedback')),
                                el('option', { value: 'left-center' }, __('Left Center', 'arcane-voice-feedback'))
                            )
                        ),
                        el('div', { style: { marginTop: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } },
                                __('Display On Pages', 'arcane-voice-feedback')
                            ),
                            el('select', {
                                value: attributes.displayOnPages,
                                onChange: (e) => setAttributes({ displayOnPages: e.target.value }),
                                style: { width: '100%', padding: '8px' }
                            },
                                el('option', { value: 'all' }, __('All Pages', 'arcane-voice-feedback')),
                                el('option', { value: 'homepage' }, __('Homepage Only', 'arcane-voice-feedback')),
                                el('option', { value: 'specific' }, __('Specific Pages (configure in block)', 'arcane-voice-feedback'))
                            )
                        )
                    ),
                    
                    el(PanelBody, { title: __('Record Button Icon', 'arcane-voice-feedback'), initialOpen: false },
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } },
                                __('Icon Type', 'arcane-voice-feedback')
                            ),
                            el('select', {
                                value: attributes.recordButtonIcon,
                                onChange: (e) => setAttributes({ recordButtonIcon: e.target.value }),
                                style: { width: '100%', padding: '8px' }
                            },
                                el('option', { value: 'microphone' }, __('Microphone', 'arcane-voice-feedback')),
                                el('option', { value: 'mic-classic' }, __('Classic Mic', 'arcane-voice-feedback')),
                                el('option', { value: 'voice' }, __('Voice Wave', 'arcane-voice-feedback')),
                                el('option', { value: 'radio' }, __('Radio Signal', 'arcane-voice-feedback')),
                                el('option', { value: 'speaker' }, __('Speaker', 'arcane-voice-feedback')),
                                el('option', { value: 'record' }, __('Record Dot', 'arcane-voice-feedback')),
                                el('option', { value: 'none' }, __('No Icon (Text Only)', 'arcane-voice-feedback'))
                            )
                        ),
                        attributes.recordButtonIcon !== 'none' && el(ToggleControl, {
                            label: __('Show Text with Icon', 'arcane-voice-feedback'),
                            checked: attributes.showIconText,
                            onChange: (value) => setAttributes({ showIconText: value })
                        }),
                        el('p', { style: { fontSize: '12px', color: '#666', marginTop: '10px' } },
                            __('Note: Custom image will override icon selection', 'arcane-voice-feedback')
                        )
                    ),
                    
                    el(PanelBody, { title: __('Recording Waveform', 'arcane-voice-feedback'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Audio Waveform', 'arcane-voice-feedback'),
                            help: __('Display live waveform visualization during recording', 'arcane-voice-feedback'),
                            checked: attributes.showWaveform,
                            onChange: (value) => setAttributes({ showWaveform: value })
                        }),
                        attributes.showWaveform && el('div', { style: { marginTop: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } },
                                __('Waveform Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.waveformColor,
                                onChangeComplete: (value) => setAttributes({ waveformColor: value.hex })
                            })
                        )
                    ),
                    
                    el(PanelBody, { title: __('Size Settings', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Container Width (e.g., 400px, 100%)', 'arcane-voice-feedback'),
                            value: attributes.width,
                            onChange: (value) => setAttributes({ width: value })
                        }),
                        el(TextControl, {
                            label: __('Container Height (e.g., 300px, auto)', 'arcane-voice-feedback'),
                            value: attributes.height,
                            onChange: (value) => setAttributes({ height: value })
                        }),
                        el(TextControl, {
                            label: __('Padding', 'arcane-voice-feedback'),
                            value: attributes.padding,
                            onChange: (value) => setAttributes({ padding: value })
                        }),
                        el(TextControl, {
                            label: __('Border Width', 'arcane-voice-feedback'),
                            value: attributes.borderWidth,
                            onChange: (value) => setAttributes({ borderWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Border Radius', 'arcane-voice-feedback'),
                            value: attributes.borderRadius,
                            onChange: (value) => setAttributes({ borderRadius: value })
                        }),
                        el(TextControl, {
                            label: __('Font Size', 'arcane-voice-feedback'),
                            value: attributes.fontSize,
                            onChange: (value) => setAttributes({ fontSize: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Record Button Size', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Width (e.g., 200px, auto)', 'arcane-voice-feedback'),
                            value: attributes.recordButtonWidth,
                            onChange: (value) => setAttributes({ recordButtonWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Height (e.g., 40px)', 'arcane-voice-feedback'),
                            value: attributes.recordButtonHeight,
                            onChange: (value) => setAttributes({ recordButtonHeight: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Review Button Size', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Width (e.g., 100px, auto)', 'arcane-voice-feedback'),
                            value: attributes.reviewButtonWidth,
                            onChange: (value) => setAttributes({ reviewButtonWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Height (e.g., 40px)', 'arcane-voice-feedback'),
                            value: attributes.reviewButtonHeight,
                            onChange: (value) => setAttributes({ reviewButtonHeight: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Send Button Size', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Width (e.g., 100px, auto)', 'arcane-voice-feedback'),
                            value: attributes.sendButtonWidth,
                            onChange: (value) => setAttributes({ sendButtonWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Height (e.g., 40px)', 'arcane-voice-feedback'),
                            value: attributes.sendButtonHeight,
                            onChange: (value) => setAttributes({ sendButtonHeight: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Re-record Button Size', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Width (e.g., 100px, auto)', 'arcane-voice-feedback'),
                            value: attributes.rerecordButtonWidth,
                            onChange: (value) => setAttributes({ rerecordButtonWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Height (e.g., 40px)', 'arcane-voice-feedback'),
                            value: attributes.rerecordButtonHeight,
                            onChange: (value) => setAttributes({ rerecordButtonHeight: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Stop Recording Button Size', 'arcane-voice-feedback'), initialOpen: false },
                        el(TextControl, {
                            label: __('Width (e.g., 150px, auto)', 'arcane-voice-feedback'),
                            value: attributes.stopButtonWidth,
                            onChange: (value) => setAttributes({ stopButtonWidth: value })
                        }),
                        el(TextControl, {
                            label: __('Height (e.g., 40px)', 'arcane-voice-feedback'),
                            value: attributes.stopButtonHeight,
                            onChange: (value) => setAttributes({ stopButtonHeight: value })
                        })
                    ),
                    
                    el(PanelBody, { title: __('Color Settings', 'arcane-voice-feedback'), initialOpen: false },
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Border Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.borderColor,
                                onChangeComplete: (value) => setAttributes({ borderColor: value.hex })
                            })
                        ),
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Background Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.fieldColor,
                                onChangeComplete: (value) => setAttributes({ fieldColor: value.hex })
                            })
                        ),
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Button Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.buttonColor,
                                onChangeComplete: (value) => setAttributes({ buttonColor: value.hex })
                            })
                        ),
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Button Text Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.buttonTextColor,
                                onChangeComplete: (value) => setAttributes({ buttonTextColor: value.hex })
                            })
                        ),
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Stop Button Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.stopButtonColor,
                                onChangeComplete: (value) => setAttributes({ stopButtonColor: value.hex })
                            })
                        ),
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Stop Button Text Color', 'arcane-voice-feedback')
                            ),
                            el(ColorPicker, {
                                color: attributes.stopButtonTextColor,
                                onChangeComplete: (value) => setAttributes({ stopButtonTextColor: value.hex })
                            })
                        )
                    ),
                    
                    el(PanelBody, { title: __('Button Images (Optional)', 'arcane-voice-feedback'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Text Under Button Images', 'arcane-voice-feedback'),
                            checked: attributes.showTextUnderImages,
                            onChange: (value) => setAttributes({ showTextUnderImages: value })
                        }),
                        el('div', { style: { marginBottom: '15px', marginTop: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Record Button Image', 'arcane-voice-feedback')
                            ),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: (media) => setAttributes({ recordButtonImage: media.url }),
                                    allowedTypes: ['image'],
                                    value: attributes.recordButtonImage,
                                    render: ({ open }) => el(Button, { 
                                        onClick: open,
                                        variant: 'secondary'
                                    }, attributes.recordButtonImage ? __('Change Image', 'arcane-voice-feedback') : __('Select Image', 'arcane-voice-feedback'))
                                })
                            ),
                            attributes.recordButtonImage && el(Button, {
                                onClick: () => setAttributes({ recordButtonImage: '' }),
                                variant: 'link',
                                isDestructive: true,
                                style: { marginLeft: '10px' }
                            }, __('Remove', 'arcane-voice-feedback'))
                        ),
                        
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Review Button Image', 'arcane-voice-feedback')
                            ),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: (media) => setAttributes({ reviewButtonImage: media.url }),
                                    allowedTypes: ['image'],
                                    value: attributes.reviewButtonImage,
                                    render: ({ open }) => el(Button, { 
                                        onClick: open,
                                        variant: 'secondary'
                                    }, attributes.reviewButtonImage ? __('Change Image', 'arcane-voice-feedback') : __('Select Image', 'arcane-voice-feedback'))
                                })
                            ),
                            attributes.reviewButtonImage && el(Button, {
                                onClick: () => setAttributes({ reviewButtonImage: '' }),
                                variant: 'link',
                                isDestructive: true,
                                style: { marginLeft: '10px' }
                            }, __('Remove', 'arcane-voice-feedback'))
                        ),
                        
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Send Button Image', 'arcane-voice-feedback')
                            ),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: (media) => setAttributes({ sendButtonImage: media.url }),
                                    allowedTypes: ['image'],
                                    value: attributes.sendButtonImage,
                                    render: ({ open }) => el(Button, { 
                                        onClick: open,
                                        variant: 'secondary'
                                    }, attributes.sendButtonImage ? __('Change Image', 'arcane-voice-feedback') : __('Select Image', 'arcane-voice-feedback'))
                                })
                            ),
                            attributes.sendButtonImage && el(Button, {
                                onClick: () => setAttributes({ sendButtonImage: '' }),
                                variant: 'link',
                                isDestructive: true,
                                style: { marginLeft: '10px' }
                            }, __('Remove', 'arcane-voice-feedback'))
                        ),
                        
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Re-record Button Image', 'arcane-voice-feedback')
                            ),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: (media) => setAttributes({ rerecordButtonImage: media.url }),
                                    allowedTypes: ['image'],
                                    value: attributes.rerecordButtonImage,
                                    render: ({ open }) => el(Button, { 
                                        onClick: open,
                                        variant: 'secondary'
                                    }, attributes.rerecordButtonImage ? __('Change Image', 'arcane-voice-feedback') : __('Select Image', 'arcane-voice-feedback'))
                                })
                            ),
                            attributes.rerecordButtonImage && el(Button, {
                                onClick: () => setAttributes({ rerecordButtonImage: '' }),
                                variant: 'link',
                                isDestructive: true,
                                style: { marginLeft: '10px' }
                            }, __('Remove', 'arcane-voice-feedback'))
                        ),
                        
                        el('div', { style: { marginBottom: '15px' } },
                            el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 'bold' } }, 
                                __('Stop Recording Button Image', 'arcane-voice-feedback')
                            ),
                            el(MediaUploadCheck, {},
                                el(MediaUpload, {
                                    onSelect: (media) => setAttributes({ stopButtonImage: media.url }),
                                    allowedTypes: ['image'],
                                    value: attributes.stopButtonImage,
                                    render: ({ open }) => el(Button, { 
                                        onClick: open,
                                        variant: 'secondary'
                                    }, attributes.stopButtonImage ? __('Change Image', 'arcane-voice-feedback') : __('Select Image', 'arcane-voice-feedback'))
                                })
                            ),
                            attributes.stopButtonImage && el(Button, {
                                onClick: () => setAttributes({ stopButtonImage: '' }),
                                variant: 'link',
                                isDestructive: true,
                                style: { marginLeft: '10px' }
                            }, __('Remove', 'arcane-voice-feedback'))
                        )
                    )
                ),
                
                el('div', { className: 'arcane-voice-feedback-preview', style: containerStyle },
                    el('div', { style: { textAlign: 'center' } },
                        el('button', { style: recordButtonStyle, disabled: true },
                            attributes.recordButtonImage ? 
                                [
                                    el('img', { 
                                        key: 'img',
                                        src: attributes.recordButtonImage, 
                                        alt: attributes.recordButtonText, 
                                        style: { maxHeight: attributes.recordButtonHeight, maxWidth: '100%', objectFit: 'contain' } 
                                    }),
                                    attributes.showTextUnderImages ? el('span', { 
                                        key: 'text',
                                        style: { marginTop: '5px', fontSize: '0.9em' } 
                                    }, attributes.recordButtonText) : null
                                ].filter(Boolean)
                                : attributes.recordButtonText
                        ),
                        el('div', { style: { marginTop: '10px', fontSize: '14px', color: '#666' } },
                            __('Preview: Voice feedback recorder will appear here on the frontend', 'arcane-voice-feedback')
                        )
                    )
                )
            );
        },
        
        save: function() {
            return null; // Rendered via PHP
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
JAVASCRIPT;
    
    $js_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-editor.js';
    // Always regenerate to ensure latest version
    file_put_contents($js_file, $script_content);
}

// Create files on plugin load
add_action('plugins_loaded', 'avf_create_editor_script', 5);
add_action('plugins_loaded', 'avf_create_frontend_script', 5);
add_action('plugins_loaded', 'avf_create_style', 5);

// Create the frontend JavaScript
function avf_create_frontend_script() {
    $script_content = <<<'JAVASCRIPT'
(function() {
    let mediaRecorder;
    let audioChunks = [];
    let recordingTimer;
    let seconds = 0;
    let currentAudioBlob;
    let audioContext;
    let analyser;
    let dataArray;
    let animationId;
    let mediaStream;
    
    document.addEventListener('DOMContentLoaded', function() {
        const containers = document.querySelectorAll('.arcane-voice-feedback');
        
        containers.forEach(function(container) {
            const recordButton = container.querySelector('.avf-record-button');
            const toggleButton = container.querySelector('.avf-toggle-button');
            const stopButton = container.querySelector('.avf-stop-button');
            const reviewButton = container.querySelector('.avf-review-button');
            const sendButton = container.querySelector('.avf-send-button');
            const rerecordButton = container.querySelector('.avf-rerecord-button');
            const recordingStatus = container.querySelector('.avf-recording-status');
            const audioPlayer = container.querySelector('.avf-audio-player');
            const actionButtons = container.querySelector('.avf-action-buttons');
            const messageDiv = container.querySelector('.avf-message');
            const timerDisplay = container.querySelector('.avf-recording-timer');
            const waveformCanvas = container.querySelector('.avf-waveform');
            const expandedContent = container.querySelector('.avf-expanded-content');
            
            const isFloating = container.dataset.floatingMode === '1';
            const showWaveform = container.dataset.showWaveform === '1';
            
            // Handle floating mode toggle
            if (isFloating && toggleButton) {
                toggleButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (container.classList.contains('avf-collapsed')) {
                        container.classList.remove('avf-collapsed');
                        expandedContent.style.display = 'block';
                    } else {
                        container.classList.add('avf-collapsed');
                        expandedContent.style.display = 'none';
                    }
                });
            }
            
            if (recordButton) {
                recordButton.addEventListener('click', function() {
                    startRecording(container);
                });
            }
            
            if (stopButton) {
                stopButton.addEventListener('click', function() {
                    stopRecording(container);
                });
            }
            
            if (reviewButton) {
                reviewButton.addEventListener('click', function() {
                    showAudioPlayer(container);
                });
            }
            
            if (sendButton) {
                sendButton.addEventListener('click', function() {
                    sendRecording(container);
                });
            }
            
            if (rerecordButton) {
                rerecordButton.addEventListener('click', function() {
                    resetRecorder(container);
                });
            }
        });
    });
    
    function drawWaveform(canvas, analyser, dataArray, color) {
        if (!canvas || !analyser) return;
        
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        
        analyser.getByteTimeDomainData(dataArray);
        
        ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.fillRect(0, 0, width, height);
        
        ctx.lineWidth = 2;
        ctx.strokeStyle = color;
        ctx.beginPath();
        
        const sliceWidth = width / dataArray.length;
        let x = 0;
        
        for (let i = 0; i < dataArray.length; i++) {
            const v = dataArray[i] / 128.0;
            const y = (v * height) / 2;
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
            
            x += sliceWidth;
        }
        
        ctx.lineTo(width, height / 2);
        ctx.stroke();
        
        animationId = requestAnimationFrame(() => drawWaveform(canvas, analyser, dataArray, color));
    }
    
    function startRecording(container) {
        const recordButton = container.querySelector('.avf-record-button');
        const recordingStatus = container.querySelector('.avf-recording-status');
        const timerDisplay = container.querySelector('.avf-recording-timer');
        const waveformCanvas = container.querySelector('.avf-waveform');
        const showWaveform = container.dataset.showWaveform === '1';
        const waveformColor = container.dataset.waveformColor || '#0073aa';
        const maxLength = parseInt(container.dataset.maxLength) || 300;
        
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(stream) {
                mediaStream = stream;
                audioChunks = [];
                seconds = 0;
                timerDisplay.textContent = '0:00';
                
                mediaRecorder = new MediaRecorder(stream);
                
                mediaRecorder.addEventListener('dataavailable', function(event) {
                    audioChunks.push(event.data);
                });
                
                mediaRecorder.addEventListener('stop', function() {
                    currentAudioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioUrl = URL.createObjectURL(currentAudioBlob);
                    const audio = container.querySelector('audio');
                    audio.src = audioUrl;
                    
                    // Stop all tracks
                    if (mediaStream) {
                        mediaStream.getTracks().forEach(track => track.stop());
                    }
                    
                    // Stop waveform animation
                    if (animationId) {
                        cancelAnimationFrame(animationId);
                    }
                    
                    // Close audio context
                    if (audioContext) {
                        audioContext.close();
                    }
                    
                    // Show action buttons
                    showActionButtons(container);
                });
                
                mediaRecorder.start();
                recordButton.style.display = 'none';
                recordingStatus.style.display = 'block';
                
                // Setup waveform visualization
                if (showWaveform && waveformCanvas) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    analyser = audioContext.createAnalyser();
                    const source = audioContext.createMediaStreamSource(stream);
                    source.connect(analyser);
                    analyser.fftSize = 2048;
                    const bufferLength = analyser.frequencyBinCount;
                    dataArray = new Uint8Array(bufferLength);
                    
                    drawWaveform(waveformCanvas, analyser, dataArray, waveformColor);
                }
                
                // Start timer
                recordingTimer = setInterval(function() {
                    seconds++;
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    timerDisplay.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
                    
                    // Auto-stop at max length
                    if (seconds >= maxLength) {
                        stopRecording(container);
                        showMessage(container, 'Maximum recording length reached (' + maxLength + ' seconds).', 'info');
                    }
                }, 1000);
            })
            .catch(function(error) {
                showMessage(container, 'Microphone access denied or not available.', 'error');
                console.error('Error accessing microphone:', error);
            });
    }
    
    function stopRecording(container) {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            clearInterval(recordingTimer);
            
            const recordingStatus = container.querySelector('.avf-recording-status');
            recordingStatus.style.display = 'none';
        }
    }
    
    function showActionButtons(container) {
        const actionButtons = container.querySelector('.avf-action-buttons');
        actionButtons.style.display = 'flex';
        actionButtons.style.gap = '10px';
        actionButtons.style.marginTop = '15px';
        actionButtons.style.justifyContent = 'center';
        actionButtons.style.flexWrap = 'wrap';
    }
    
    function showAudioPlayer(container) {
        const audioPlayer = container.querySelector('.avf-audio-player');
        audioPlayer.style.display = 'block';
        audioPlayer.style.marginTop = '15px';
    }
    
    function resetRecorder(container) {
        const recordButton = container.querySelector('.avf-record-button');
        const audioPlayer = container.querySelector('.avf-audio-player');
        const actionButtons = container.querySelector('.avf-action-buttons');
        const messageDiv = container.querySelector('.avf-message');
        const isFloating = container.dataset.floatingMode === '1';
        
        if (isFloating) {
            container.classList.add('avf-collapsed');
            const expandedContent = container.querySelector('.avf-expanded-content');
            if (expandedContent) {
                expandedContent.style.display = 'none';
            }
        } else {
            recordButton.style.display = 'block';
        }
        
        audioPlayer.style.display = 'none';
        actionButtons.style.display = 'none';
        messageDiv.style.display = 'none';
        
        currentAudioBlob = null;
        audioChunks = [];
        seconds = 0;
    }
    
    function sendRecording(container) {
        if (!currentAudioBlob) {
            showMessage(container, 'No recording to send.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'avf_save_recording');
        formData.append('nonce', avfData.nonce);
        formData.append('audio', currentAudioBlob, 'recording.webm');
        
        showMessage(container, 'Sending...', 'info');
        
        fetch(avfData.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(container, data.data.message, 'success');
                setTimeout(function() {
                    resetRecorder(container);
                }, 2000);
            } else {
                showMessage(container, data.data.message || 'Failed to send recording.', 'error');
            }
        })
        .catch(error => {
            showMessage(container, 'Error sending recording.', 'error');
            console.error('Error:', error);
        });
    }
    
    function showMessage(container, message, type) {
        const messageDiv = container.querySelector('.avf-message');
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        messageDiv.style.marginTop = '15px';
        messageDiv.style.padding = '10px';
        messageDiv.style.borderRadius = '4px';
        messageDiv.style.textAlign = 'center';
        
        if (type === 'success') {
            messageDiv.style.backgroundColor = '#d4edda';
            messageDiv.style.color = '#155724';
            messageDiv.style.border = '1px solid #c3e6cb';
        } else if (type === 'error') {
            messageDiv.style.backgroundColor = '#f8d7da';
            messageDiv.style.color = '#721c24';
            messageDiv.style.border = '1px solid #f5c6cb';
        } else {
            messageDiv.style.backgroundColor = '#d1ecf1';
            messageDiv.style.color = '#0c5460';
            messageDiv.style.border = '1px solid #bee5eb';
        }
    }
})();
JAVASCRIPT;
    
    $js_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-frontend.js';
    // Always regenerate to ensure latest version
    file_put_contents($js_file, $script_content);
}

// Create the CSS file
function avf_create_style() {
    $css_content = <<<'CSS'
.arcane-voice-feedback {
    box-sizing: border-box;
    margin: 20px auto;
}

.arcane-voice-feedback * {
    box-sizing: border-box;
}

/* Floating Mode Styles */
.avf-floating {
    position: fixed;
    z-index: 9999;
    margin: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.avf-floating.avf-collapsed {
    width: auto !important;
    height: auto !important;
    padding: 0 !important;
}

.avf-floating.avf-collapsed .avf-recording-interface {
    padding: 0;
}

.avf-floating-top-left {
    top: 20px;
    left: 20px;
}

.avf-floating-top-center {
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
}

.avf-floating-top-right {
    top: 20px;
    right: 20px;
}

.avf-floating-right-center {
    top: 50%;
    right: 20px;
    transform: translateY(-50%);
}

.avf-floating-bottom-right {
    bottom: 20px;
    right: 20px;
}

.avf-floating-bottom-center {
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
}

.avf-floating-bottom-left {
    bottom: 20px;
    left: 20px;
}

.avf-floating-left-center {
    top: 50%;
    left: 20px;
    transform: translateY(-50%);
}

.avf-toggle-button {
    cursor: pointer;
}

.avf-expanded-content {
    margin-top: 15px;
}

.avf-recording-interface {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.avf-button-wrapper {
    display: inline-flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px;
}

.avf-button-wrapper img {
    max-width: 100%;
    max-height: calc(100% - 25px);
    object-fit: contain;
}

.avf-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.avf-icon svg {
    width: 24px;
    height: 24px;
}

.avf-button-text {
    font-size: 0.85em;
    text-align: center;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.avf-record-button,
.avf-review-button,
.avf-send-button,
.avf-rerecord-button,
.avf-stop-button,
.avf-toggle-button {
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.2s;
    padding: 10px;
}

.avf-record-button:hover,
.avf-review-button:hover,
.avf-send-button:hover,
.avf-rerecord-button:hover,
.avf-stop-button:hover,
.avf-toggle-button:hover {
    opacity: 0.9;
}

.avf-recording-status {
    width: 100%;
    margin-top: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.avf-recording-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    width: 100%;
    max-width: 300px;
}

.avf-recording-indicator {
    color: #dc3545;
    font-weight: bold;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.avf-waveform {
    display: block;
    width: 100%;
    max-width: 300px;
    height: 60px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 10px auto;
    background: #f9f9f9;
}

.avf-stop-button {
    width: auto;
    margin: 15px auto 0 auto;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.avf-stop-button.avf-button-wrapper {
    flex-direction: column;
    gap: 5px;
}

.avf-audio-player {
    width: 100%;
}

.avf-audio-player audio {
    width: 100%;
    margin-top: 10px;
}

.avf-action-buttons {
    width: 100%;
}

.avf-message {
    width: 100%;
    font-weight: 500;
}

/* Responsive design */
@media (max-width: 600px) {
    .arcane-voice-feedback {
        width: 100% !important;
        max-width: 100%;
    }
    
    .avf-action-buttons {
        flex-direction: column;
    }
    
    .avf-action-buttons button {
        width: 100%;
    }
}
CSS;
    
    $css_file = plugin_dir_path(__FILE__) . 'arcane-voice-feedback-style.css';
    // Always regenerate to ensure latest version
    file_put_contents($css_file, $css_content);
}
