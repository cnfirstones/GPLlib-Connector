<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class Settings {

    const PAGE = 'gpllib-connector';

    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
    }

    public static function menu(): void {
        add_menu_page(
            __( 'GPLlib Connector', 'gpllib-connector' ),
            __( 'GPLlib 自动更新', 'gpllib-connector' ),
            'manage_options',
            self::PAGE,
            [ self::class, 'render' ],
            'dashicons-update',
            81
        );
    }

    public static function render(): void {
        echo '<div class="wrap"><div id="gpllib-connector-app"></div></div>';
    }

    public static function enqueue( string $hook ): void {
        if ( 'toplevel_page_' . self::PAGE !== $hook ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $manifest_path = GPLLIB_CONNECTOR_DIR . 'admin/dist/.vite/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            add_action( 'admin_notices', static function () {
                echo '<div class="notice notice-warning"><p>'
                    . esc_html__( 'GPLlib Connector 前端资源未构建，请在 admin/ 目录执行 npm install && npm run build。', 'gpllib-connector' )
                    . '</p></div>';
            } );
            return;
        }

        $manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
        $entry    = $manifest['src/main.js'] ?? null;
        if ( ! $entry ) {
            return;
        }

        $base = GPLLIB_CONNECTOR_URL . 'admin/dist/';

        
        
        $css_files = (array) ( $entry['css'] ?? [] );
        foreach ( (array) $manifest as $item ) {
            if ( isset( $item['file'] ) && '.css' === substr( (string) $item['file'], -4 ) ) {
                $css_files[] = $item['file'];
            }
        }
        foreach ( array_unique( $css_files ) as $css ) {
            wp_enqueue_style( 'gpllib-connector-' . md5( $css ), $base . $css, [], GPLLIB_CONNECTOR_VERSION );
        }

        wp_enqueue_script( 'gpllib-connector-app', $base . $entry['file'], [], GPLLIB_CONNECTOR_VERSION, true );

        wp_add_inline_script(
            'gpllib-connector-app',
            'window.GPLLIB_CONNECTOR_CFG = ' . wp_json_encode( [
                'rest_url'   => esc_url_raw( rest_url( 'gpllib-connector/v1/' ) ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'site_url'   => home_url(),
                'site_host'  => License::site_domain(),
                'version'    => GPLLIB_CONNECTOR_VERSION,
                'locale'     => self::bcp47_locale(),
                'i18n'       => self::i18n_dict(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
            'before'
        );
        
        add_filter( 'script_loader_tag', static function ( $tag, $handle ) {
            if ( 'gpllib-connector-app' === $handle ) {
                return str_replace( '<script ', '<script type="module" ', $tag );
            }
            return $tag;
        }, 10, 2 );
    }

    
    private static function bcp47_locale(): string {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        return str_replace( '_', '-', $locale );
    }

    
    private static function i18n_dict(): array {
        return [
            
            'GPLlib 自动更新 · Connector' => __( 'GPLlib 自动更新 · Connector', 'gpllib-connector' ),
            '概览'                        => __( '概览', 'gpllib-connector' ),
            '设置'                        => __( '设置', 'gpllib-connector' ),

            
            '使用引导'                    => __( '使用引导', 'gpllib-connector' ),
            '打开 GPLlib 用户中心 →'      => __( '打开 GPLlib 用户中心 →', 'gpllib-connector' ),
            '获取授权码'                  => __( '获取授权码', 'gpllib-connector' ),
            '登录 GPLlib，在「用户中心 → 自动更新」复制专属授权码' => __( '登录 GPLlib，在「用户中心 → 自动更新」复制专属授权码', 'gpllib-connector' ),
            '激活本站'                    => __( '激活本站', 'gpllib-connector' ),
            '把授权码填入左侧，点击「激活并绑定」' => __( '把授权码填入左侧，点击「激活并绑定」', 'gpllib-connector' ),
            '自动更新'                    => __( '自动更新', 'gpllib-connector' ),
            '有权限且支持自动更新的主题/插件将自动检查并可一键升级' => __( '有权限且支持自动更新的主题/插件将自动检查并可一键升级', 'gpllib-connector' ),
            '提示：仅「会员有效期内」或「已单独购买」且 GPLlib 标注支持自动更新的资源才会出现更新。' => __( '提示：仅「会员有效期内」或「已单独购买」且 GPLlib 标注支持自动更新的资源才会出现更新。', 'gpllib-connector' ),

            
            '激活授权'                    => __( '激活授权', 'gpllib-connector' ),
            '在 GPLlib 用户中心「自动更新」页复制授权码，填入下方完成本站绑定。' => __( '在 GPLlib 用户中心「自动更新」页复制授权码，填入下方完成本站绑定。', 'gpllib-connector' ),
            '本站域名'                    => __( '本站域名', 'gpllib-connector' ),
            '授权码'                      => __( '授权码', 'gpllib-connector' ),
            '粘贴 GPLlib 用户中心的授权码' => __( '粘贴 GPLlib 用户中心的授权码', 'gpllib-connector' ),
            '激活并绑定'                  => __( '激活并绑定', 'gpllib-connector' ),

            
            '接口设置'                    => __( '接口设置', 'gpllib-connector' ),
            'API 地址'                    => __( 'API 地址', 'gpllib-connector' ),
            '一般无需修改；仅当 GPLlib 官方告知新地址时才需要调整。' => __( '一般无需修改；仅当 GPLlib 官方告知新地址时才需要调整。', 'gpllib-connector' ),
            '保存地址'                    => __( '保存地址', 'gpllib-connector' ),

            
            '绑定状态'                    => __( '绑定状态', 'gpllib-connector' ),
            '已绑定'                      => __( '已绑定', 'gpllib-connector' ),
            '需重新激活'                  => __( '需重新激活', 'gpllib-connector' ),
            '绑定域名'                    => __( '绑定域名', 'gpllib-connector' ),
            '会员资格'                    => __( '会员资格', 'gpllib-connector' ),
            '会员（永久）'                => __( '会员（永久）', 'gpllib-connector' ),
            
            '会员，到期：%s'              => __( '会员，到期：%s', 'gpllib-connector' ),
            '单购用户'                    => __( '单购用户', 'gpllib-connector' ),
            
            '已购资源 %s'                 => __( '已购资源 %s', 'gpllib-connector' ),
            '授权站点'                    => __( '授权站点', 'gpllib-connector' ),
            '令牌有效期'                  => __( '令牌有效期', 'gpllib-connector' ),
            '永久'                        => __( '永久', 'gpllib-connector' ),
            '已过期，请重新激活'          => __( '已过期，请重新激活', 'gpllib-connector' ),
            '上次检查'                    => __( '上次检查', 'gpllib-connector' ),
            '从未'                        => __( '从未', 'gpllib-connector' ),
            '立即检查更新'                => __( '立即检查更新', 'gpllib-connector' ),
            '确定解绑本站？解绑后将停止自动更新。' => __( '确定解绑本站？解绑后将停止自动更新。', 'gpllib-connector' ),
            '解绑本站'                    => __( '解绑本站', 'gpllib-connector' ),
            '令牌已失效或被吊销，请在 GPLlib 用户中心确认权限后重新激活。' => __( '令牌已失效或被吊销，请在 GPLlib 用户中心确认权限后重新激活。', 'gpllib-connector' ),

            
            '更新概览'                    => __( '更新概览', 'gpllib-connector' ),
            '受管资源'                    => __( '受管资源', 'gpllib-connector' ),
            '待更新'                      => __( '待更新', 'gpllib-connector' ),
            '所有受管资源均为最新版本。'  => __( '所有受管资源均为最新版本。', 'gpllib-connector' ),
            '展开'                        => __( '展开', 'gpllib-connector' ),
            '收起'                        => __( '收起', 'gpllib-connector' ),
            '实际更新在「仪表盘 → 更新」或插件/主题列表中执行。' => __( '实际更新在「仪表盘 → 更新」或插件/主题列表中执行。', 'gpllib-connector' ),
            '尚未激活，激活后可在此查看受管资源与待更新列表。' => __( '尚未激活，激活后可在此查看受管资源与待更新列表。', 'gpllib-connector' ),
            '尚未检查更新，点击「立即检查更新」获取最新结果。' => __( '尚未检查更新，点击「立即检查更新」获取最新结果。', 'gpllib-connector' ),
            '待更新资源'                  => __( '待更新资源', 'gpllib-connector' ),
            
            '新版本 %s'                   => __( '新版本 %s', 'gpllib-connector' ),
            '该资源本次未提供更新说明。'  => __( '该资源本次未提供更新说明。', 'gpllib-connector' ),

            
            '请输入授权码'                => __( '请输入授权码', 'gpllib-connector' ),
            '激活成功'                    => __( '激活成功', 'gpllib-connector' ),
            '激活失败'                    => __( '激活失败', 'gpllib-connector' ),
            '已解绑'                      => __( '已解绑', 'gpllib-connector' ),
            '检查完成'                    => __( '检查完成', 'gpllib-connector' ),
            '检查失败'                    => __( '检查失败', 'gpllib-connector' ),
            '已保存'                      => __( '已保存', 'gpllib-connector' ),
            '保存失败'                    => __( '保存失败', 'gpllib-connector' ),
            '响应异常'                    => __( '响应异常', 'gpllib-connector' ),
        ];
    }
}
