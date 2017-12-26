<?php

/**
 * Ajax Login and Forgot password handler class
 *
 * @since 2.8
 */

class WPUF_Login_Widget extends WP_Widget {

    function __construct() {

        parent::__construct(
            'WPUF_Login_Widget', 
            __('WPUF Ajax Login', 'wpuf'), 
            array( 'description' => __( 'Ajax Login widget for WP User Frontend', 'wpuf' ), ) 
        );

        add_action( 'widgets_init', array( $this, 'wpuf_ajax_login_widget' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
        add_action( 'wp_ajax_ajax_login', array( $this, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_ajax_login', array( $this, 'ajax_login' ) );
        add_action( 'wp_ajax_lost_password', array( $this, 'ajax_reset_pass' ) );
        add_action( 'wp_ajax_nopriv_lost_password', array( $this, 'ajax_reset_pass' ) );
        add_action( 'wp_ajax_ajax_logout', array( $this, 'ajax_logout' ) );
        add_filter( 'lostpassword_url', array( $this, 'lostpassword_url' ));
    }

    public function register_scripts() {

        wp_register_script( 'wp_ajax_login', WPUF_ASSET_URI . '/js/wpuf-ajax-login.js', array( 'jquery' ), false, true );
        
        wp_localize_script( 'wp_ajax_login', 'wpuf_ajax', array( 
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ));
    }

    /**
     * Ajax Login function
     *
     * @return void
     */
    public function ajax_login() {

        $user_login     = $_POST['log'];  
        $user_pass      = $_POST['pwd'];
        $rememberme     = $_POST['rememberme'];

        if ( empty( $user_login ) || empty( $user_pass ) ) {
            wp_send_json_error( array( 'message'=> __( 'Please fill all form fields', 'wpuf') ) );
        } else {
            $user = wp_signon( array('user_login' => $user_login, 'user_password' => $user_pass), false );

            if ( is_wp_error($user) ) {
                wp_send_json_error( array( 'message'=> $user->get_error_message() ) );
            } else {
                wp_send_json_success( array( 'message'=> __( 'Login successful!', 'wpuf' ) ) );
            }
        }
        wp_set_auth_cookie( $user->ID, $rememberme, $secure_cookie );
    }

    /**
     * Ajax Logout function
     *
     * @return void
     */
    public function ajax_logout() {

        wp_logout();
        wp_send_json_success( array( 'message'=> __( 'Logout successful!', 'wpuf' ) ) );
    }

    /**
     * Ajax password reset function
     *
     * @return void
     */
    function ajax_reset_pass() {

        $username_or_email = $_POST['user_login'];  

        // Check if input variables are empty
        if ( empty( $username_or_email ) ) {
            wp_send_json_error( array( 'error' => true, 'message'=> __( 'Please fill all form fields', 'wpuf' ) ) );
        } else {
            $username = is_email( $username_or_email ) ? sanitize_email( $username_or_email ) : sanitize_user( $username_or_email );

            $user_forgotten = $this->ajax_lostpassword_retrieve( $username );
            
            if ( is_wp_error( $user_forgotten ) ) {
                $lostpass_error_messages = $user_forgotten->errors;

                $display_errors = '';
                foreach ( $lostpass_error_messages as $error ) {
                    $display_errors .= '<p>'.$error[0].'</p>';
                }
                
                wp_send_json_error( array( 'message' => $display_errors ) );
            } else {
                wp_send_json_success( array( 'message' => __( 'Password has been reset. Please check your email.', 'wpuf' ) ) );
            }
        }
    }

    /**
     * Password retrieve function
     *
     * @return mixed
     */
    private function ajax_lostpassword_retrieve( $user_input ) {
        
        global $wpdb, $wp_hasher;

        $errors = new WP_Error();

        if ( empty( $user_input ) ) {
            $errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or email address.', 'wpuf'));
        } elseif ( strpos( $user_input, '@' ) ) {
            $user_data = get_user_by( 'email', trim( $user_input ) );
            if ( empty( $user_data ) )
                $errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.', 'wpuf'));
        } else {
            $login = trim($user_input);
            $user_data = get_user_by('login', $login);
        }

        /**
         * Fires before errors are returned from a password reset request.
         */
        do_action( 'lostpassword_post', $errors );

        if ( $errors->get_error_code() )
            return $errors;

        if ( !$user_data ) {
            $errors->add( 'invalidcombo', __( '<strong>ERROR</strong>: Invalid username or email.', 'wpuf' ) );
            return $errors;
        }

        // Redefining user_login ensures we return the right case in the email.
        $user_login = $user_data->user_login;
        $user_email = $user_data->user_email;
        $key = get_password_reset_key( $user_data );

        if ( is_wp_error( $key ) ) {
            return $key;
        }

        $message = __('Someone has requested a password reset for the following account:', 'wpuf') . "\r\n\r\n";
        $message .= network_home_url( '/' ) . "\r\n\r\n";
        $message .= sprintf(__('Username: %s', 'wpuf'), $user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, just ignore this email and nothing will happen.', 'wpuf') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:', 'wpuf') . "\r\n\r\n";
        $message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";
        
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $title = sprintf( __('[%s] Password Reset', 'wpuf'), $blogname );

        $title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

        $message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

        if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) )
            $errors->add('mailfailed', __('<strong>ERROR</strong>: The email could not be sent.Possible reason: your host may have disabled the mail() function.', 'wpuf'));

        return true;
    }

    /**
     * Display Ajax Login widget
     *
     * @return void
     */
    public function widget( $args, $instance ) {

        wp_enqueue_script( 'wp_ajax_login' );

        $title            = apply_filters( 'widget_title', $instance['title'] );
        $log_in_header    = apply_filters( 'widget_text_content', $instance['log_in_header'] );
        $pwd_reset_header = apply_filters( 'widget_text_content', $instance['pwd_reset_header'] );
        $uname_label      = apply_filters( 'widget_text', $instance['uname_label'] );
        $pwd_label        = apply_filters( 'widget_text', $instance['pwd_label'] );
        $remember_label   = apply_filters( 'widget_text', $instance['remember_label'] );
        $log_in_label     = apply_filters( 'widget_text', $instance['log_in_label'] );
        $pass_reset_label = apply_filters( 'widget_text', $instance['pass_reset_label'] );
 
        echo $args['before_widget'];
        if ( ! empty( $title ) ) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
 
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            echo get_avatar( $user_id, 24 );
        }

        $login_args = array(
            'redirect'          => '',
            'form_id'           => 'wpuf_ajax_login_form',
            'label_username'    => $uname_label,
            'label_password'    => $pwd_label,
            'label_remember'    => $remember_label,
            'label_log_in'      => $log_in_label
        );
        ?>
        <div class='login-container' id="login-widget-container">
            <?php 
            if( ! is_user_logged_in() ) { // only show the registration/login form to non-logged-in members ?>
               
                <!-- Login form -->
                <div class="wpuf-ajax-login-form" id="wpuf-ajax-login">
                    <div class="wpuf-ajax-errors"></div>
                    <?php echo get_option( 'WPUF_Login_Widget' ); ?>

                    <p><?php echo $log_in_header; ?></p>
            
                    <?php 
                    wp_login_form( $login_args );
                    if ( get_option( 'users_can_register' ) ) {
                        $registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register', 'wpuf' ) );
                        echo apply_filters( 'register', $registration_url );
                        echo apply_filters( 'login_link_separator', ' | ' );
                    }?>
                    <a href="#wpuf-ajax-lost-pw-url" id="wpuf-ajax-lost-pw-url"><?php _e( 'Lost your password?', 'wpuf' ); ?></a>
                </div>

                <!-- Lost Password form -->
                <div class="wpuf-ajax-reset-password-form" id="wpuf-ajax-reset-password">
                    <form id="wpuf_ajax_reset_pass_form" action="<?php echo home_url( '/' ); ?>" method="POST">
                        <div class="wpuf-ajax-errors"></div>
                        <p> <?php echo $pwd_reset_header; ?> </p>
                        <p>
                            <label for="wpuf-user_login"><?php _e( 'Username or E-mail:', 'wpuf' ); ?></label>
                            <input type="text" name="user_login" id="wpuf-user_login" class="input" value="" size="20" />
                        </p>

                        <?php do_action( 'lostpassword_form' ); ?>

                        <p class="submit">
                            <input type="submit" name="wp-submit" id="wp-submit" value="<?php echo $pass_reset_label; ?>" />
                            <input type="hidden" name="redirect_to" value="<?php echo WPUF_Simple_Login::get_posted_value( 'redirect_to' ); ?>" />
                            <input type="hidden" name="wpuf_reset_password" value="true" />
                            <input type="hidden" name="action" value="lost_password" />

                            <?php wp_nonce_field( 'wpuf_lost_pass' ); ?>
                        </p>
                    </form>
                    <div id="ajax-lp-section">
                        <a href="#wpuf-ajax-login-url" id="wpuf-ajax-login-url"> <?php _e( 'Login', 'wpuf' ); ?> </a>
                        <?php
                            if ( get_option( 'users_can_register' ) ) {
                                echo apply_filters( 'login_link_separator', ' | ' );
                                $registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register', 'wpuf' ) );
                                echo apply_filters( 'register', $registration_url );
                            }
                        ?>
                    </div>
                </div>
            <?php } else { ?>
                <div class="wpuf-ajax-logout">                      
                    <a href="#logout"><?php echo __('Log out', 'wpuf') ?></a>
                </div>
            <?php } ?>      
        </div>

        <?php
        echo $args['after_widget']; 
    }
         
    /**
     * Ajax Login widget backend
     *
     * @return void
     */ 
    public function form( $instance ) {

        $title            = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'WPUF Login Widget', 'wpuf' );
        $log_in_header    = isset( $instance[ 'log_in_header' ] ) ? $instance[ 'log_in_header' ] : __( 'Username or Email Address', 'wpuf' );
        $pwd_reset_header = isset( $instance[ 'pwd_reset_header' ] ) ? $instance[ 'pwd_reset_header' ] : __( 'Please enter your username or email address. You will receive a link to create a new password via email', 'wpuf' );
        $uname_label      = isset( $instance[ 'uname_label' ] ) ? $instance[ 'uname_label' ] : __( 'Username', 'wpuf' );
        $pwd_label        = isset( $instance[ 'pwd_label' ] ) ? $instance[ 'pwd_label' ] : __( 'Password', 'wpuf' );
        $remember_label   = isset( $instance[ 'remember_label' ] ) ? $instance[ 'remember_label' ] : __( 'Remember Me', 'wpuf' );
        $log_in_label     = isset( $instance[ 'log_in_label' ] ) ? $instance[ 'log_in_label' ] : __( 'Log In', 'wpuf' );
        $pass_reset_label = isset( $instance[ 'pass_reset_label' ] ) ? $instance[ 'pass_reset_label' ] : __( 'Reset Password', 'wpuf' );

        // Ajax Login Widget admin form
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'log_in_header' ); ?>"><?php _e( 'Log-in Text:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'log_in_header' ); ?>" name="<?php echo $this->get_field_name( 'log_in_header' ); ?>" type="textarea" value="<?php echo esc_attr( $log_in_header ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'uname_label' ); ?>"><?php _e( 'Username Label:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'uname_label' ); ?>" name="<?php echo $this->get_field_name( 'uname_label' ); ?>" type="text" value="<?php echo esc_attr( $uname_label ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'pwd_label' ); ?>"><?php _e( 'Password Label:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'pwd_label' ); ?>" name="<?php echo $this->get_field_name( 'pwd_label' ); ?>" type="text" value="<?php echo esc_attr( $pwd_label ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'remember_label' ); ?>"><?php _e( 'Remember Me Label:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'remember_label' ); ?>" name="<?php echo $this->get_field_name( 'remember_label' ); ?>" type="text" value="<?php echo esc_attr( $remember_label ); ?>" />
        </p>
            <label for="<?php echo $this->get_field_id( 'log_in_label' ); ?>"><?php _e( 'Log In Label:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'log_in_label' ); ?>" name="<?php echo $this->get_field_name( 'log_in_label' ); ?>" type="text" value="<?php echo esc_attr( $log_in_label ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'pwd_reset_header' ); ?>"><?php _e( 'Password Reset Text:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'pwd_reset_header' ); ?>" name="<?php echo $this->get_field_name( 'pwd_reset_header' ); ?>" type="textarea" value="<?php echo esc_attr( $pwd_reset_header ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'pass_reset_label' ); ?>"><?php _e( 'Password Reset Label:', 'wpuf' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'pass_reset_label' ); ?>" name="<?php echo $this->get_field_name( 'pass_reset_label' ); ?>" type="text" value="<?php echo esc_attr( $pass_reset_label ); ?>" />
        </p>
        <?php 
    }

    /**
     * Updating widget replacing old instances with new
     *
     * @return $instance
     */
    public function update( $new_instance, $old_instance ) {

        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['log_in_header'] = ( ! empty( $new_instance['log_in_header'] ) ) ? strip_tags( $new_instance['log_in_header'] ) : '';
        $instance['pwd_reset_header'] = ( ! empty( $new_instance['pwd_reset_header'] ) ) ? strip_tags( $new_instance['pwd_reset_header'] ) : '';
        $instance['uname_label'] = ( ! empty( $new_instance['uname_label'] ) ) ? strip_tags( $new_instance['uname_label'] ) : '';
        $instance['pwd_label'] = ( ! empty( $new_instance['pwd_label'] ) ) ? strip_tags( $new_instance['pwd_label'] ) : '';
        $instance['remember_label'] = ( ! empty( $new_instance['remember_label'] ) ) ? strip_tags( $new_instance['remember_label'] ) : '';
        $instance['log_in_label'] = ( ! empty( $new_instance['log_in_label'] ) ) ? strip_tags( $new_instance['log_in_label'] ) : '';
        $instance['pass_reset_label'] = ( ! empty( $new_instance['pass_reset_label'] ) ) ? strip_tags( $new_instance['pass_reset_label'] ) : '';
        
        return $instance;
    }

    /**
     * Register Ajax Login widget
     *
     */
    public function wpuf_ajax_login_widget() {

        register_widget( 'WPUF_Login_Widget' );
    }

    /**
     * Hook to filter lost-password url
     *
     */
    public function wpuf_lostpassword_url() {

        $page_id = wpuf_get_option( 'login_page', 'wpuf_profile', false );

        if ( !$page_id ) {
            return false;
        }

        $url = get_permalink( $page_id );

        $login_url = apply_filters( 'wpuf_login_url', $url, $page_id );

        return $login_url . '?action=lostpassword';
    }

}