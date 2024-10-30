<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Settings</h1>

    <?php // Update settings
    global $wpdb;
    $table = $wpdb->prefix . "contentlock_settings";

    if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

        // Verify nonce
        if ( !isset( $_POST['cntlk_settings_nonce'] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['cntlk_settings_nonce']) ), 'cntlk_settings_nonce' ) ) {
            wp_die('Invalid nonce value');
        }

        // Fields
        $email_subject = sanitize_text_field($_POST['email_subject']);
        $email_message = wp_kses_post($_POST['email_message']);

        // Validation
        if ( empty($email_subject) ) {
            echo '<div class="error"><p>Please fill out all required fields to proceed.</p></div>';
        } else {
            // Update Email subject
            $email_subject_update = $wpdb->update($table, array('value' => $email_subject), array('name' => 'email_subject'));

            // Update Email message
            $email_message_update = $wpdb->update($table, array('value' => $email_message), array('name' => 'email_message'));

            // Status
            if ( $email_subject_update !== false && $email_message_update !== false ) {
                echo '<div class="updated"><p>Settings saved.</p></div>';
            } else {
                echo '<div class="error"><p>An error occurred during the save process.</p></div>';
            }

        }

    } ?>

    <form method="post">
        <?php wp_nonce_field( 'cntlk_settings_nonce', 'cntlk_settings_nonce' ); ?>

        <h2>Email settings</h2>

        <p>You can set up the content of the outgoing email here, which will include the access code for the visitor to access the content.</p>

        <table class="form-table">
            <tbody>

                <tr>
                    <th>
                        <label for="email_subject_input">Email subject</label>
                    </th>
                    <td>
                        <?php // E-mail subject
                        $subject = $wpdb->get_row("SELECT value FROM $table WHERE name = 'email_subject'", ARRAY_A);
                        $subject = $subject['value']; ?>
                        <input type="text" id="email_subject_input" name="email_subject" value="<?php echo esc_html($subject); ?>" class="regular-text">
                        <p class="description">Modify the subject of the email.</p>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="email_message_editor">Email message</label>
                    </th>
                    <td>
                        <p>Insert or move <strong oncopy="return false" onpaste="return false" oncut="return false"><code>%CODE%</code></strong> in the email content where you want to display your generated code for the user.</p>
                        <?php // WP Editor for E-mail content
                        $content = $wpdb->get_row("SELECT value FROM $table WHERE name = 'email_message'", ARRAY_A);
                        $content = $content['value'];
                        $editor = 'email_message_editor'; // input id
                        $settings = array(
                            'wpautop' => false,
                            'media_buttons' => false,
                            'textarea_name' => 'email_message', // input name
                            'tinymce' => array(
                                // Items for the 'Visual' Tab
                                'toolbar1' => 'bold,italic,underline,link,unlink,forecolor,undo,redo',
                            ),
                            'quicktags' => array(
                                // Items for the 'Text' Tab
                                'buttons' => 'strong,em,underline,link'
                            )
                            
                        );
                        wp_editor( $content, $editor, $settings ); ?>
                        <p class="description">When you click on the <strong><i>Text</i></strong> tab, you can paste/modify your HTML email template. Make sure to include the <code>%CODE%</code> snippet in the text to display the access code in the outgoing email!</p>
                    </td>
                </tr>

            </tbody>
        </table>
        <?php // Save
        submit_button(__('Save Changes', 'contentlock')); ?>
    </form>
</div>