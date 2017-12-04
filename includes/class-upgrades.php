<?php

/**
 * Plugin Upgrade Routine
 *
 * @since 2.2
 */
class WPUF_Upgrades {

    /**
     * The upgrades
     *
     * @var array
     */
    private static $upgrades = array(
        '2.1.9' => 'upgrades/upgrade-2.1.9.php',
        '2.6.0' => 'upgrades/upgrade-2.6.0.php',
        '2.7.0' => 'upgrades/upgrade-2.7.0.php'
    );

    /**
     * Get the plugin version
     *
     * @return string
     */
    public function get_version() {
        return get_option( 'wpuf_version' );
    }

    /**
     * Check if the plugin needs any update
     *
     * @return boolean
     */
    public function needs_update() {

        // may be it's the first install
        if ( ! $this->get_version() ) {
            return false;
        }

        if ( version_compare( $this->get_version(), WPUF_VERSION, '<' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Perform all the necessary upgrade routines
     *
     * @return void
     */
    function perform_updates() {
        $installed_version = $this->get_version();
        $path              = trailingslashit( dirname( __FILE__ ) );

        foreach ( self::$upgrades as $version => $file ) {
            if ( version_compare( $installed_version, $version, '<' ) ) {
                include $path . $file;
                update_option( 'wpuf_version', $version );
                wp_safe_redirect( admin_url( 'index.php?page=whats-new-wpuf' ) );
            }
        }

        update_option( 'wpuf_version', WPUF_VERSION );
    }
}
