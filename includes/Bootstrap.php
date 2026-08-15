<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class Bootstrap {

    public static function init(): void {
        require_once GPLLIB_CONNECTOR_DIR . 'includes/License.php';
        require_once GPLLIB_CONNECTOR_DIR . 'includes/ErrorMap.php';
        require_once GPLLIB_CONNECTOR_DIR . 'includes/Client.php';
        require_once GPLLIB_CONNECTOR_DIR . 'includes/Updater.php';
        require_once GPLLIB_CONNECTOR_DIR . 'includes/Settings.php';
        require_once GPLLIB_CONNECTOR_DIR . 'includes/Rest.php';

        
        if ( is_admin() ) {
            Settings::register();
        }
        Rest::register();
        Updater::register();
    }

    
    public static function on_deactivate(): void {
        require_once GPLLIB_CONNECTOR_DIR . 'includes/License.php';
        delete_option( License::OPT_UPDATES );
    }
}
