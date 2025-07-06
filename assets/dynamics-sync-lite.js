// Dynamics Sync Lite JavaScript
jQuery(document).ready(function($) {
    // Check if we should use AJAX or regular form submission
    var useAjax = true;
    
    $('#dynamics-contact-form').on('submit', function(e) {
        if (!useAjax) {
            // Allow normal form submission as fallback
            return true;
        }
        
        e.preventDefault();
        
        var $form = $(this);
        var $messages = $('#dynamics-messages');
        var $loading = $('#dynamics-loading');
        var $submitButton = $form.find('input[type="submit"]');
        
        // Clear previous messages
        $messages.empty();
        
        // Show loading and disable submit button
        $loading.show();
        $submitButton.prop('disabled', true).val(dynamics_ajax.messages.updating);
        
        // Get form data
        var formData = $form.serialize();
        formData += '&action=update_dynamics_contact';
        
        // Make AJAX request
        $.ajax({
            url: dynamics_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                console.log('AJAX Success Response:', response);
                
                $loading.hide();
                $submitButton.prop('disabled', false).val(dynamics_ajax.messages.update_button);
                
                if (response && response.success) {
                    $messages.html('<div class="dynamics-success"><p><strong>Success:</strong> ' + response.data.message + '</p></div>');
                    $('html, body').animate({
                        scrollTop: $messages.offset().top - 100
                    }, 500);
                } else {
                    var errorMessage = response && response.data && response.data.message ? response.data.message : dynamics_ajax.messages.unknown_error;
                    $messages.html('<div class="dynamics-error"><p><strong>Error:</strong> ' + errorMessage + '</p></div>');
                    $('html, body').animate({
                        scrollTop: $messages.offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error - Status:', status, 'Error:', error);
                console.log('Response Text:', xhr.responseText);
                
                $loading.hide();
                $submitButton.prop('disabled', false).val(dynamics_ajax.messages.update_button);
                
                // If AJAX fails completely, fall back to regular form submission
                if (status === 'timeout' || status === 'error') {
                    console.log('AJAX failed, falling back to regular form submission');
                    useAjax = false;
                    $form.off('submit'); // Remove this event handler
                    $form.submit(); // Submit the form normally
                    return;
                }
                
                var errorMessage = dynamics_ajax.messages.connection_error;
                
                // Try to parse error response
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse && errorResponse.data && errorResponse.data.message) {
                            errorMessage = errorResponse.data.message;
                        }
                    } catch (e) {
                        console.log('JSON Parse Error:', e);
                    }
                }
                
                $messages.html('<div class="dynamics-error"><p><strong>Error:</strong> ' + errorMessage + '</p></div>');
                $('html, body').animate({
                    scrollTop: $messages.offset().top - 100
                }, 500);
            }
        });
    });

    // Admin settings page JavaScript
    if ($('#dynamics-settings-form').length) {
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
            
            $btn.prop('disabled', true).text('Testing...');
            $result.html('<div class="spinner is-active" style="float: none;"></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_dynamics_connection',
                    nonce: dynamics_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Connection test failed. Please check your settings.</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }
});
