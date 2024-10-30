<?php
/*
Plugin Name: ContentLock
Description: Secure access to your content with email-based two-step verification.
Author: Adam Solymosi
Author URI: https://www.linkedin.com/in/adam-solymosi/
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Version: 1.0.5
*/

/*
    Exit if accessed directly  
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
    Define constants
*/
define( 'CNTLK_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__) );
define( 'CNTLK_PLUGIN_DIR_URL', plugin_dir_url(__FILE__) );
define( 'CNTLK_PLUGIN_BASENAME', plugin_basename(__FILE__) );

/*
    Plugin activation hook
*/
function cntlk_plugin_activate() {

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Set datatable: Groups
    $table_groups = $wpdb->prefix . 'contentlock_groups';
    $sql_groups = "CREATE TABLE $table_groups (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Set datatable: Emails
    $table_emails = $wpdb->prefix . 'contentlock_emails';
    $sql_emails = "CREATE TABLE $table_emails (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(50) NOT NULL,
        group_id mediumint(9) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (group_id) REFERENCES $table_groups(id)
    ) $charset_collate;";

    // Set datatable: Posts
    $table_posts = $wpdb->prefix . 'contentlock_posts';
    $sql_posts = "CREATE TABLE $table_posts (
        post_id mediumint(9) NOT NULL,
        group_id mediumint(9) NOT NULL,
        PRIMARY KEY (post_id, group_id),
        FOREIGN KEY (group_id) REFERENCES $table_groups(id)
    ) $charset_collate;";

    // Set datatable: Settings
    $table_settings = $wpdb->prefix . 'contentlock_settings';
    $sql_settings = "CREATE TABLE $table_settings (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(50) NOT NULL,
        value longtext NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Create datatables:

    // Table for Groups
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_groups'") != $table_groups) {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_groups );
    }

    // Table for Emails
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_emails'") != $table_emails) {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_emails );
    }

    // Table for Posts
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_posts'") != $table_posts) {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_posts );
    }

    // Table for Settings
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_settings'") != $table_settings) {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_settings );
    }

    // Update "Settings" datatable:

    // Insert CNTLK_SECRET_KEY (for encryptions)
    $secret_key = $wpdb->get_row("SELECT name FROM $table_settings WHERE name = 'secret_key'", ARRAY_A);
    if ( !$secret_key ) {
        $cost_factor = 12;
        $secret_key_hash = password_hash("Heimdall", PASSWORD_BCRYPT, ['cost' => $cost_factor]);
        $wpdb->insert($table_settings, array('name' => 'secret_key', 'value' => $secret_key_hash));
    }
    
    // Insert Email subject
    $email_subject = $wpdb->get_row("SELECT name FROM $table_settings WHERE name = 'email_subject'", ARRAY_A);
    if ( !$email_subject ) {
        $default_email_subject = 'Activation code for access';
        $wpdb->insert($table_settings, array('name' => 'email_subject', 'value' => $default_email_subject));
    }

    // Insert Email subject
    $email_message = $wpdb->get_row("SELECT name FROM $table_settings WHERE name = 'email_message'", ARRAY_A);
    if ( !$email_message ) {
        $default_email_message = '<h1><strong>Hello,</strong></h1>
        <p>Your activation code for the content is: %CODE%</p>
        <p>Best regards</p>';
        $wpdb->insert($table_settings, array('name' => 'email_message', 'value' => $default_email_message));
    }

}
register_activation_hook( __FILE__, 'cntlk_plugin_activate' );

/*
    WordPress Admin menu
*/
function cntlk_menu() {

    // Main Menu
    add_menu_page(
        'ContentLock',
        'ContentLock',
        'manage_options',
        'contentlock',
        'cntlk_display_groups',
        plugins_url('contentlock/img/contentlock_icon.png')
    );

    // Subpage: Settings
    add_submenu_page(
        'contentlock',
        'ContentLock Settings',
        'Settings',
        'manage_options',
        'contentlock-settings',
        'cntlk_display_settings'
    );
}

// Display group layouts
function cntlk_display_groups() {
    if ( isset($_GET['group']) ) {
        include CNTLK_PLUGIN_DIR_PATH . 'edit-group.php'; // Edit group
    } else {
        include CNTLK_PLUGIN_DIR_PATH . 'groups.php'; // Groups (list)
    }
}

// Display plugin settings
function cntlk_display_settings() {
    include CNTLK_PLUGIN_DIR_PATH . 'settings.php';
}

// Add menu
add_action('admin_menu', 'cntlk_menu');

/*
    Add "Settings" url to plugin list
*/
function cntlk_settings_link($links) {
    $settings_link = '<a href="admin.php?page=contentlock-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . CNTLK_PLUGIN_BASENAME, 'cntlk_settings_link');

/*
    List of plugin pages
*/
function cntlk_is_plugin_admin_page() {
    // Plugin page slugs
    $plugin_pages = array(
        'contentlock',
        'contentlock-settings'
    );
    return isset($_GET['page']) && in_array($_GET['page'], $plugin_pages, true);
}

/*
    Register and load custom CSS file
*/
function cntlk_enqueue_scripts() {
    if ( cntlk_is_plugin_admin_page() ) {
        wp_enqueue_style('cntlk-admin-styles', CNTLK_PLUGIN_DIR_URL . 'css/admin.css', null, '1.0.1');
        wp_enqueue_script('cntlk-admin-scripts', CNTLK_PLUGIN_DIR_URL . 'js/admin.js', null, '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'cntlk_enqueue_scripts');

/*
    Add custom admin header on plugin pages
*/
function cntlk_admin_header() {
    if ( cntlk_is_plugin_admin_page() ) {
        include CNTLK_PLUGIN_DIR_PATH . '/inc/admin_header.php';
    }
}
add_action('in_admin_header', 'cntlk_admin_header');

/*
    Set secret key for encryptions
*/
function cntlk_set_secret_key() {
    global $wpdb;
    $table = $wpdb->prefix . "contentlock_settings"; // Datatable with settings
    $row = $wpdb->get_row("SELECT value FROM $table WHERE name = 'secret_key'", ARRAY_A);
    define("CNTLK_SECRET_KEY", $row['value']);
}
add_action('init', 'cntlk_set_secret_key');

/*
    WordPress Admin Meta box for edit layout
*/
function cntlk_meta_box() {
    $post_types = get_post_types( array( 'public' => true ), 'names' ); // All public post type
    add_meta_box( 'contentlock_select_groups', 'ContentLock', 'cntlk_meta_box_display', $post_types, 'side', 'core' );
}
add_action('add_meta_boxes', 'cntlk_meta_box');

function cntlk_meta_box_display() {
    wp_nonce_field('cntlk_metabox_nonce', 'cntlk_metabox_nonce'); // Generate nonce field
    echo '<p>Limit content visibility to the selected group(s):</p>';
    global $wpdb;
    $post_id = get_the_ID(); // Current "post_id"
    $table_groups = $wpdb->prefix . "contentlock_groups"; // Datatable with groups
    $table_posts = $wpdb->prefix . "contentlock_posts"; // Datatable with assigned posts
    $results = $wpdb->get_results(
        $wpdb->prepare(
        "SELECT id, name,
        IF(id IN (
            SELECT group_id
            FROM $table_posts
            WHERE post_id = %d
        ), 'Assigned', '') AS assigned
        FROM $table_groups", $post_id),
        ARRAY_A
    );

    // List the created groups
    if ( $results ) { ?>
        <ul class="categorychecklist">
            <?php foreach ( $results as $row ) { ?>
                <li>
                    <label>
                        <input value="<?php echo esc_attr($row['id']); ?>" type="checkbox" name="contentlock_groups[]" id="contentlock-groups-<?php echo esc_attr($row['id']); ?>" <?php echo ( $row['assigned'] === 'Assigned' ) ? 'checked="checked"' : ''; ?>>
                        <?php echo esc_html($row['name']); ?>
                    </label>
                </li>
            <?php } ?>
        </ul>
    <?php }
}

/*
    Save post hook - Save metabox settings
*/
function cntlk_save_custom_metabox_data($post_id) {

    // Check user's permission
    if ( !current_user_can('edit_post', $post_id) ) {
        return;
    }

    // Check metabox nonce
    if ( !isset($_POST['cntlk_metabox_nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cntlk_metabox_nonce'] ) ), 'cntlk_metabox_nonce') ) {
        return;
    }

    // Modify the "contentlock_posts" datatable
    global $wpdb;
    $table_posts = $wpdb->prefix . "contentlock_posts"; // Datatable with assigned posts
    if ( isset($_POST['contentlock_groups']) ) {
        $group_ids = array_map('sanitize_text_field', $_POST['contentlock_groups']); // Selected groups (Metabox)
        $group_ids_list = implode( ',', $group_ids );

        // Delete not relevant records
        $wpdb->query($wpdb->prepare("DELETE FROM $table_posts WHERE post_id = %d AND group_id NOT IN (%s)", $post_id, $group_ids_list));

        // Insert new records
        foreach ($group_ids as $group_id) {
            $existing_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_posts WHERE post_id = %d AND group_id = %d", $post_id, $group_id));
            if (!$existing_record) {
                $wpdb->insert(
                    "$table_posts",
                    array(
                        'post_id' => $post_id,
                        'group_id' => $group_id
                    ),
                    array(
                        '%d',
                        '%d'
                    )
                );
            }
        }
    } else {
        // Empty "contentlock_groups" input - Delete records with the current post_id
        $wpdb->delete($table_posts, array('post_id' => $post_id));
    }

}
add_action('save_post', 'cntlk_save_custom_metabox_data');

/*
    Delete relevant records from the plugin's "_posts" table before the permanent deletion
*/
function cntlk_before_delete_post( $post_id ) {

    global $wpdb;
    $table = $wpdb->prefix . "contentlock_posts"; // Datatable with assigned posts
    $wpdb->delete($table, array('post_id' => $post_id,));
  
}
add_action( 'before_delete_post', 'cntlk_before_delete_post' );

/*
    Prevent page load if necessary (Template Redirect)
*/
function cntlk_template_redirect() {

    // Start session
    if ( !session_id() ) {
        session_start();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'contentlock_posts';

    $post_id = get_queried_object_id(); // Current "post_id"

    // Get the group IDs
    $group_ids = $wpdb->get_col($wpdb->prepare("SELECT group_id FROM $table WHERE post_id = %d", $post_id));

    if ($group_ids) {

        // Check for validated group IDs
        if ( isset($_SESSION['contentlock_validated_groups'], $_SESSION['IV']) && !empty($_SESSION['contentlock_validated_groups']) ) {
            // Validated groups
            $validated_groups = sanitize_text_field(openssl_decrypt( $_SESSION['contentlock_validated_groups'], 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field( $_SESSION['IV'] ) )); // Validated groups decryption
            $validated_groups = unserialize($validated_groups);
            $commonIDs = array_intersect($validated_groups, $group_ids);
        } else {
            $commonIDs = NULL;
        }

        if ( !empty($commonIDs) ) {
            // User validated to access post/page
        } else {
            cntlk_display_login($group_ids);
        }

    }
}
add_action('template_redirect', 'cntlk_template_redirect');

/*
    Display login surface
*/
function cntlk_display_login($group_ids) {
    if ( !session_id() ) {
        session_start();
    }
    // URL for "Back" navigation
    if ( !empty(wp_get_referer()) && wp_get_referer() != get_the_permalink() ) {
        $_SESSION['contentlock_previous_url'] = wp_get_referer(); // Store previous url
    }
    include CNTLK_PLUGIN_DIR_PATH . 'inc/login.php'; // Login layout
    exit;
}

/*
    Generate activation code
*/
function cntlk_generate_code($length = 6) {
    $characters = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; // Enabled characters
    $random = '';
    
    for ($i = 0; $i < $length; $i++) {
        $random .= $characters[wp_rand(0, strlen($characters) - 1)];
    }
    
    return $random;
}

/*
    Email content
*/
function cntlk_email_content() {
    global $wpdb;
    $table = $wpdb->prefix . "contentlock_settings";
    $results = $wpdb->get_results("SELECT name, value FROM $table WHERE name = 'email_subject' OR name = 'email_message'", ARRAY_A);

    if ( $results ) {
        $values = array();
        foreach ($results as $result) {
            $name = $result['name'];
            $value = $result['value'];
            $values[$name] = $value;
        }
        return $values;
    } else {
        return array();
    }
}

/*
    Verify the email address (login.php response)
*/
function cntlk_login_verify_email() {
    if ( isset($_POST['submit']) && isset( $_POST['cntlk_login_verify_email_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_login_verify_email_form_nonce']) ), 'cntlk_login_verify_email_form_nonce' ) ) {

        // Start session
        if ( !session_id() ) {
            session_start();
        }
        
        // Post/page URL to redirect
        $url = wp_get_referer();

        global $wpdb;
        $table = $wpdb->prefix . "contentlock_emails"; // Datatable with enabled emails

        // Login variables
        $email = sanitize_email($_POST['email']);

        if ( isset( $_POST['contentlock_group_id'] ) ) {
            // Group(s)
            $group_ids = sanitize_text_field($_POST['contentlock_group_id']);
            $group_ids = explode(",",$group_ids);
            $group_ids = array_map('intval', $group_ids);

            // Look for email in specified group(s)
            $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE email = %s AND group_id IN (" . implode(', ', array_fill(0, count($group_ids), '%d')) . ")", $email, ...$group_ids));

        } else {
            $result = '';
        }
        
        if ( !empty($result) && $result !== NULL ) {

            // Valid email address

            $activation_code = cntlk_generate_code(); // Generate code for access
            $email_content = cntlk_email_content(); // Get email content from Database
            
            if ( !empty($email_content) ) {
                
                // Send email with the access code
                $subject = isset($email_content['email_subject']) ? $email_content['email_subject'] : null;
                $message = isset($email_content['email_message']) ? $email_content['email_message'] : null;
                
                $activation_code_formatted = '<span style="font-family: monospace; font-size: 35px; font-weight: bold;">' . $activation_code . '</span>';

                $message = str_replace("%CODE%", $activation_code_formatted, $message);
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($email, $subject, $message, $headers);

                // Set SESSION variables
                $_SESSION['contentlock_valid_email'] = true;
                $_SESSION['IV'] = sanitize_text_field(openssl_random_pseudo_bytes(16)); // Initialization Vector for encryption
                $_SESSION['contentlock_activation_code'] = openssl_encrypt(sanitize_text_field($activation_code), 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field($_SESSION['IV']) ); // Encrypt activation code
                $_SESSION['contentlock_entered_email'] = openssl_encrypt(sanitize_email($email), 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field($_SESSION['IV']) ); // Encrypt user email

            }

        } else {

            // Invalid email address
            
            // Set SESSION variables
            $_SESSION['contentlock_invalid_email'] = true;

        }

        // Redirect to login interface
        wp_redirect( $url );
        exit;
    }
}
add_action('init', 'cntlk_login_verify_email');

/*
    Verify the activation code (login.php response)
*/
function cntlk_login_verify_code() {
    if ( isset($_POST['submit']) && isset($_POST['code']) && isset( $_POST['cntlk_login_verify_code_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_login_verify_code_form_nonce']) ), 'cntlk_login_verify_code_form_nonce' ) ) {
        
        // Start session
        if ( !session_id() ) {
            session_start();
        }

        // Post/page URL to redirect
        $url = wp_get_referer();

        // Login variables
        $user_code = sanitize_text_field($_POST['code']); // Input of the user
        if ( isset( $_SESSION['contentlock_activation_code'], $_SESSION['IV'] ) ) {
            // Activation code decryption
            $activation_code = sanitize_text_field(openssl_decrypt( $_SESSION['contentlock_activation_code'], 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field( $_SESSION['IV'] ) ));
        } else {
            $activation_code = '';
        }
        if ( isset( $_SESSION['contentlock_entered_email'], $_SESSION['IV'] ) ) {
            // User email decryption
            $entered_email = sanitize_email(openssl_decrypt( $_SESSION['contentlock_entered_email'], 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field( $_SESSION['IV'] ) ));
        } else {
            $entered_email = '';
        }

        if ( $user_code === $activation_code ) {

            // Valid code
            
            // Get all group_id for the entered email address
            global $wpdb;
            $table = $wpdb->prefix . "contentlock_emails"; // Datatable with enabled emails
            $group_ids = array_column(
                $results = $wpdb->get_results($wpdb->prepare("SELECT group_id FROM $table WHERE email = %s", $entered_email), ARRAY_A),
                'group_id'
            );
            $group_ids = serialize($group_ids);

            // Set SESSION variables
            $_SESSION['contentlock_validated_groups'] = openssl_encrypt( sanitize_text_field($group_ids), 'aes-256-cbc', CNTLK_SECRET_KEY, OPENSSL_RAW_DATA, sanitize_text_field( $_SESSION['IV'] ) ); // Encrypt Group ID(s)

            // Unset SESSION variables
            unset($_SESSION['contentlock_valid_email']);

        } else {

            // Invalid code

            // Set SESSION variables
            $_SESSION['contentlock_invalid_code'] = true;

        }

        // Redirect to login interface
        wp_redirect( $url );
        exit;

    }
}
add_action('init', 'cntlk_login_verify_code');