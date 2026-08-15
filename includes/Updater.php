<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class Updater {

    private const PKG_SCHEME   = 'gpllib-connector://';
    private const CACHE_TTL    = 21600; 
    private const MAX_ITEMS    = 200;

    public static function register(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ self::class, 'inject_plugins' ] );
        add_filter( 'pre_set_site_transient_update_themes',  [ self::class, 'inject_themes' ] );
        add_filter( 'upgrader_pre_download', [ self::class, 'pre_download' ], 10, 3 );
    }

    
    private static string $last_error = '';

    
    private const CHANGELOG_MAX = 2000;

    
    public static function refresh(): array {
        self::$last_error = '';
        $summary          = self::summarize( self::get_updates( true ) );
        $summary['error'] = self::$last_error;
        return $summary;
    }

    
    public static function cached_summary(): ?array {
        $cache = get_option( License::OPT_UPDATES );
        if ( ! is_array( $cache ) || ! isset( $cache['items'] ) || ! is_array( $cache['items'] ) ) {
            return null;
        }
        return self::summarize( $cache['items'] );
    }

    
    private static function summarize( array $items ): array {
        $names   = self::display_names();
        $pending = [];

        foreach ( $items as $slug => $it ) {
            if ( empty( $it['has_update'] ) || empty( $it['entitled'] ) || empty( $it['installable'] ) || empty( $it['online'] ) ) {
                continue;
            }
            $slug      = (string) $slug;
            
            
            $changelog = trim( wp_strip_all_tags( (string) ( $it['changelog'] ?? '' ) ) );
            if ( mb_strlen( $changelog ) > self::CHANGELOG_MAX ) {
                $changelog = mb_substr( $changelog, 0, self::CHANGELOG_MAX ) . '…';
            }
            $pending[] = [
                'slug'      => $slug,
                'name'      => (string) ( $names[ $slug ] ?? $slug ),
                'version'   => (string) ( $it['latest'] ?? '' ),
                'changelog' => $changelog,
            ];
        }

        return [
            'managed'    => count( $items ),
            'updatable'  => count( $pending ),
            'pending'    => $pending,
            'last_check' => (int) get_option( License::OPT_LAST_CHECK, 0 ),
        ];
    }

    
    private static function display_names(): array {
        $out = [];
        foreach ( self::installed_plugins() as $slug => $info ) {
            $out[ $slug ] = (string) ( $info['name'] ?? $slug );
        }
        foreach ( wp_get_themes() as $stylesheet => $theme ) {
            $slug = sanitize_title( $stylesheet );
            if ( '' !== $slug ) {
                $out[ $slug ] = (string) $theme->get( 'Name' );
            }
        }
        return $out;
    }

    

    public static function inject_plugins( $transient ) {
        if ( ! is_object( $transient ) || ! License::is_bound() ) {
            return $transient;
        }
        $updates = self::get_updates();
        if ( empty( $updates ) ) {
            return $transient;
        }
        $map = self::installed_plugins(); 
        foreach ( $map as $slug => $info ) {
            $u = $updates[ $slug ] ?? null;
            if ( ! self::is_updatable( $u, $info['version'] ) ) {
                continue;
            }
            if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                $transient->response = [];
            }
            $transient->response[ $info['file'] ] = (object) [
                'slug'        => $slug,
                'plugin'      => $info['file'],
                'new_version' => (string) $u['latest'],
                'url'         => self::detail_url( $u, $slug ),
                'package'     => self::PKG_SCHEME . 'plugin/' . $slug,
            ];
        }
        return $transient;
    }

    public static function inject_themes( $transient ) {
        if ( ! is_object( $transient ) || ! License::is_bound() ) {
            return $transient;
        }
        $updates = self::get_updates();
        if ( empty( $updates ) ) {
            return $transient;
        }
        foreach ( self::installed_themes() as $slug => $version ) {
            $u = $updates[ $slug ] ?? null;
            if ( ! self::is_updatable( $u, $version ) ) {
                continue;
            }
            if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                $transient->response = [];
            }
            $transient->response[ $slug ] = [
                'theme'       => $slug,
                'new_version' => (string) $u['latest'],
                'url'         => self::detail_url( $u, $slug ),
                'package'     => self::PKG_SCHEME . 'theme/' . $slug,
            ];
        }
        return $transient;
    }

    
    public static function pre_download( $reply, $package, $upgrader ) {
        if ( ! is_string( $package ) || 0 !== strpos( $package, self::PKG_SCHEME ) ) {
            return $reply; 
        }

        $ref  = substr( $package, strlen( self::PKG_SCHEME ) );
        $slug = (string) substr( strrchr( '/' . $ref, '/' ), 1 ); 
        if ( '' === $slug ) {
            return new \WP_Error( 'gpllib_connector_bad_pkg', __( '无效的更新包标识', 'gpllib-connector' ) );
        }

        $grant = Client::download_url( $slug ); 
        if ( is_wp_error( $grant ) ) {
            return $grant;
        }

        $url      = (string) $grant['download_url'];
        $expected = (string) $grant['zip_sha256'];
        unset( $grant ); 

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = download_url( $url, 300 ); 
        unset( $url );                    
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        
        if ( '' === $expected ) {
            return $tmp;
        }

        $actual = hash_file( 'sha256', $tmp );

        
        if ( ! is_string( $actual ) || ! hash_equals( $expected, strtolower( $actual ) ) ) {
            
            @unlink( $tmp ); 
            return new \WP_Error(
                'gpllib_connector_checksum_mismatch',
                sprintf(
                    
                    __( '更新包校验未通过：%s 的文件与 GPLlib 记录的不一致，可能是下载损坏或文件被篡改。已删除该临时文件，本次更新已中止，请稍后重试。', 'gpllib-connector' ),
                    $slug
                )
                
            );
        }

        return $tmp; 
    }

    

    private static function is_updatable( ?array $u, string $installed ): bool {
        return $u
            && ! empty( $u['known'] )
            && ! empty( $u['installable'] )
            && ! empty( $u['entitled'] )
            && ! empty( $u['online'] )
            && '' !== (string) ( $u['latest'] ?? '' )
            && version_compare( $installed, (string) $u['latest'], '<' );
    }

    
    private static function get_updates( bool $force = false ): array {
        if ( ! License::is_bound() ) {
            return [];
        }
        $cache = get_option( License::OPT_UPDATES );
        if ( ! $force && is_array( $cache ) && isset( $cache['ts'], $cache['items'] )
             && ( time() - (int) $cache['ts'] ) < self::CACHE_TTL ) {
            return $cache['items'];
        }

        $req = [];
        foreach ( self::installed_plugins() as $slug => $info ) {
            $req[ $slug ] = [ 'slug' => $slug, 'version' => $info['version'] ];
        }
        foreach ( self::installed_themes() as $slug => $version ) {
            $req[ $slug ] = [ 'slug' => $slug, 'version' => $version ];
        }
        $req = array_slice( array_values( $req ), 0, self::MAX_ITEMS );

        $data = Client::check_updates( $req );
        if ( is_wp_error( $data ) ) {
            self::$last_error = $data->get_error_message();
            return is_array( $cache['items'] ?? null ) ? $cache['items'] : [];
        }
        if ( empty( $data['items'] ) ) {
            return is_array( $cache['items'] ?? null ) ? $cache['items'] : [];
        }

        
        
        $map = [];
        foreach ( $data['items'] as $it ) {
            if ( ! empty( $it['slug'] ) && ! empty( $it['known'] ) ) {
                $map[ (string) $it['slug'] ] = $it;
            }
        }
        update_option( License::OPT_UPDATES, [ 'ts' => time(), 'items' => $map ], false );
        update_option( License::OPT_LAST_CHECK, time(), false );
        return $map;
    }

    
    private static function installed_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $out = [];
        foreach ( get_plugins() as $file => $data ) {
            $slug = strpos( $file, '/' ) !== false ? dirname( $file ) : basename( $file, '.php' );
            $slug = sanitize_title( $slug );
            if ( '' !== $slug ) {
                $out[ $slug ] = [
                    'file'    => $file,
                    'version' => (string) ( $data['Version'] ?? '0' ),
                    'name'    => (string) ( $data['Name'] ?? $slug ),
                ];
            }
        }
        return $out;
    }

    
    private static function installed_themes(): array {
        $out = [];
        foreach ( wp_get_themes() as $stylesheet => $theme ) {
            $slug = sanitize_title( $stylesheet );
            if ( '' !== $slug ) {
                $out[ $slug ] = (string) $theme->get( 'Version' );
            }
        }
        return $out;
    }

    
    private static function detail_url( ?array $u, string $slug ): string {
        $permalink = isset( $u['permalink'] ) ? trim( (string) $u['permalink'] ) : '';
        if ( '' !== $permalink ) {
            $permalink = esc_url_raw( $permalink );
            if ( '' !== $permalink ) {
                return $permalink;
            }
        }
        return self::info_url( $slug );
    }

    
    private static function info_url( string $slug ): string {
        unset( $slug ); 
        $base = preg_replace( '#/wp-json/.*$#', '', License::api_base() );
        return $base ? ( rtrim( $base, '/' ) . '/' ) : '';
    }
}
