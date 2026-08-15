<?php


namespace GPLlib\Connector\Plugin;

defined( 'ABSPATH' ) || exit;

class ErrorMap {

    
    const CODE_FORBIDDEN            = 1002; 
    const CODE_RESOURCE_OFFLINE     = 1005; 
    const CODE_QUOTA_EXCEEDED       = 1006; 
    const CODE_SUBSCRIPTION_EXPIRED = 1007; 
    const CODE_NO_PURCHASE          = 1008; 
    const CODE_NONCE_INVALID        = 1012; 
    const CODE_RATE_LIMITED         = 1013; 
    const CODE_LICENSE_INVALID      = 1014; 
    const CODE_SITE_LIMIT           = 1015; 
    const CODE_READONLY_NODE        = 4003; 

    
    private const CREDENTIAL_CODES = [
        self::CODE_LICENSE_INVALID,
        self::CODE_SITE_LIMIT,
    ];

    
    public static function is_credential_error( int $status, $code ): bool {
        
        if ( 401 === $status ) {
            return true;
        }
        return is_numeric( $code ) && in_array( (int) $code, self::CREDENTIAL_CODES, true );
    }

    
    public static function message( int $status, $code, string $fallback = '', int $retry_after = 0 ): string {
        
        
        if ( 429 === $status || ( is_numeric( $code ) && self::CODE_RATE_LIMITED === (int) $code ) ) {
            return self::rate_limited( $retry_after );
        }

        $fallback = trim( $fallback );

        
        if ( is_numeric( $code ) && self::CODE_FORBIDDEN === (int) $code && '' !== $fallback ) {
            return $fallback;
        }

        if ( is_numeric( $code ) ) {
            $hit = self::by_code( (int) $code );
            if ( '' !== $hit ) {
                return $hit;
            }
        }

        if ( '' !== $fallback ) {
            return $fallback; 
        }

        return self::by_status( $status );
    }

    
    public static function by_code( int $code ): string {
        switch ( $code ) {
            case self::CODE_FORBIDDEN:
                return __( '当前授权无权执行该操作。', 'gpllib-connector' );
            case self::CODE_RESOURCE_OFFLINE:
                return __( '该资源已在 GPLlib 下架，暂不提供更新。', 'gpllib-connector' );
            case self::CODE_QUOTA_EXCEEDED:
                return __( '今日自动更新次数已用完，明天恢复。', 'gpllib-connector' );
            case self::CODE_SUBSCRIPTION_EXPIRED:
                return __( '会员已过期，续费后可继续自动更新。', 'gpllib-connector' );
            case self::CODE_NO_PURCHASE:
                return __( '你的 GPLlib 账户没有该资源的购买记录。', 'gpllib-connector' );
            case self::CODE_NONCE_INVALID:
                return __( '安全校验未通过，请稍后重试。', 'gpllib-connector' );
            case self::CODE_RATE_LIMITED:
                return __( '请求过于频繁，请稍后再试。', 'gpllib-connector' );
            case self::CODE_LICENSE_INVALID:
                return __( '授权码无效或绑定失败，请在 GPLlib 用户中心核对后重新激活。', 'gpllib-connector' );
            case self::CODE_SITE_LIMIT:
                return __( '授权站点数已达上限，请在 GPLlib 用户中心解绑其它站点后重试。', 'gpllib-connector' );
            case self::CODE_READONLY_NODE:
                return __( 'GPLlib 服务正在维护，暂时只读，请稍后再试。', 'gpllib-connector' );
        }
        return '';
    }

    
    public static function by_status( int $status ): string {
        if ( 401 === $status ) {
            return __( '本站令牌已失效或被吊销，请重新激活本站。', 'gpllib-connector' );
        }
        if ( 403 === $status ) {
            return __( 'GPLlib 拒绝了本次请求，请在 GPLlib 用户中心确认本站权限。', 'gpllib-connector' );
        }
        if ( 429 === $status ) {
            return self::rate_limited();
        }
        if ( $status >= 500 ) {
            return __( 'GPLlib 服务暂时不可用，请稍后再试。', 'gpllib-connector' );
        }
        
        return sprintf( __( '服务端响应异常（HTTP %d）', 'gpllib-connector' ), $status );
    }

    
    public static function rate_limited( int $retry_after = 0 ): string {
        if ( $retry_after > 0 ) {
            
            return sprintf( __( '请求过于频繁，请约 %d 秒后再试。', 'gpllib-connector' ), $retry_after );
        }
        return __( '请求过于频繁，请稍后再试。', 'gpllib-connector' );
    }

    
    public static function non_json( int $status ): string {
        if ( $status >= 500 ) {
            return __( 'GPLlib 服务暂时不可用（未返回可识别的数据），请稍后再试。', 'gpllib-connector' );
        }
        if ( 0 === $status || 404 === $status || ( $status >= 200 && $status < 300 ) ) {
            return __( '该地址没有返回 GPLlib 的数据，请核对设置页「设置 → API 地址」是否填写正确。', 'gpllib-connector' );
        }
        return self::by_status( $status );
    }

    
    public static function network_message(): string {
        return __( '无法连接 GPLlib，请检查本站的网络或防火墙设置。', 'gpllib-connector' );
    }
}
