<?php


defined( 'WP_UNINSTALL_PLUGIN' ) || exit;


$gpllib_connector_options = [
    'gpllib_connector_api_base',
    'gpllib_connector_license',
    'gpllib_connector_token',
    'gpllib_connector_domain',
    'gpllib_connector_status',
    'gpllib_connector_last_check',
    'gpllib_connector_updates',
];


if ( '' !== (string) get_option( 'gpllib_connector_token', '' )
     && 'active' === (string) get_option( 'gpllib_connector_status', '' ) ) {

    $gpllib_connector_dir = plugin_dir_path( __FILE__ );

    
    if ( ! defined( 'GPLLIB_CONNECTOR_DEFAULT_API_BASE' ) ) {
        define( 'GPLLIB_CONNECTOR_DEFAULT_API_BASE', 'https://gpllib.com/wp-json/gpl/v1' );
    }

    try {
        
        if ( is_readable( $gpllib_connector_dir . 'includes/License.php' )
             && is_readable( $gpllib_connector_dir . 'includes/ErrorMap.php' )
             && is_readable( $gpllib_connector_dir . 'includes/Client.php' ) ) {

            require_once $gpllib_connector_dir . 'includes/License.php';
            require_once $gpllib_connector_dir . 'includes/ErrorMap.php';
            require_once $gpllib_connector_dir . 'includes/Client.php';

            if ( class_exists( '\GPLlib\Connector\Plugin\Client' ) ) {
                \GPLlib\Connector\Plugin\Client::deactivate(); 
            }
        }
    } catch ( \Throwable $e ) {
        
        unset( $e );
    }
}


foreach ( $gpllib_connector_options as $gpllib_connector_option ) {
    delete_option( $gpllib_connector_option );
}
unset( $gpllib_connector_options, $gpllib_connector_option );
