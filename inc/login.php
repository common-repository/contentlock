<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title( '', true ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(plugins_url('contentlock/css/login.css')); ?>">
    <script src="<?php echo esc_url(plugins_url('contentlock/js/login.js')); ?>" defer></script>
</head>
<body>
    <div class="container">
        <div class="frame animation-fade">

            <div class="login">

                <div class="preloader">
                    <figure>
                        <img src="<?php echo esc_url(plugins_url('contentlock/img/preloader.svg')); ?>" alt="Preloader">
                    </figure>
                </div>

                <?php if ( isset( $_SESSION['contentlock_valid_email'] ) ) {
                    
                    // Activation code
                    
                    // Response
                    if ( isset( $_SESSION['contentlock_invalid_code'] ) ) {
                        echo '<div class="alert alert-danger">Incorrect authentication code. Please try again.</div>';
                        unset($_SESSION['contentlock_invalid_code']);
                    } ?>
                    <h1 class="title">Activation</h1>
                    <hr>
                    <p>Please enter the single-use authentication code you received via email into the field below to gain access to the content associated with the group.</p>

                    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                        <input type="hidden" name="action" value="cntlk_login_verify_code">
                        <input type="hidden" name="contentlock_group_id" value="<?php echo esc_attr(implode(',', $group_ids)); ?>">
                        <?php wp_nonce_field( 'cntlk_login_verify_code_form_nonce', 'cntlk_login_verify_code_form_nonce' ); ?>
                        <div>
                            <label for="customlogin_email">Activation code</label>
                            <input type="text" class="form-control form-control__code" id="customlogin_code" name="code" autocomplete="off" required autofocus />
                        </div>
                        <button type="submit" name="submit" class="btn btn-wide btn-login">Login</button>
                    </form>

                <?php } else {
                    
                    // Email validation
                    
                    // Response
                    if ( isset( $_SESSION['contentlock_invalid_email'] ) ) {
                        echo '<div class="alert alert-danger">The provided email address is not listed within the group.</div>';
                        unset($_SESSION['contentlock_invalid_email']);
                    } ?>
                    <h1 class="title">Protected content</h1>
                    <hr>
                    <p>This content is only accessible to members of a specific group. If you are a member of this group, please enter your email address below.</p>

                    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                        <input type="hidden" name="action" value="cntlk_login_verify_email">
                        <input type="hidden" name="contentlock_group_id" value="<?php echo esc_attr(implode(',', $group_ids)); ?>">
                        <?php wp_nonce_field( 'cntlk_login_verify_email_form_nonce', 'cntlk_login_verify_email_form_nonce' ); ?>
                        <div>
                            <label for="customlogin_email">Email</label>
                            <input type="email" class="form-control" id="customlogin_email" name="email" required autofocus />
                        </div>
                        <button type="submit" name="submit" class="btn btn-wide btn-login">Login</button>
                    </form>

                <?php } ?>
                <div class="sitename"><?php bloginfo('name'); ?></div>
            </div>
            <?php // Navigate back
            if ( isset( $_SESSION['contentlock_previous_url'] ) ) {
                $back_url = sanitize_url( $_SESSION['contentlock_previous_url'] );
            } else {
                $back_url = home_url('/');
            } ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-small">Back</a>
        </div>
    </div>
</body>
</html>