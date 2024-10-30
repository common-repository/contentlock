<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">

    <h1 class="wp-heading-inline">Groups</h1>

    <?php // Get Email groups
    global $wpdb;
    $table_groups = $wpdb->prefix . "contentlock_groups"; // Datatable with groups
    $table_posts = $wpdb->prefix . "contentlock_posts"; // Datatable with posts
    $table_emails = $wpdb->prefix . "contentlock_emails"; // Datatable with emails

    // Delete existing group
    if ( isset($_GET['action']) && $_GET['action'] === 'delete_row' ) {

        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'cntlk_delete_single_group_nonce')) {
            wp_die('Invalid nonce value');
        }

        $row_id = isset($_GET['row_id']) ? intval($_GET['row_id']) : 0;
        if ($row_id > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table_posts WHERE group_id = %d", $row_id)); // Delete all records with the group_id
            $wpdb->query($wpdb->prepare("DELETE FROM $table_emails WHERE group_id = %d", $row_id)); // Delete all records (emails) with the group_id
            $wpdb->delete($table_groups, array('id' => $row_id)); // Delete the group
            echo '<div class="updated"><p>Group deleted successfully!</p></div>';
        }
    }

    // Add new group
    if ( isset($_POST['add_group']) && !empty(trim($_POST['new_group_name'])) ) {

        // Verify nonce
        if ( !isset( $_POST['cntlk_groups_form_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_groups_form_nonce']) ), 'cntlk_groups_form_nonce' ) ) {
            wp_die('Invalid nonce value');
        }

        $new_group_name = sanitize_text_field($_POST['new_group_name']);
        $wpdb->insert($table_groups, array('name' => $new_group_name));
        echo '<div class="updated"><p>New group added successfully!</p></div>';
    }
    
    // Table data
    if ( isset($_POST['s']) && !empty(trim($_POST['s'])) ) {

        // Verify nonce
        if ( !isset( $_POST['cntlk_search_in_groups_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_search_in_groups_nonce']) ), 'cntlk_search_in_groups_nonce' ) ) {
            wp_die('Invalid nonce value');
        }

        // Display Groups with searched email (Search input)
        $email = sanitize_text_field($_POST['s']);
        $table_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT groups.*
                FROM $table_groups AS groups
                INNER JOIN $table_emails AS emails ON groups.id = emails.group_id
                WHERE emails.email LIKE %s
                GROUP BY groups.id
                ORDER BY groups.name ASC;",
                '%' . $wpdb->esc_like($email) . '%'
            ),
            ARRAY_A
        );
    } else {
        // Display all Groups (Default)
        $table_data = $wpdb->get_results("SELECT id, name FROM $table_groups", ARRAY_A);
    }
    
    // Number of items
    $num_table_data = count($table_data); ?>

    <form method="post">
        <?php wp_nonce_field( 'cntlk_search_in_groups_nonce', 'cntlk_search_in_groups_nonce' );
        
        if ( isset($email) ) { ?>
            <span>Search results for: <strong><?php echo esc_attr($email); ?></strong></span>
        <?php } ?>
        <p class="search-box">
            <label class="screen-reader-text" for="search-email-address">Search Email in Groups:</label>
            <input type="search" id="search-email-address" name="s" value="<?php echo esc_attr( ( isset($email) ) ? $email : '' ); ?>">
            <input type="submit" id="search-submit" class="button" value="Search Email in Groups">
        </p>
    </form>

    <form method="post">
        <?php wp_nonce_field( 'cntlk_groups_form_nonce', 'cntlk_groups_form_nonce' ); ?>

        <div class="tablenav top">
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php echo esc_attr($num_table_data); ?> <?php echo ($num_table_data > 1) ? 'groups' : 'group'; ?></span>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" id="title" class="manage-column column-primary">
                        <span>Name</span>
                    </th>
                    <th scope="col" class="manage-column">Emails in Group</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if ( $table_data ) {
                    foreach ( $table_data as $row ) { ?>
                        <tr>
                            <td class="title has-row-actions column-primary page-title" data-colname="Name">
                                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=contentlock&group=' . $row['id'])); ?>" class="row-title"><?php echo esc_html($row['name']); ?></a></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=contentlock&group=' . $row['id'])); ?>">Edit</a></span> | 
                                    <span class="trash"><a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete_row', 'row_id' => $row['id'])), 'cntlk_delete_single_group_nonce')); ?>" onclick="return confirm('Are you sure you want to delete this group?')">Delete</a></span>
                                </div>
                                <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                            </td>
                            <?php // Count emails in group
                            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}contentlock_emails WHERE group_id = %d", $row['id'])); ?>
                            <td class="has-row-actions" data-colname="Emails in Group">
                                <?php echo esc_attr($count); ?>
                            </td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr class="no-items">
                        <td colspan="5">No Email groups found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php echo esc_attr($num_table_data); ?> <?php echo ($num_table_data > 1) ? 'groups' : 'group'; ?></span>
            </div>
            <br class="clear">
        </div>

        <div class="grid-container grid-cols-2">
            <div class="grid-item">
                <div class="postbox">
                    <h2><span class="icon dashicons dashicons-groups"></span> Add New Email Group</h2>
                    <div class="inside">
                        <input type="text" name="new_group_name" required>
                        <input type="submit" name="add_group" value="Add Email Group" class="button button-primary action">
                        <p>Create a group for your emails.</p>
                    </div>
                </div>
            </div>
        </div>

    </form>

    <div class="postbox">
        <h2><span class="icon dashicons dashicons-info"></span> Help</h2>
        <div class="inside">
            <ul>
                <li><strong><code>Step 1.</code> - Create a group.</strong> (To provide access to content that should not be visible to other visitors of the site.)</li>
                <li><strong><code>Step 2.</code> - Add the email addresses to the created group.</strong> (Visitors will receive a one-time access code to the content through these email addresses.)</li>
                <li><strong><code>Step 3.</code> - Select the group while editing a post or page.</strong> (You can select multiple previously created groups to grant visibility to.)</li>
            </ul>
        </div>
    </div>

</div>