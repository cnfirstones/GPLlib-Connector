<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class Client {

    
    private const TIMEOUT_DEFAULT = 20;

    
    private const TIMEOUT_BY_PATH = [
        '/connector/check-updates' => 30,
        '/connector/download-url'  => 45,
    ];

    
    private const RETRYABLE_PATHS = [
        '/connector/check-updates',
        '/connector/status',
    ];

    
    private const RETRY_BACKOFF_US = 1500000; 

    
    private const RETRY_MIN_WINDOW = 5;

    
    public static function activate( string $license_key ): array {
        $domain = License::site_domain();
        $resp   = self::request( 'POST', '/connector/activate', [
            'license_key' => $license_key,
            'domain'      => $domain,
        ], false );

        if ( is_wp_error( $resp ) ) {
            return [ 'ok' => false, 'message' => $resp->get_error_message() ];
        }
        if ( ( $resp['code'] ?? -1 ) !== 0 || empty( $resp['data']['token'] ) ) {
            return [
                'ok'      => false,
                'code'    => (int) ( $resp['code'] ?? 0 ),
                'message' => (string) ( $resp['message'] ?? __( '激活失败', 'gpllib-connector' ) ),
            ];
        }

        License::save_binding( $license_key, (string) $resp['data']['token'], (string) ( $resp['data']['domain'] ?? $domain ) );
        return [ 'ok' => true ];
    }

    
    public static function deactivate(): array {
        $resp = self::request( 'POST', '/connector/deactivate', [], true );
        License::clear();
        if ( is_wp_error( $resp ) ) {
            return [ 'ok' => true, 'message' => __( '本地已解绑（主站通知失败）', 'gpllib-connector' ) ];
        }
        return [ 'ok' => true ];
    }

    
    public static function check_updates( array $items ) {
        $resp = self::request( 'POST', '/connector/check-updates', [ 'items' => array_values( $items ) ], true );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        if ( 0 !== (int) ( $resp['code'] ?? 0 ) ) {
            return new \WP_Error(
                'gpllib_connector_check_failed',
                (string) ( $resp['message'] ?? __( '检查更新失败', 'gpllib-connector' ) )
            );
        }
        return $resp['data'] ?? [ 'items' => [] ];
    }

    
    public static function download_url( string $slug ) {
        $resp = self::request( 'POST', '/connector/download-url', [ 'slug' => $slug ], true );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        if ( ( $resp['code'] ?? -1 ) !== 0 || empty( $resp['data']['download_url'] ) ) {
            return new \WP_Error(
                'gpllib_connector_no_url',
                (string) ( $resp['message'] ?? __( '无法获取下载地址', 'gpllib-connector' ) )
            );
        }
        $data = (array) $resp['data'];
        return [
            'download_url' => (string) $data['download_url'],
            
            'zip_sha256'   => strtolower( trim( (string) ( $data['zip_sha256'] ?? '' ) ) ),
            'version'      => (string) ( $data['version'] ?? '' ),
        ];
    }

    
    public static function status() {
        $resp = self::request( 'GET', '/connector/status', [], true );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        if ( 0 !== (int) ( $resp['code'] ?? 0 ) ) {
            return new \WP_Error(
                'gpllib_connector_status_failed',
                (string) ( $resp['message'] ?? __( '无法获取绑定状态', 'gpllib-connector' ) )
            );
        }
        return $resp['data'] ?? [];
    }

    

    
    private static function request( string $method, string $path, array $body, bool $auth ) {
        $url     = License::api_base() . $path;
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            
            
            
            'X-GPL-Lang'   => self::request_locale(),
            
            
            'User-Agent'   => self::user_agent(),
        ];

        if ( $auth ) {
            $token = License::token();
            if ( '' === $token ) {
                return new \WP_Error( 'gpllib_connector_unbound', __( '尚未绑定，请先激活', 'gpllib-connector' ) );
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        
        $budget  = self::timeout_for( $path );
        $started = microtime( true );

        $args = [
            'method'    => $method,
            'timeout'   => $budget,
            'headers'   => $headers,
            'sslverify' => self::should_verify_ssl( $url ),
        ];
        if ( 'GET' !== $method ) {
            $args['body'] = wp_json_encode( $body );
        }

        
        $retries_left = self::is_retryable( $path ) ? 1 : 0;

        while ( true ) {
            $res = wp_remote_request( $url, $args );

            $failed = is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) >= 500;
            if ( ! $failed || $retries_left < 1 ) {
                break;
            }

            
            $remaining = $budget - ( microtime( true ) - $started ) - ( self::RETRY_BACKOFF_US / 1000000 );
            if ( $remaining < self::RETRY_MIN_WINDOW ) {
                break; 
            }

            $retries_left--;
            usleep( self::RETRY_BACKOFF_US );
            $args['timeout'] = (int) floor( $remaining );
        }

        if ( is_wp_error( $res ) ) {
            
            return new \WP_Error( 'gpllib_connector_network', ErrorMap::network_message() );
        }

        $status      = (int) wp_remote_retrieve_response_code( $res );
        $retry_after = self::retry_after_seconds( $res );
        $data        = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( ! is_array( $data ) ) {
            
            
            return new \WP_Error( 'gpllib_connector_bad_response', ErrorMap::non_json( $status ) );
        }

        
        
        $code = $data['code'] ?? null;

        if ( $status >= 400 || ( is_numeric( $code ) && 0 !== (int) $code ) ) {
            
            $data['message'] = ErrorMap::message( $status, $code, (string) ( $data['message'] ?? '' ), $retry_after );

            
            if ( $auth && ErrorMap::is_credential_error( $status, $code ) ) {
                License::set_status( 'error' );
            }
        }

        return $data;
    }

    
    private static function timeout_for( string $path ): int {
        return self::TIMEOUT_BY_PATH[ $path ] ?? self::TIMEOUT_DEFAULT;
    }

    
    private static function is_retryable( string $path ): bool {
        return in_array( $path, self::RETRYABLE_PATHS, true );
    }

    
    private static function user_agent(): string {
        $version = defined( 'GPLLIB_CONNECTOR_VERSION' ) ? GPLLIB_CONNECTOR_VERSION : '0';
        return 'GPLlib-Connector/' . $version . '; ' . License::site_domain();
    }

    
    private static function retry_after_seconds( $res ): int {
        if ( is_wp_error( $res ) ) {
            return 0;
        }
        $raw = trim( (string) wp_remote_retrieve_header( $res, 'retry-after' ) );
        if ( '' === $raw ) {
            return 0;
        }
        if ( ctype_digit( $raw ) ) {
            $seconds = (int) $raw;
        } else {
            $ts      = strtotime( $raw );
            $seconds = $ts ? ( $ts - time() ) : 0;
        }
        return $seconds > 0 ? min( $seconds, 3600 ) : 0;
    }

    
    private static function request_locale(): string {
        return function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
    }

    
    private static function should_verify_ssl( string $url ): bool {
        $env  = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

        $is_local = in_array( $env, [ 'local', 'development' ], true )
            || 'localhost' === $host
            || (bool) preg_match( '/\.(local|test|localhost)$/', $host );

        return (bool) apply_filters( 'gpllib_connector_sslverify', ! $is_local, $url );
    }
}
