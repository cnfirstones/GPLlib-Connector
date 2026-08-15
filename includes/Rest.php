<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class Rest {

    const NS = 'gpllib-connector/v1';

    public static function register(): void {
        add_action( 'rest_api_init', [ self::class, 'routes' ] );
    }

    public static function routes(): void {
        $perm = [ self::class, 'can' ];

        register_rest_route( self::NS, '/state', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'state' ],
            'permission_callback' => $perm,
        ] );
        register_rest_route( self::NS, '/activate', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'activate' ],
            'permission_callback' => $perm,
        ] );
        register_rest_route( self::NS, '/deactivate', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'deactivate' ],
            'permission_callback' => $perm,
        ] );
        register_rest_route( self::NS, '/check-now', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'check_now' ],
            'permission_callback' => $perm,
        ] );
        register_rest_route( self::NS, '/settings', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'save_settings' ],
            'permission_callback' => $perm,
        ] );
    }

    public static function can(): bool {
        return current_user_can( 'manage_options' );
    }

    public static function state(): \WP_REST_Response {
        return new \WP_REST_Response( self::build_state(), 200 );
    }

    public static function activate( \WP_REST_Request $request ): \WP_REST_Response {
        $license = sanitize_text_field( (string) $request->get_param( 'license_key' ) );
        if ( '' === $license ) {
            return new \WP_REST_Response( [ 'ok' => false, 'message' => __( '请输入授权码', 'gpllib-connector' ) ], 200 );
        }
        $r = Client::activate( $license );
        return new \WP_REST_Response( array_merge( $r, [ 'state' => self::build_state() ] ), 200 );
    }

    public static function deactivate(): \WP_REST_Response {
        $r = Client::deactivate();
        return new \WP_REST_Response( array_merge( $r, [ 'state' => self::build_state() ] ), 200 );
    }

    public static function check_now(): \WP_REST_Response {
        if ( ! License::is_bound() ) {
            return new \WP_REST_Response( [ 'ok' => false, 'message' => __( '尚未绑定', 'gpllib-connector' ) ], 200 );
        }
        $summary = Updater::refresh();

        
        
        $error = (string) ( $summary['error'] ?? '' );
        if ( '' !== $error ) {
            return new \WP_REST_Response( [
                'ok'      => false,
                'message' => $error,
                'state'   => self::build_state(),
            ], 200 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'summary' => $summary, 'state' => self::build_state() ], 200 );
    }

    public static function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $api = (string) $request->get_param( 'api_base' );
        if ( '' !== trim( $api ) && ! License::set_api_base( $api ) ) {
            return new \WP_REST_Response( [
                'ok'      => false,
                'message' => __( 'API 地址必须以 https:// 开头', 'gpllib-connector' ),
                'state'   => self::build_state(),
            ], 200 );
        }
        return new \WP_REST_Response( [ 'ok' => true, 'state' => self::build_state() ], 200 );
    }

    
    private static function build_state(): array {
        $state = [
            'bound'           => License::is_bound(),
            'status'          => License::status(),
            'domain'          => License::domain() ?: License::site_domain(),
            'site_host'       => License::site_domain(),
            'license_masked'  => License::license_masked(),
            'api_base'        => License::api_base(),
            'last_check'      => (int) get_option( License::OPT_LAST_CHECK, 0 ),
            'entitlement'     => null,
            
            
            'summary'         => Updater::cached_summary(),
        ];
        if ( License::is_bound() ) {
            $remote = Client::status();
            if ( ! is_wp_error( $remote ) && is_array( $remote ) ) {
                $state['entitlement'] = $remote['entitlement'] ?? null;
                $state['site_limit']  = $remote['site_limit'] ?? null;
                $state['sites_used']  = $remote['sites_used'] ?? null;
                $state['expires_at']  = $remote['expires_at'] ?? null;
            }
        }
        return $state;
    }
}
