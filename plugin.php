<?php
/*
    Plugin Name: miladoll-disable-wp-misbehaviour
    Plugin URI: https://miladoll.jp/
    Description: `miladoll-disable-wp-misbehaviour` let WordPress never misbehave
    Version: 0.1.2
    Author: MILADOLL Decchi
    Author URI: https://miladoll.jp/
    License: MIT
*/

class miladoll_disable_wp_misbehaviour {
    private $fqdn = '';
    private $admin_page_to_add = 'general';

    public function miladoll_disable_wp_misbehaviour() {
        // self::setup_plugin();
        // $this->fqdn = self::get_target_fqdn();
        ;
        if ( ! is_admin() ) {
            // 管理ページ下で動作させると異常を呼ぶので除外
            // 　例）「一般設定」→「サイトアドレス」値が消えるなど
            self::add_action_when_loaded();
        }
    }

    public function setup_plugin() {
        // Plugin API
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $this->plugin = get_plugin_data( __FILE__ );
        // 1st time
        $opt_fqdn = self::get_plugins_option( 'fqdn' );
        if ( ! $opt_fqdn ) {
            // デフォルト
            $home_url = home_url();
            $this_fqdn = array_shift( explode( '/', (explode( '//', $home_url ))[1] ) );
            self::set_plugins_option( 'fqdn', $this_fqdn );
        }
        if ( is_admin() ) {
            // プラグイン [設定] 追加
            add_filter(
                'plugin_action_links',
                array( $this, 'admin_set_link_for_plugin_admin' ),
                10, 2
            );
            add_filter(
                'admin_init',
                array( $this, 'admin_add_setting_field' ),
                10, 1
            );
        }
    }

    /*
        ヘルパメソッド
    */
    // クラス名取得
    public function get_class_name() {
        return( get_class( $this ) );
    }
    // 関連オプション名はすべて {$class_name}__{$opt} で統一する
    public function get_plugin_option_name( $opt ) {
        $class_name = self::get_class_name();
        return( "{$class_name}__$opt" );
    }
    // オプションgetter
    public function get_plugins_option( $opt ) {
        return( get_option( self::get_plugin_option_name( $opt ) ) );
    }
    // オプションsetter
    public function set_plugins_option( $opt, $value ) {
        update_option( self::get_plugin_option_name( $opt ), $value );
    }
    /*
        使用しているオプション：
        * miladoll_remove_uri_protohost__fqdn
            * 正規化（削除）の対象となるFQDN。| 区切りで列記
            * `.` はエスケープされずに収容する
            * グルーピング `(?:...)` は取得した者がやる
            * まとめて preg_quote すると | (?:) がエスケープされるので注意
    */

    /*
        管理画面追加
    */
    // プラグイン一覧ページの自分行に [設定] 追加
    public function admin_set_link_for_plugin_admin( $links, $file ) {
        $option_name_fqdn = self::get_plugin_option_name( 'fqdn' );
        if (
            $file == plugin_basename( __FILE__ )
            && current_user_can( 'manage_options' )
        ) {
            array_unshift(
                $links,
                '<a href="options-general.php#' . $option_name_fqdn . '">'
                . __( 'Settings' )
                . '</a>'
            );
        }
        return $links;
    }
    // オプション general セクションにオプション領域を追加する
    public function admin_add_setting_field() {
        $page_to_add = $this->admin_page_to_add;
        $option_name_fqdn = self::get_plugin_option_name( 'fqdn' );
        add_settings_field(
            $option_name_fqdn,
            __( '相対化対象FQDNs' ),
            array( $this, 'admin_draw_option' ),
            $page_to_add,
        );
        register_setting(
            $page_to_add,
            $option_name_fqdn
        );
    }
    public function admin_draw_option() {
        $opt_fqdn = self::get_plugins_option( 'fqdn' );
        $option_name_fqdn = self::get_plugin_option_name( 'fqdn' );
        ?>
            <fieldset>
                <input
                    type="text"
                    name="<?= $option_name_fqdn ?>"
                    id="<?= $option_name_fqdn ?>"
                    value="<?= $opt_fqdn ?>"
                    style="width: 32em;"
                >
                <p>
                    指定したドメインのURLがHTMLタグ中に存在した場合
                        <code>https?://FQDN</code>
                    部分を削除します。<br>
                    複数ドメインの指定は
                        <code>|</code>
                    で区切ります
                </p>
            </fieldset>
            <script type="text/javascript">
                // 無理やり [サイトアドレス (URL)] の次に移動させる
                jQuery( '#<?= $option_name_fqdn ?>' )
                    .closest( 'tr' )
                    .insertAfter(
                        jQuery( '#home' ).closest( 'tr' )
                    )
                ;
            </script>
        <?php
    }

    /*
        メインブロック
    */

    public function add_action_when_loaded() {
        add_action( 'wp_loaded', array( $this, 'behave' ) , PHP_INT_MAX, 1 );
    }
    // 本体
    public function behave() {
        // <meta name="generator" content="WordPress ...
        remove_action( 'wp_head', 'wp_generator' );
        // <link rel="wlwmanifest" ...
        remove_action( 'wp_head', 'wlwmanifest_link' );
        // <link rel="EditURI" ...
        remove_action( 'wp_head', 'rsd_link' );
        // <link rel='shortlink' ...
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
        // <link rel='https://api.w.org/' ...
        remove_action('wp_head','rest_output_link_wp_head');
        // <link rel="alternate" type="application/json+oembed" ...
        // <link rel="alternate" type="text/xml+oembed" ...
        remove_action('wp_head','wp_oembed_add_discovery_links');
        remove_action('wp_head','wp_oembed_add_host_js');
        remove_theme_support( 'automatic-feed-links' );
        // Link: <http://example.com/wp-json/>; rel="https://api.w.org/" in HTTP Response
        remove_action('template_redirect', 'rest_output_link_header', 11 );
        // Disable /wp-sitemap.xml
        add_filter( 'wp_sitemaps_enabled', '__return_false' );
        // DNS Prefetch
        add_filter(
            'wp_resource_hints',
            function( $hints, $relation_type ) {
                if ( 'dns-prefetch' === $relation_type ) {
                    return array_diff( wp_dependencies_unique_hosts(), $hints );
                }
                return $hints;
            },
            10, 2
        );
        // RSS article add
        add_action(
            'wp_head',
            function() {
                $url = esc_attr( get_bloginfo('rss2_url') );
                echo <<<"_EOF_rss"
                    <link rel="alternate" type="application/rss+xml" title="RSS" href="$url" />
_EOF_rss;
            },
            99
        );
    }

}

new miladoll_disable_wp_misbehaviour();
