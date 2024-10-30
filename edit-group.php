<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Check group ID
if ( !isset($_GET['group']) || !is_numeric($_GET['group']) ) {
    exit();
} else {
    $group_id = (int) wp_unslash( $_GET['group'] );
} ?>

<div class="wrap">
    
    <?php // Group
    global $wpdb;
    $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}contentlock_groups WHERE id = %d", $group_id));
    if ( $name === null ) {
        echo "<div class='error'><p>Sorry, there's no group with ID #" . esc_attr($group_id) . ".</p></div>";
        exit;
    }

    // Datatable with enabled emails
    $table = $wpdb->prefix . "contentlock_emails";

    // Delete existing (single) email
    if ( isset($_GET['action']) && $_GET['action'] === 'delete_row' ) {

        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'cntlk_delete_single_email_nonce')) {
            wp_die('Invalid nonce value');
        }

        $row_id = isset($_GET['row_id']) ? intval($_GET['row_id']) : 0;
        if ( $row_id > 0 ) {
            $wpdb->delete($table, array('id' => $row_id));
            echo '<div class="updated"><p>Email deleted successfully!</p></div>';
        }
    }
    
    // POST method form submits
    if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

        // Rename Group
        if ( isset($_POST['post_title']) && !empty($_POST['post_title']) && $name !== $_POST['post_title'] ) {

            // Verify nonce
            if ( !isset( $_POST['cntlk_rename_group_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_rename_group_nonce']) ), 'cntlk_rename_group_nonce' ) ) {
                wp_die('Invalid nonce value');
            }

            $new_name = sanitize_text_field($_POST['post_title']);
            $rename_group = $wpdb->update( $wpdb->prefix . "contentlock_groups", array('name' => $new_name), array('id' => $group_id) );
            if ( $rename_group !== false ) {
                $name = $new_name;
                echo '<div class="updated"><p>Group renamed.</p></div>';
            } else {
                echo '<div class="error"><p>New name is not valid!</p></div>';
            }
        }

        // Add new email address
        if ( isset($_POST['add_email']) && isset($_POST['new_email']) ) {

            // Verify nonce
            if ( !isset( $_POST['cntlk_add_new_email_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_add_new_email_nonce']) ), 'cntlk_add_new_email_nonce' ) ) {
                wp_die('Invalid nonce value');
            }

            $new_email = sanitize_email($_POST['new_email']);
            if ( is_email($new_email) ) {
                // Check email with "group_id"
                $existing_email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}contentlock_emails WHERE email = %s AND group_id = %s", $new_email, $group_id));
                if ( $existing_email === null ) {
                    $wpdb->insert($table, array('email' => $new_email, 'group_id' => $group_id));
                    echo '<div class="updated"><p>New email added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Email already exists in this group: <i>' . esc_attr($new_email) . '</i></p></div>';
                }
            } else {
                echo '<div class="error"><p>Email is not valid!</p></div>';
            }
        }

        // Import CSV
        if ( isset($_FILES['email_list']) ) {

            // Verify nonce
            if ( !isset( $_POST['cntlk_import_emails_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_import_emails_nonce']) ), 'cntlk_import_emails_nonce' ) ) {
                wp_die('Invalid nonce value');
            }

            $file = $_FILES['email_list'];

            // Check file extension
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['csv'];

            if ( in_array($file_extension, $allowed_extensions) ) {
                if ( $file['error'] === 0 ) {

                    global $wp_filesystem;

                    // Read file content
                    if ( $wp_filesystem->exists($file['tmp_name']) ) {
                        $file_content = $wp_filesystem->get_contents($file['tmp_name']);
                        if ($file_content !== false) {
                            $already_existing_emails = []; // Array for exiting emails (input)
                            $lines = explode("\n", $file_content);
                            foreach ($lines as $line) {
                                if ( trim($line) === '' ) continue; // Skip empty lines
                                $data = str_getcsv($line);
                                $new_email = sanitize_email($data[0]);
                                if ( is_email($new_email) ) {
                                    // Check email with "group_id"
                                    $existing_email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}contentlock_emails WHERE email = %s AND group_id = %s", $new_email, $group_id));
                                    if ( $existing_email === null ) {
                                        $insert_email = $wpdb->insert($table, array('email' => $new_email, 'group_id' => $group_id));
                                        if ( $insert_email !== false ) {
                                            $insert_email_success = true;
                                        }
                                    } else {
                                        $already_existing_emails[] = $new_email;
                                    }
                                }
                            }
                            // Messages
                            if ( isset($insert_email_success) && $insert_email_success === true ) {
                                echo '<div class="updated"><p>New emails added successfully!</p></div>';
                            }
                            if ( !empty($already_existing_emails) ) {
                                echo '<div class="notice notice-warning"><p>The following emails are already exists in this group:</p><ul>';
                                foreach ( $already_existing_emails as $already_existing_email ) {
                                    echo '<li><i>' . esc_attr($already_existing_email) . '</i></li>';
                                }
                                echo '</ul></div>';
                            }
                        }
                    }
                } else {
                    echo '<div class="error"><p>Hiba történt a fájl feltöltése során.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Hibás fájlformátum. Csak CSV fájlok engedélyezettek.</p></div>';
            }
        }

        // Bulk delete of emails
        if ( isset($_POST['emails_to_delete']) && !empty($_POST['emails_to_delete']) ) {

            // Verify nonce
            if ( !isset( $_POST['cntlk_emails_form_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_emails_form_nonce']) ), 'cntlk_emails_form_nonce' ) ) {
                wp_die('Invalid nonce value');
            }

            $emails_to_delete = array_map('sanitize_text_field', $_POST['emails_to_delete']);
            $success = true;
            foreach ($emails_to_delete as $email_id) {
                $deleted = $wpdb->delete($table, array('id' => $email_id));
                if ( $deleted === false ) {
                    $success = false;
                    break;
                }
            }
            if ( $success ) {
                echo '<div class="updated"><p>Email(s) deleted successfully!</p></div>';
            } else {
                echo '<div class="error"><p>There was an error deleting some emails. Please try again.</p></div>';
            }
        }

    }
    
    // Get all emails in the group
    $table = $wpdb->prefix . 'contentlock_emails';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE group_id = %d", $group_id), ARRAY_A);
    
    // Number of items
    $num_results = count($results);

    if ($name !== null) { ?>
        <h1 class="wp-heading-inline">Edit Email Group: <strong><?php echo esc_html($name); ?></strong></h1>
        <form method="post">
            <?php wp_nonce_field( 'cntlk_rename_group_nonce', 'cntlk_rename_group_nonce' ); ?>
            <div class="editor-header">
                <div id="titlediv" class="input-container">
                    <div id="titlewrap">
                        <label class="screen-reader-text" id="title-prompt-text" for="title">Enter title here</label>
                        <input type="text" name="post_title" size="30" value="<?php echo esc_html($name); ?>" id="title" spellcheck="true" autocomplete="off">
                    </div>
                </div>
                <div class="button-container">
                    <input type="submit" name="rename_group" value="Rename Group" id="editor-header-button" class="button button-secondary action" disabled>
                </div>
            </div>
        </form>
    <?php } ?>

    <div class="grid-container grid-cols-2">
        <div class="grid-item">
            <div class="postbox">
                <h2><span class="icon dashicons dashicons-plus"></span> Add New Email for Group</h2>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field( 'cntlk_add_new_email_nonce', 'cntlk_add_new_email_nonce' ); ?>
                        <input type="email" name="new_email" required>
                        <input type="submit" name="add_email" value="Add Email" class="button button-primary action">
                    </form>
                    <p>Adding a standalone email address to this group.</p>
                </div>
            </div>
        </div>
        <div class="grid-item">
            <div class="postbox">
                <h2><span class="icon dashicons dashicons-upload"></span> Import Emails from <i>CSV</i> File</h2>
                <div class="inside">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'cntlk_import_emails_nonce', 'cntlk_import_emails_nonce' ); ?>
                        <input type="file" name="email_list" accept=".csv" required>
                        <input type="submit" name="import_file" value="Import" class="button button-primary action">
                    </form>
                    <p>Export your email list in CSV format and upload it to this group. <a href="<?php echo esc_url(CNTLK_PLUGIN_DIR_URL . 'inc/contentlock-csv-example.csv'); ?>" download>[Download sample file]</a></p>
                </div>
            </div>
        </div>
    </div>

    <h2>Emails in Group</h2>
    
    <form id="email-actions" method="post">
        <?php wp_nonce_field( 'cntlk_emails_form_nonce', 'cntlk_emails_form_nonce' ); ?>
        
        <?php if ( $num_results > 0 ) { ?>
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <input type="submit" id="doaction" class="button action" value="Delete selected emails" onclick="return confirm('Are you sure you want to delete these emails?')">
                </div>
                <div class="tablenav-pages one-page">
                    <span class="displaying-num"><?php echo esc_attr($num_results); ?> <?php echo ($num_results > 1) ? 'emails' : 'email'; ?></span>
                </div>
                <br class="clear">
            </div>
        <?php } ?>
    
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                        <label for="cb-select-all-1"><span class="screen-reader-text">Select All</span></label>
                    </td>
                    <th>Email</th>
                    <th>Action</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($results) {
                foreach ($results as $row) { ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input id="cb-select-<?php echo esc_attr($row['id']); ?>" type="checkbox" name="emails_to_delete[]" value="<?php echo esc_attr($row['id']); ?>">
                            <label for="cb-select-<?php echo esc_attr($row['id']); ?>"><span class="screen-reader-text">Select email</span></label>
                        </th>
                        <td><strong><span class="row-title"><?php echo esc_attr($row['email']); ?></span></strong></td>
                        <td><a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete_row', 'row_id' => $row['id'])), 'cntlk_delete_single_email_nonce')); ?>" onclick="return confirm('Are you sure you want to delete this email?')">Delete</a></td>
                        <td><?php echo esc_attr($row['id']); ?></td>
                    </tr>
                <?php }
            } else { ?>
                <tr class="no-items">
                    <td colspan="4">No Emails found.</td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </form>

    <?php if ( $num_results > 0 ) { ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php echo esc_attr($num_results); ?> <?php echo ($num_results > 1) ? 'emails' : 'email'; ?></span>
            </div>
            <br class="clear">
        </div>
    <?php } ?>

</div>