<?php
/**
 * Plugin Name: Dynamics Sync Lite
 * Description: WordPress plugin for Microsoft Dynamics 365 integration - allows users to view and update their contact information
 * Version: 1.0.0
 * Author: Nilesh Singh
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DYNAMICS_SYNC_LITE_VERSION', '1.0.0');
define('DYNAMICS_SYNC_LITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DYNAMICS_SYNC_LITE_PLUGIN_URL', plugin_dir_url(__FILE__));

class DynamicsSyncLite {
    
    private $dynamics_api;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_update_dynamics_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_nopriv_update_dynamics_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_test_dynamics_connection', array($this, 'ajax_test_connection'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_shortcode('dynamics_contact_form', array($this, 'render_contact_form'));
        
        // Debug: Log when AJAX actions are registered
        error_log('Dynamics Sync Lite: AJAX actions registered');
        
        // Initialize Dynamics API handler
        $this->dynamics_api = new DynamicsAPI();
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('dynamics-sync-lite', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('dynamics-sync-lite-js', DYNAMICS_SYNC_LITE_PLUGIN_URL . 'assets/dynamics-sync-lite.js', array('jquery'), DYNAMICS_SYNC_LITE_VERSION, true);
        wp_enqueue_style('dynamics-sync-lite-css', DYNAMICS_SYNC_LITE_PLUGIN_URL . 'assets/dynamics-sync-lite.css', array(), DYNAMICS_SYNC_LITE_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('dynamics-sync-lite-js', 'dynamics_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dynamics_sync_nonce'),
            'messages' => array(
                'loading' => __('Loading...', 'dynamics-sync-lite'),
                'updating' => __('Updating...', 'dynamics-sync-lite'),
                'update_button' => __('Update Contact Information', 'dynamics-sync-lite'),
                'success' => __('Contact information updated successfully!', 'dynamics-sync-lite'),
                'error' => __('Error updating contact information. Please try again.', 'dynamics-sync-lite'),
                'unknown_error' => __('An unknown error occurred. Please try again.', 'dynamics-sync-lite'),
                'connection_error' => __('Connection error. Please check if Azure credentials are configured in Admin Settings.', 'dynamics-sync-lite')
            )
        ));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Dynamics Sync Lite Settings',
            'Dynamics Sync Lite',
            'manage_options',
            'dynamics-sync-lite',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        $errors = array();
        $success_message = '';
        
        if (isset($_POST['submit'])) {
            $validation_result = $this->validate_and_save_settings();
            $errors = $validation_result['errors'];
            $success_message = $validation_result['success_message'];
        }
        
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Dynamics Sync Lite Settings', 'dynamics-sync-lite'); ?></h1>
            
            <?php if (!empty($errors)): ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('Please correct the following errors:', 'dynamics-sync-lite'); ?></strong></p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" id="dynamics-settings-form">
                <?php wp_nonce_field('dynamics_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Client ID', 'dynamics-sync-lite'); ?>
                            <span class="required">*</span>
                        </th>
                        <td>
                            <input type="text" name="dynamics_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text <?php echo in_array('client_id', array_keys($errors)) ? 'error' : ''; ?>" required />
                            <p class="description"><?php _e('Microsoft Azure App Client ID (UUID format)', 'dynamics-sync-lite'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Client Secret', 'dynamics-sync-lite'); ?>
                            <span class="required">*</span>
                        </th>
                        <td>
                            <input type="password" name="dynamics_client_secret" value="<?php echo esc_attr($client_secret); ?>" class="regular-text <?php echo in_array('client_secret', array_keys($errors)) ? 'error' : ''; ?>" required />
                            <p class="description"><?php _e('Microsoft Azure App Client Secret (minimum 32 characters)', 'dynamics-sync-lite'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Tenant ID', 'dynamics-sync-lite'); ?>
                            <span class="required">*</span>
                        </th>
                        <td>
                            <input type="text" name="dynamics_tenant_id" value="<?php echo esc_attr($tenant_id); ?>" class="regular-text <?php echo in_array('tenant_id', array_keys($errors)) ? 'error' : ''; ?>" required />
                            <p class="description"><?php _e('Microsoft Azure Tenant ID (UUID format)', 'dynamics-sync-lite'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Dynamics Resource URL', 'dynamics-sync-lite'); ?>
                            <span class="required">*</span>
                        </th>
                        <td>
                            <input type="url" name="dynamics_resource_url" value="<?php echo esc_attr($resource_url); ?>" class="regular-text <?php echo in_array('resource_url', array_keys($errors)) ? 'error' : ''; ?>" required />
                            <p class="description"><?php _e('Your Dynamics 365 instance URL (e.g., https://yourorg.crm.dynamics.com)', 'dynamics-sync-lite'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="dynamics-test-connection" style="margin-top: 20px;">
                    <h3><?php _e('Test Connection', 'dynamics-sync-lite'); ?></h3>
                    <p class="description"><?php _e('Test your connection to Dynamics 365 after saving settings.', 'dynamics-sync-lite'); ?></p>
                    <button type="button" id="test-connection-btn" class="button button-secondary" <?php echo empty($client_id) ? 'disabled' : ''; ?>>
                        <?php _e('Test Connection', 'dynamics-sync-lite'); ?>
                    </button>
                    <div id="test-connection-result"></div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Real-time validation
            $('#dynamics-settings-form input[required]').on('blur', function() {
                var $field = $(this);
                var fieldName = $field.attr('name');
                var fieldValue = $field.val().trim();
                
                $field.removeClass('error valid');
                
                if (fieldValue === '') {
                    $field.addClass('error');
                    return;
                }
                
                // Validate specific fields
                if (fieldName === 'dynamics_client_id' || fieldName === 'dynamics_tenant_id') {
                    var uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
                    if (!uuidPattern.test(fieldValue)) {
                        $field.addClass('error');
                        return;
                    }
                }
                
                if (fieldName === 'dynamics_client_secret' && fieldValue.length < 32) {
                    $field.addClass('error');
                    return;
                }
                
                if (fieldName === 'dynamics_resource_url') {
                    try {
                        var url = new URL(fieldValue);
                        if (url.protocol !== 'https:') {
                            $field.addClass('error');
                            return;
                        }
                    } catch (e) {
                        $field.addClass('error');
                        return;
                    }
                }
                
                $field.addClass('valid');
            });
            
            // Test connection functionality
            $('#test-connection-btn').on('click', function() {
                var $btn = $(this);
                var $result = $('#test-connection-result');
                
                $btn.prop('disabled', true).text('<?php _e('Testing...', 'dynamics-sync-lite'); ?>');
                $result.html('<div class="spinner is-active" style="float: none;"></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_dynamics_connection',
                        nonce: '<?php echo wp_create_nonce('test_dynamics_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p><?php _e('Connection test failed. Please check your settings.', 'dynamics-sync-lite'); ?></p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Test Connection', 'dynamics-sync-lite'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    private function validate_and_save_settings() {
        $errors = array();
        $success_message = '';
        
        // Security checks
        if (!wp_verify_nonce($_POST['_wpnonce'], 'dynamics_settings_nonce')) {
            $errors['security'] = __('Security check failed. Please try again.', 'dynamics-sync-lite');
            return array('errors' => $errors, 'success_message' => $success_message);
        }
        
        if (!current_user_can('manage_options')) {
            $errors['permission'] = __('Insufficient permissions.', 'dynamics-sync-lite');
            return array('errors' => $errors, 'success_message' => $success_message);
        }
        
        // Validate and sanitize inputs
        $client_id = sanitize_text_field($_POST['dynamics_client_id']);
        $client_secret = sanitize_text_field($_POST['dynamics_client_secret']);
        $tenant_id = sanitize_text_field($_POST['dynamics_tenant_id']);
        $resource_url = esc_url_raw($_POST['dynamics_resource_url']);
        
        // Field validation
        if (empty($client_id)) {
            $errors['client_id'] = __('Client ID is required.', 'dynamics-sync-lite');
        } elseif (!$this->is_valid_uuid($client_id)) {
            $errors['client_id'] = __('Client ID must be a valid UUID format.', 'dynamics-sync-lite');
        }
        
        if (empty($client_secret)) {
            $errors['client_secret'] = __('Client Secret is required.', 'dynamics-sync-lite');
        } elseif (strlen($client_secret) < 32) {
            $errors['client_secret'] = __('Client Secret must be at least 32 characters long.', 'dynamics-sync-lite');
        }
        
        if (empty($tenant_id)) {
            $errors['tenant_id'] = __('Tenant ID is required.', 'dynamics-sync-lite');
        } elseif (!$this->is_valid_uuid($tenant_id)) {
            $errors['tenant_id'] = __('Tenant ID must be a valid UUID format.', 'dynamics-sync-lite');
        }
        
        if (empty($resource_url)) {
            $errors['resource_url'] = __('Dynamics Resource URL is required.', 'dynamics-sync-lite');
        } elseif (!filter_var($resource_url, FILTER_VALIDATE_URL)) {
            $errors['resource_url'] = __('Please enter a valid URL.', 'dynamics-sync-lite');
        } elseif (parse_url($resource_url, PHP_URL_SCHEME) !== 'https') {
            $errors['resource_url'] = __('URL must use HTTPS protocol for security.', 'dynamics-sync-lite');
        } elseif (!preg_match('/\.crm\d*\.dynamics\.com$/i', parse_url($resource_url, PHP_URL_HOST))) {
            $errors['resource_url'] = __('URL must be a valid Dynamics 365 instance (e.g., https://yourorg.crm.dynamics.com)', 'dynamics-sync-lite');
        }
        
        // If no errors, save the settings
        if (empty($errors)) {
            update_option('dynamics_client_id', $client_id);
            update_option('dynamics_client_secret', $client_secret);
            update_option('dynamics_tenant_id', $tenant_id);
            update_option('dynamics_resource_url', $resource_url);
            
            $success_message = __('Settings saved successfully! You can now test your connection.', 'dynamics-sync-lite');
        }
        
        return array('errors' => $errors, 'success_message' => $success_message);
    }
    
    private function is_valid_uuid($uuid) {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }
    
    public function render_contact_form($atts) {
        if (!is_user_logged_in()) {
            return '<div class="dynamics-error"><p>' . __('You must be logged in to view your contact information.', 'dynamics-sync-lite') . '</p></div>';
        }
        
        $user = wp_get_current_user();
        $form_submitted = false;
        $error_message = '';
        $success_message = '';
        
        // Handle form submission (fallback for when AJAX fails)
        if (isset($_POST['dynamics_submit']) && wp_verify_nonce($_POST['dynamics_nonce'], 'dynamics_update_contact')) {
            $form_submitted = true;
            
            // Check Azure configuration immediately
            $config_check = $this->validate_azure_credentials_saved();
            if (!$config_check['configured']) {
                $error_message = $config_check['error_message'];
            } else {
                // Process the form if configuration is valid
                $validation_result = $this->validate_contact_form_data($_POST);
                if (!$validation_result['valid']) {
                    $error_message = $validation_result['message'];
                } else {
                    $connection_test = $this->dynamics_api->verify_azure_connection();
                    if (!$connection_test['success']) {
                        $error_message = __('Azure connection failed: ', 'dynamics-sync-lite') . $connection_test['message'];
                    } else {
                        $contact_data = $validation_result['data'];
                        $result = $this->dynamics_api->update_contact($user->user_email, $contact_data);
                        if ($result['success']) {
                            $success_message = __('Contact information updated successfully!', 'dynamics-sync-lite');
                        } else {
                            $error_message = $result['message'];
                        }
                    }
                }
            }
        }
        
        // Try to get contact data, but don't block form rendering if it fails
        $contact_result = $this->dynamics_api->get_contact_by_email_with_error_handling($user->user_email);
        $contact_data = $contact_result['data'];
        $api_error = $contact_result['error'];
        
        ob_start();
        ?>
        <div id="dynamics-contact-form-container">
            <?php if ($form_submitted && !empty($error_message)): ?>
                <div class="dynamics-error">
                    <p><strong><?php _e('Error:', 'dynamics-sync-lite'); ?></strong></p>
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($form_submitted && !empty($success_message)): ?>
                <div class="dynamics-success">
                    <p><strong><?php _e('Success:', 'dynamics-sync-lite'); ?></strong></p>
                    <p><?php echo esc_html($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($api_error && !$form_submitted): ?>
                <div class="dynamics-warning">
                    <p><strong><?php _e('Notice:', 'dynamics-sync-lite'); ?></strong></p>
                    <p><?php _e('Unable to load your current information from Dynamics 365. You can still submit updates using the form below.', 'dynamics-sync-lite'); ?></p>
                </div>
            <?php endif; ?>
            
            <div id="dynamics-loading" style="display: none;">
                <p><?php _e('Updating your information...', 'dynamics-sync-lite'); ?></p>
            </div>
            
            <div id="dynamics-messages"></div>
            
            <form id="dynamics-contact-form" method="post">
                <?php wp_nonce_field('dynamics_update_contact', 'dynamics_nonce'); ?>
                <input type="hidden" name="dynamics_submit" value="1" />
                
                <div class="dynamics-form-group">
                    <label for="first_name"><?php _e('First Name', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($contact_data['firstname'] ?? $_POST['first_name'] ?? ''); ?>" required />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="last_name"><?php _e('Last Name', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($contact_data['lastname'] ?? $_POST['last_name'] ?? ''); ?>" required />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="email"><?php _e('Email Address', 'dynamics-sync-lite'); ?></label>
                    <input type="email" id="email" name="email" value="<?php echo esc_attr($contact_data['emailaddress1'] ?? $_POST['email'] ?? $user->user_email); ?>" required />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="phone"><?php _e('Phone Number', 'dynamics-sync-lite'); ?></label>
                    <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($contact_data['telephone1'] ?? $_POST['phone'] ?? ''); ?>" />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="address"><?php _e('Address', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="address" name="address" value="<?php echo esc_attr($contact_data['address1_line1'] ?? $_POST['address'] ?? ''); ?>" />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="city"><?php _e('City', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="city" name="city" value="<?php echo esc_attr($contact_data['address1_city'] ?? $_POST['city'] ?? ''); ?>" />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="state"><?php _e('State/Province', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="state" name="state" value="<?php echo esc_attr($contact_data['address1_stateorprovince'] ?? $_POST['state'] ?? ''); ?>" />
                </div>
                
                <div class="dynamics-form-group">
                    <label for="postal_code"><?php _e('Postal Code', 'dynamics-sync-lite'); ?></label>
                    <input type="text" id="postal_code" name="postal_code" value="<?php echo esc_attr($contact_data['address1_postalcode'] ?? $_POST['postal_code'] ?? ''); ?>" />
                </div>
                
                <div class="dynamics-form-group">
                    <input type="submit" value="<?php _e('Update Contact Information', 'dynamics-sync-lite'); ?>" class="button button-primary" />
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function check_plugin_configuration() {
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        // Check if any required field is empty
        if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($resource_url)) {
            $missing_fields = array();
            if (empty($client_id)) $missing_fields[] = 'Client ID';
            if (empty($client_secret)) $missing_fields[] = 'Client Secret';
            if (empty($tenant_id)) $missing_fields[] = 'Tenant ID';
            if (empty($resource_url)) $missing_fields[] = 'Resource URL';
            
            return array(
                'configured' => false,
                'message' => sprintf(
                    __('Missing configuration: %s. Please go to Settings > Dynamics Sync Lite to configure these fields.', 'dynamics-sync-lite'),
                    implode(', ', $missing_fields)
                )
            );
        }
        
        // Validate format of saved settings
        if (!$this->is_valid_uuid($client_id)) {
            return array(
                'configured' => false,
                'message' => __('Invalid Client ID format. Please check the configuration in Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        if (!$this->is_valid_uuid($tenant_id)) {
            return array(
                'configured' => false,
                'message' => __('Invalid Tenant ID format. Please check the configuration in Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        if (!filter_var($resource_url, FILTER_VALIDATE_URL) || parse_url($resource_url, PHP_URL_SCHEME) !== 'https') {
            return array(
                'configured' => false,
                'message' => __('Invalid Dynamics 365 URL format. Please check the configuration in Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        return array('configured' => true, 'message' => '');
    }
    
    public function ajax_update_contact() {
        // Enable error logging for debugging
        error_log('Dynamics Sync Lite: AJAX update contact called');
        
        // Verify nonce
        if (!isset($_POST['dynamics_nonce']) || !wp_verify_nonce($_POST['dynamics_nonce'], 'dynamics_update_contact')) {
            error_log('Dynamics Sync Lite: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'dynamics-sync-lite')));
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('Dynamics Sync Lite: User not logged in');
            wp_send_json_error(array('message' => __('You must be logged in', 'dynamics-sync-lite')));
            return;
        }
        
        // MANDATORY: Check if Azure credentials are saved in admin settings
        error_log('Dynamics Sync Lite: Checking Azure credentials');
        $azure_config_check = $this->validate_azure_credentials_saved();
        if (!$azure_config_check['configured']) {
            error_log('Dynamics Sync Lite: Azure credentials not configured - ' . $azure_config_check['error_message']);
            wp_send_json_error(array('message' => $azure_config_check['error_message']));
            return;
        }
        
        // Validate the submitted form data
        error_log('Dynamics Sync Lite: Validating form data');
        $validation_result = $this->validate_contact_form_data($_POST);
        if (!$validation_result['valid']) {
            error_log('Dynamics Sync Lite: Form validation failed - ' . $validation_result['message']);
            wp_send_json_error(array('message' => $validation_result['message']));
            return;
        }
        
        // Test Azure connection before proceeding with update
        error_log('Dynamics Sync Lite: Testing Azure connection');
        $connection_test = $this->dynamics_api->verify_azure_connection();
        if (!$connection_test['success']) {
            error_log('Dynamics Sync Lite: Azure connection failed - ' . $connection_test['message']);
            wp_send_json_error(array('message' => __('Azure connection failed: ', 'dynamics-sync-lite') . $connection_test['message']));
            return;
        }
        
        // All validations passed, proceed with the update
        error_log('Dynamics Sync Lite: Proceeding with contact update');
        $contact_data = $validation_result['data'];
        $user = wp_get_current_user();
        $result = $this->dynamics_api->update_contact($user->user_email, $contact_data);
        
        if ($result['success']) {
            error_log('Dynamics Sync Lite: Contact update successful');
            wp_send_json_success(array('message' => __('Contact information updated successfully!', 'dynamics-sync-lite')));
        } else {
            error_log('Dynamics Sync Lite: Contact update failed - ' . $result['message']);
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Specifically validates that Azure credentials are saved in WordPress admin settings
     */
    private function validate_azure_credentials_saved() {
        // Get values directly from WordPress options table
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        // Check if ANY credential is missing or empty
        if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($resource_url)) {
            return array(
                'configured' => false,
                'error_message' => __('ERROR: Azure connection is not configured in WordPress admin. Please contact the administrator to configure Microsoft Dynamics 365 settings in Admin Dashboard > Settings > Dynamics Sync Lite. All Azure credentials (Client ID, Client Secret, Tenant ID, Resource URL) must be saved before this form can work.', 'dynamics-sync-lite')
            );
        }
        
        // Additional validation - check for whitespace-only values
        if (trim($client_id) === '' || trim($client_secret) === '' || trim($tenant_id) === '' || trim($resource_url) === '') {
            return array(
                'configured' => false,
                'error_message' => __('ERROR: One or more Azure credentials are empty in WordPress admin settings. Please contact the administrator to properly configure all fields in Admin Dashboard > Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        return array('configured' => true, 'error_message' => '');
    }
    
    private function comprehensive_configuration_check() {
        // Get all configuration values
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        $errors = array();
        
        // Check for empty/missing values
        if (empty($client_id) || trim($client_id) === '') {
            $errors[] = __('Client ID is not configured', 'dynamics-sync-lite');
        }
        
        if (empty($client_secret) || trim($client_secret) === '') {
            $errors[] = __('Client Secret is not configured', 'dynamics-sync-lite');
        }
        
        if (empty($tenant_id) || trim($tenant_id) === '') {
            $errors[] = __('Tenant ID is not configured', 'dynamics-sync-lite');
        }
        
        if (empty($resource_url) || trim($resource_url) === '') {
            $errors[] = __('Dynamics 365 Resource URL is not configured', 'dynamics-sync-lite');
        }
        
        // If any field is missing, return error immediately
        if (!empty($errors)) {
            return array(
                'valid' => false,
                'message' => __('Microsoft Dynamics 365 is not properly configured. Missing: ', 'dynamics-sync-lite') . implode(', ', $errors) . __('. Please contact the administrator to configure these settings in WordPress Admin > Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        // Validate formats of configured values
        if (!$this->is_valid_uuid($client_id)) {
            return array(
                'valid' => false,
                'message' => __('Client ID format is invalid. Please check the configuration in Settings > Dynamics Sync Lite. It should be in UUID format (e.g., 12345678-1234-1234-1234-123456789abc).', 'dynamics-sync-lite')
            );
        }
        
        if (!$this->is_valid_uuid($tenant_id)) {
            return array(
                'valid' => false,
                'message' => __('Tenant ID format is invalid. Please check the configuration in Settings > Dynamics Sync Lite. It should be in UUID format (e.g., 12345678-1234-1234-1234-123456789abc).', 'dynamics-sync-lite')
            );
        }
        
        if (strlen($client_secret) < 32) {
            return array(
                'valid' => false,
                'message' => __('Client Secret appears to be invalid (too short). Please check the configuration in Settings > Dynamics Sync Lite. It should be at least 32 characters long.', 'dynamics-sync-lite')
            );
        }
        
        if (!filter_var($resource_url, FILTER_VALIDATE_URL)) {
            return array(
                'valid' => false,
                'message' => __('Dynamics 365 Resource URL format is invalid. Please check the configuration in Settings > Dynamics Sync Lite. It should be a valid URL (e.g., https://yourorg.crm.dynamics.com).', 'dynamics-sync-lite')
            );
        }
        
        if (parse_url($resource_url, PHP_URL_SCHEME) !== 'https') {
            return array(
                'valid' => false,
                'message' => __('Dynamics 365 Resource URL must use HTTPS protocol. Please check the configuration in Settings > Dynamics Sync Lite.', 'dynamics-sync-lite')
            );
        }
        
        // Check if URL looks like a Dynamics URL
        $host = parse_url($resource_url, PHP_URL_HOST);
        if (!preg_match('/\.crm\d*\.dynamics\.com$/i', $host)) {
            return array(
                'valid' => false,
                'message' => __('Dynamics 365 Resource URL does not appear to be a valid Dynamics 365 URL. Please check the configuration in Settings > Dynamics Sync Lite. It should end with .crm.dynamics.com or similar.', 'dynamics-sync-lite')
            );
        }
        
        // All validations passed
        return array('valid' => true, 'message' => '');
    }
    
    private function validate_contact_form_data($data) {
        $errors = array();
        
        // Required fields validation
        if (empty($data['first_name']) || strlen(trim($data['first_name'])) === 0) {
            $errors[] = __('First name is required.', 'dynamics-sync-lite');
        }
        
        if (empty($data['last_name']) || strlen(trim($data['last_name'])) === 0) {
            $errors[] = __('Last name is required.', 'dynamics-sync-lite');
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = __('Valid email address is required.', 'dynamics-sync-lite');
        }
        
        // Phone validation (if provided)
        if (!empty($data['phone']) && !preg_match('/^[\+]?[1-9][\d\s\-\(\)\.]{7,20}$/', $data['phone'])) {
            $errors[] = __('Please enter a valid phone number.', 'dynamics-sync-lite');
        }
        
        // Postal code validation (if provided)
        if (!empty($data['postal_code']) && strlen($data['postal_code']) > 20) {
            $errors[] = __('Postal code is too long.', 'dynamics-sync-lite');
        }
        
        if (!empty($errors)) {
            return array(
                'valid' => false,
                'message' => __('Please correct the following errors: ', 'dynamics-sync-lite') . implode(' ', $errors)
            );
        }
        
        // Return sanitized data
        return array(
            'valid' => true,
            'data' => array(
                'firstname' => sanitize_text_field($data['first_name']),
                'lastname' => sanitize_text_field($data['last_name']),
                'emailaddress1' => sanitize_email($data['email']),
                'telephone1' => sanitize_text_field($data['phone']),
                'address1_line1' => sanitize_text_field($data['address']),
                'address1_city' => sanitize_text_field($data['city']),
                'address1_stateorprovince' => sanitize_text_field($data['state']),
                'address1_postalcode' => sanitize_text_field($data['postal_code'])
            )
        );
    }
    
    public function ajax_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_dynamics_connection')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'dynamics-sync-lite')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'dynamics-sync-lite')));
        }
        
        // Test the connection
        $test_result = $this->dynamics_api->test_connection();
        
        if ($test_result['success']) {
            wp_send_json_success(array('message' => $test_result['message']));
        } else {
            wp_send_json_error(array('message' => $test_result['message']));
        }
    }
    
    public function activate() {
        // Create any necessary database tables or set default options
        add_option('dynamics_client_id', '');
        add_option('dynamics_client_secret', '');
        add_option('dynamics_tenant_id', '');
        add_option('dynamics_resource_url', '');
    }
    
    public function deactivate() {
        // Clean up if necessary
    }
}

/**
 * Dynamics API Handler Class
 */
class DynamicsAPI {
    
    private $access_token;
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $resource_url;
    
    public function __construct() {
        $this->client_id = get_option('dynamics_client_id');
        $this->client_secret = get_option('dynamics_client_secret');
        $this->tenant_id = get_option('dynamics_tenant_id');
        $this->resource_url = rtrim(get_option('dynamics_resource_url'), '/');
    }
    
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/token";
        
        $body = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'resource' => $this->resource_url
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            return $this->access_token;
        }
        
        return false;
    }
    
    public function get_contact_by_email($email) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array();
        }
        
        $api_url = $this->resource_url . "/api/data/v9.1/contacts?\$filter=emailaddress1 eq '" . urlencode($email) . "'";
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['value']) && !empty($data['value'])) {
            return $data['value'][0];
        }
        
        return array();
    }
    
    /**
     * Specifically verify Azure connection for form submission
     */
    public function verify_azure_connection() {
        // Double-check that credentials exist before testing connection
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($resource_url)) {
            return array(
                'success' => false, 
                'message' => 'Azure credentials are not saved in WordPress admin settings. Please contact administrator to configure Settings > Dynamics Sync Lite.'
            );
        }
        
        // Test the actual connection
        return $this->test_connection();
    }
    
    public function get_contact_by_email_with_error_handling($email) {
        // Check basic configuration first
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        // If not configured, return empty data without error (form will still show)
        if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($resource_url)) {
            return array(
                'data' => array(),
                'error' => null // No error shown on form load
            );
        }
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'data' => array(),
                'error' => __('Unable to authenticate with Microsoft Dynamics 365.', 'dynamics-sync-lite')
            );
        }
        
        $api_url = $this->resource_url . "/api/data/v9.1/contacts?\$filter=emailaddress1 eq '" . urlencode($email) . "'";
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'sslverify' => true,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'data' => array(),
                'error' => __('Network error while connecting to Dynamics 365.', 'dynamics-sync-lite')
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'data' => array(),
                'error' => __('Unable to retrieve your information from Dynamics 365.', 'dynamics-sync-lite')
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['value']) && !empty($data['value'])) {
            return array(
                'data' => $data['value'][0],
                'error' => null
            );
        }
        
        return array(
            'data' => array(),
            'error' => null
        );
    }
    
    public function update_contact($email, $contact_data) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array('success' => false, 'message' => __('Authentication failed. Please contact the administrator.', 'dynamics-sync-lite'));
        }
        
        // First, get the contact ID
        $existing_contact = $this->get_contact_by_email($email);
        
        if (empty($existing_contact)) {
            // Create new contact
            return $this->create_contact($contact_data);
        } else {
            // Update existing contact
            $contact_id = $existing_contact['contactid'];
            return $this->update_existing_contact($contact_id, $contact_data);
        }
    }
    
    private function create_contact($contact_data) {
        $access_token = $this->get_access_token();
        $api_url = $this->resource_url . "/api/data/v9.1/contacts";
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($contact_data),
            'sslverify' => true,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => __('Network error while creating contact: ', 'dynamics-sync-lite') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 201) {
            return array('success' => true, 'message' => __('Contact created successfully', 'dynamics-sync-lite'));
        } else {
            $body = wp_remote_retrieve_body($response);
            return array('success' => false, 'message' => sprintf(__('Failed to create contact. Status: %d, Response: %s', 'dynamics-sync-lite'), $response_code, $body));
        }
    }
    
    private function update_existing_contact($contact_id, $contact_data) {
        $access_token = $this->get_access_token();
        $api_url = $this->resource_url . "/api/data/v9.1/contacts(" . $contact_id . ")";
        
        $response = wp_remote_request($api_url, array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($contact_data),
            'sslverify' => true,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => __('Network error while updating contact: ', 'dynamics-sync-lite') . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 204) {
            return array('success' => true, 'message' => __('Contact updated successfully', 'dynamics-sync-lite'));
        } else {
            $body = wp_remote_retrieve_body($response);
            return array('success' => false, 'message' => sprintf(__('Failed to update contact. Status: %d, Response: %s', 'dynamics-sync-lite'), $response_code, $body));
        }
    }
    
    public function test_connection() {
        // First ensure we have basic configuration
        $client_id = get_option('dynamics_client_id', '');
        $client_secret = get_option('dynamics_client_secret', '');
        $tenant_id = get_option('dynamics_tenant_id', '');
        $resource_url = get_option('dynamics_resource_url', '');
        
        if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($resource_url)) {
            return array(
                'success' => false, 
                'message' => 'Missing Azure credentials. Please configure Client ID, Client Secret, Tenant ID, and Resource URL in WordPress admin.'
            );
        }
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'success' => false, 
                'message' => 'Authentication failed. Please verify your Azure credentials (Client ID, Client Secret, Tenant ID) are correct.'
            );
        }
        
        // Test API connectivity by making a simple request
        $api_url = $this->resource_url . "/api/data/v9.1/contacts?\$top=1";
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'sslverify' => true,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false, 
                'message' => 'Network connection failed: ' . $response->get_error_message() . '. Please check your Resource URL and network connectivity.'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            return array('success' => true, 'message' => 'Connection successful! Plugin is ready to use.');
        } elseif ($response_code === 401) {
            return array(
                'success' => false, 
                'message' => 'Authentication failed (401). Please verify your Client ID, Client Secret, and Tenant ID are correct.'
            );
        } elseif ($response_code === 403) {
            return array(
                'success' => false, 
                'message' => 'Access forbidden (403). Please verify your Azure app has proper permissions for Dynamics 365.'
            );
        } elseif ($response_code === 404) {
            return array(
                'success' => false, 
                'message' => 'Resource not found (404). Please verify your Dynamics 365 Resource URL is correct.'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            return array(
                'success' => false, 
                'message' => 'API request failed with status code: ' . $response_code . '. Please contact administrator with this error code.'
            );
        }
    }
}

// Initialize the plugin
new DynamicsSyncLite();
