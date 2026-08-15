<?php

namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class License {

    const OPT_API_BASE   = 'gpllib_connector_api_base';
    const OPT_LICENSE    = 'gpllib_connector_license';   
    const OPT_TOKEN      = 'gpllib_connector_token';     
    const OPT_DOMAIN     = 'gpllib_connector_domain';
    const OPT_STATUS     = 'gpllib_connector_status';    
    const OPT_LAST_CHECK = 'gpllib_connector_last_check';
    const OPT_UPDATES    = 'gpllib_connector_updates';   

    
    public static function api_base(): string {
        $base = (string) get_option( self::OPT_API_BASE, '' );
        if ( '' === $base ) {
            $base = GPLLIB_CONNECTOR_DEFAULT_API_BASE;
        }
        $base = (string) apply_filters( 'gpllib_connector_api_base', $base );
        return rtrim( $base, '/' );
    }

    
    public static function set_api_base( string $base ): bool {
        $base = rtrim( trim( $base ), '/' );
        if ( 0 === stripos( $base, 'http://' ) ) {
            $base = 'https://' . substr( $base, 7 ); 
        }
        $base = esc_url_raw( $base );
        if ( '' === $base || 0 !== stripos( $base, 'https://' ) ) {
            return false; 
        }
        update_option( self::OPT_API_BASE, $base );
        return true;
    }

    public static function token(): string {
        return (string) get_option( self::OPT_TOKEN, '' );
    }

    public static function license(): string {
        return (string) get_option( self::OPT_LICENSE, '' );
    }

    public static function domain(): string {
        return (string) get_option( self::OPT_DOMAIN, '' );
    }

    public static function status(): string {
        return (string) get_option( self::OPT_STATUS, 'unbound' );
    }

    public static function is_bound(): bool {
        return '' !== self::token() && 'active' === self::status();
    }

    
    public static function save_binding( string $license, string $token, string $domain ): void {
        update_option( self::OPT_LICENSE, sanitize_text_field( $license ) );
        update_option( self::OPT_TOKEN, $token ); 
        update_option( self::OPT_DOMAIN, sanitize_text_field( $domain ) );
        update_option( self::OPT_STATUS, 'active' );
    }

    public static function clear(): void {
        update_option( self::OPT_TOKEN, '' );
        update_option( self::OPT_STATUS, 'unbound' );
        delete_option( self::OPT_UPDATES );
    }

    public static function set_status( string $status ): void {
        update_option( self::OPT_STATUS, sanitize_key( $status ) );
    }

    
    public static function license_masked(): string {
        $k = self::license();
        return '' !== $k ? ( substr( $k, 0, 6 ) . '••••••' ) : '';
    }

    
    public static function site_domain(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return $host ? strtolower( $host ) : '';
    }
}
