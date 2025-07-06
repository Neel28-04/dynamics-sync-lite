# Dynamics Sync Lite

A secure WordPress plugin that allows logged-in users to view and update their own contact information stored in Microsoft Dynamics 365. Perfect for nonprofits and organizations that want to give donors and members control over their contact details.

## Features

- **Secure Integration**: OAuth 2.0 authentication with Microsoft Dynamics 365
- **User-Friendly Interface**: Clean contact form with real-time validation
- **WordPress Security**: Built with WordPress best practices including nonces, sanitization, and capability checks
- **Dual Submission**: AJAX for better UX with fallback to regular form submission
- **Multi-language Ready**: Translation-ready with proper internationalization
- **Error Handling**: Comprehensive error messages and connection testing

## Table of Contents

1. [Installation](#installation)
2. [Azure App Registration](#azure-app-registration)
3. [Plugin Configuration](#plugin-configuration)
4. [Usage](#usage)
5. [Design Decisions](#design-decisions)
6. [Known Limitations](#known-limitations)
7. [Troubleshooting](#troubleshooting)
8. [Security Considerations](#security-considerations)

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- HTTPS enabled (required for OAuth)
- Active Microsoft Dynamics 365 subscription
- Azure Active Directory access

### Installation Steps

1. **Download Plugin Files**

   ```
   /wp-content/plugins/dynamics-sync-lite/
   ├── dynamics-sync-lite.php (main plugin file)
   ├── assets/
   │   ├── dynamics-sync-lite.js
   │   └── dynamics-sync-lite.css
   └── README.md
   ```

2. **Upload to WordPress**

   - Upload the `dynamics-sync-lite` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Dynamics Sync Lite" and click "Activate"

## Azure App Registration

Before using the plugin, you must register an application in Azure Active Directory.

### Step 1: Create Azure App Registration

1. **Access Azure Portal**

   - Go to [portal.azure.com](https://portal.azure.com)
   - Sign in with your organization's Azure account

2. **Navigate to App Registrations**

   - Search for "App registrations" in the top search bar
   - Click on "App registrations" service

3. **Create New Registration**
   - Click "+ New registration"
   - Fill in the details:
     - **Name**: `WordPress Dynamics Sync Lite`
     - **Supported account types**: `Accounts in this organizational directory only`
     - **Redirect URI**: Leave blank for now
   - Click "Register"

### Step 2: Configure App Settings

1. **Note Application Details**

   - Copy the **Application (client) ID** - you'll need this
   - Copy the **Directory (tenant) ID** - you'll need this

2. **Create Client Secret**

   - Go to "Certificates & secrets" tab
   - Click "+ New client secret"
   - Add description: `WordPress Plugin Secret`
   - Choose expiration: `24 months` (recommended)
   - Click "Add"
   - **IMPORTANT**: Copy the secret value immediately - you won't see it again

3. **Set API Permissions**
   - Go to "API permissions" tab
   - Click "+ Add a permission"
   - Select "Dynamics CRM"
   - Choose "Delegated permissions"
   - Select: `user_impersonation`
   - Click "Add permissions"
   - Click "Grant admin consent" (if you have permissions)

### Step 3: Get Dynamics 365 URL

1. **Find Your Dynamics 365 Instance URL**
   - Go to your Dynamics 365 environment
   - The URL format is typically: `https://yourorg.crm.dynamics.com`
   - Copy this URL - you'll need it for plugin configuration

## Plugin Configuration

### Step 1: Access Plugin Settings

1. **Navigate to Settings**
   - Go to WordPress Admin
   - Click "Settings" → "Dynamics Sync Lite"

### Step 2: Enter Azure Credentials

Fill in the following fields with information from your Azure app registration:

1. **Client ID**

   - Enter the Application (client) ID from Azure
   - Format: `12345678-1234-1234-1234-123456789abc`

2. **Client Secret**

   - Enter the client secret value you copied from Azure
   - Must be at least 32 characters

3. **Tenant ID**

   - Enter the Directory (tenant) ID from Azure
   - Format: `12345678-1234-1234-1234-123456789abc`

4. **Dynamics Resource URL**
   - Enter your Dynamics 365 instance URL
   - Format: `https://yourorg.crm.dynamics.com`
   - Must use HTTPS protocol

### Step 3: Test Connection

1. **Save Settings**

   - Click "Save Changes" to store your configuration

2. **Test Connection**
   - Click the "Test Connection" button
   - You should see: "Connection successful! Plugin is ready to use."
   - If you see errors, verify your credentials and try again

## Usage

### Adding the Contact Form

Use the shortcode to display the contact form on any page or post:

```
[dynamics_contact_form]
```

**Examples:**

1. **On a Page**

   - Create a new page called "My Profile"
   - Add the shortcode `[dynamics_contact_form]` to the content
   - Publish the page

2. **In a Widget**

   - Add a Text widget to your sidebar
   - Insert the shortcode `[dynamics_contact_form]`

3. **In Template Files**
   ```php
   echo do_shortcode('[dynamics_contact_form]');
   ```

### User Experience

1. **For Logged-in Users**

   - Form displays with current contact information from Dynamics 365
   - Users can update: Name, Email, Phone, Address details
   - Form validates input and shows success/error messages

2. **For Non-logged-in Users**

   - Shows message: "You must be logged in to view your contact information"

3. **Configuration Errors**
   - If Azure credentials aren't configured, users see clear error message
   - Directs users to contact administrator

## Design Decisions

### Architecture Choices

1. **OAuth 2.0 Client Credentials Flow**

   - **Why**: Most secure for server-to-server communication
   - **Alternative considered**: Authorization Code flow (requires user consent)
   - **Decision**: Client credentials better for automated contact sync

2. **Dual Form Submission**

   - **Why**: Ensures reliability across different hosting environments
   - **Primary**: AJAX for better user experience
   - **Fallback**: Regular POST for guaranteed functionality

3. **Contact Matching by Email**
   - **Why**: Email is most reliable unique identifier
   - **Limitation**: Users with multiple email addresses may have issues
   - **Mitigation**: Uses WordPress user's primary email

### Security Design

1. **Input Sanitization**

   - All user inputs sanitized using WordPress functions
   - Email validation with `sanitize_email()`
   - Text fields with `sanitize_text_field()`

2. **Nonce Protection**

   - All forms protected with WordPress nonces
   - Prevents CSRF attacks

3. **Capability Checks**

   - Admin functions require `manage_options` capability
   - Contact updates require user authentication

4. **HTTPS Enforcement**
   - All API communication uses HTTPS
   - Plugin validates HTTPS in configuration

### User Experience Design

1. **Progressive Enhancement**

   - Form works without JavaScript (fallback)
   - Enhanced with AJAX for better experience

2. **Error Handling Strategy**

   - Show form first, validate on submission
   - Clear, actionable error messages
   - Preserve user input after errors

3. **Responsive Design**
   - Form adapts to different screen sizes
   - Clean, accessible styling

## Known Limitations

### Technical Limitations

1. **Single Contact Per User**

   - Plugin assumes one Dynamics contact per WordPress user
   - Matching is done by email address only
   - Multiple contacts with same email may cause conflicts

2. **Limited Field Support**

   - Currently supports basic contact fields only:
     - First Name, Last Name, Email, Phone
     - Address (single address line, city, state, postal code)
   - Custom fields not supported without code modification

3. **OAuth Token Management**

   - Tokens are not cached (fetched on each request)
   - May hit rate limits with high usage
   - No automatic token refresh handling

4. **Error Recovery**
   - No retry mechanism for failed API calls
   - Network timeouts may require manual retry
   - No offline mode or data queuing

### Integration Limitations

1. **Dynamics 365 Permissions**

   - Requires admin consent for API permissions
   - App registration must be done by Azure administrator
   - Cannot self-register for security reasons

2. **WordPress User Sync**

   - No automatic creation of WordPress users from Dynamics
   - No sync of Dynamics contacts to WordPress users
   - One-way update only (WordPress → Dynamics)

3. **Multi-site Compatibility**
   - Not tested with WordPress multisite
   - May require additional configuration for network installations

### Scalability Limitations

1. **Concurrent Usage**

   - No rate limiting implementation
   - High concurrent usage may exceed API limits
   - Recommended for small to medium organizations

2. **Performance**
   - API calls are synchronous (blocking)
   - Form submission may be slow with poor connectivity
   - No caching of contact data

## Troubleshooting

### Common Issues

1. **"Connection failed" Error**

   ```
   Possible causes:
   - Incorrect Azure credentials
   - API permissions not granted
   - Dynamics 365 URL incorrect
   - Network connectivity issues

   Solutions:
   - Verify all Azure app registration details
   - Check API permissions and admin consent
   - Test Dynamics 365 URL in browser
   - Check server firewall/proxy settings
   ```

2. **"Authentication failed" Error**

   ```
   Possible causes:
   - Incorrect Client ID or Client Secret
   - Incorrect Tenant ID
   - Client secret expired

   Solutions:
   - Double-check Azure app registration details
   - Generate new client secret if expired
   - Verify tenant ID matches your Azure directory
   ```

3. **Form Not Showing**

   ```
   Possible causes:
   - User not logged in
   - Shortcode not properly placed
   - Plugin not activated

   Solutions:
   - Ensure user is logged into WordPress
   - Check shortcode spelling: [dynamics_contact_form]
   - Verify plugin is activated in WordPress admin
   ```

### Debug Mode

Enable WordPress debug mode to see detailed error logs:

1. **Add to wp-config.php**

   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Check Error Logs**
   - Look in `/wp-content/debug.log`
   - Search for "Dynamics Sync Lite" entries

### Testing Connection

Use the built-in connection test:

1. Go to WordPress Admin → Settings → Dynamics Sync Lite
2. Enter all credentials
3. Click "Test Connection"
4. Review any error messages for specific issues

## Security Considerations

### Data Protection

1. **Credentials Storage**

   - Azure credentials stored in WordPress options table
   - Client secret stored in plain text (consider encryption for high-security environments)
   - Consider using environment variables for credentials in production

2. **Data Transmission**

   - All API communication uses HTTPS/TLS
   - No sensitive data cached or logged
   - OAuth tokens are short-lived

3. **Access Control**
   - Users can only view/edit their own contact information
   - Admin-only access to plugin configuration
   - Proper WordPress capability checks

### Recommendations

1. **Regular Updates**

   - Keep WordPress and plugins updated
   - Monitor for security patches
   - Review Azure app permissions regularly

2. **Monitoring**

   - Monitor failed authentication attempts
   - Set up alerts for API errors
   - Regular backup of plugin configuration

3. **Testing**
   - Test in staging environment before production
   - Verify user permissions and access controls
   - Test error handling scenarios

## Support and Development

### Getting Help

1. **Documentation**: Review this README thoroughly
2. **WordPress Logs**: Check debug logs for error details
3. **Azure Portal**: Verify app registration and permissions
4. **Dynamics 365**: Ensure user has proper access rights

### Customization

The plugin is designed to be extended. Common customization points:

1. **Additional Fields**: Modify the form and API calls to support more Dynamics fields
2. **Styling**: Override CSS classes for custom appearance
3. **Validation**: Add custom validation rules in `validate_contact_form_data()`
4. **Error Handling**: Customize error messages and handling logic

### Contributing

When modifying the plugin:

1. Follow WordPress coding standards
2. Maintain security best practices
3. Test thoroughly in staging environment
4. Document any changes or customizations

---

**Plugin Version**: 1.0.0  
**WordPress Compatibility**: 5.0+  
**PHP Compatibility**: 7.4+  
**Last Updated**: 2025-01-06

