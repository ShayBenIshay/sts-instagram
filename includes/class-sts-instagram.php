<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Instagram {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'sts_instagram_token_refresh_cron', array( $this, 'cron_refresh_token' ) );
    }

    // -------------------------------------------------------------------------
    // Activation / Deactivation
    // -------------------------------------------------------------------------

    public static function activate() {
        if ( ! wp_next_scheduled( 'sts_instagram_token_refresh_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'sts_instagram_token_refresh_cron' );
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'sts_instagram_token_refresh_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'sts_instagram_token_refresh_cron' );
        }
    }

    // -------------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------------

    public function cron_refresh_token() {
        $token = $this->get_access_token();
        if ( empty( $token ) ) {
            return;
        }
        $status = $this->check_token_expiry( $token );
        if ( false !== $status && ( ! empty( $status['expired'] ) || empty( $status['is_valid'] ) ) ) {
            $this->refresh_token( $token );
        }
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Instagram API Settings',
            'Instagram API',
            'manage_options',
            'sts-instagram-api',
            array( $this, 'settings_page' )
        );
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['save_instagram_api_settings'] ) ) {
            check_admin_referer( 'sts_instagram_api_settings_action' );

            $access_token = isset( $_POST['instagram_access_token'] ) ? sanitize_text_field( $_POST['instagram_access_token'] ) : '';
            $business_id  = isset( $_POST['instagram_business_id'] )  ? sanitize_text_field( $_POST['instagram_business_id'] )  : '';
            $app_id       = isset( $_POST['facebook_app_id'] )        ? sanitize_text_field( $_POST['facebook_app_id'] )        : '';
            $app_secret   = isset( $_POST['facebook_app_secret'] )    ? sanitize_text_field( $_POST['facebook_app_secret'] )    : '';

            update_option( 'sts_instagram_access_token', $access_token );
            update_option( 'sts_instagram_business_id',  $business_id );
            update_option( 'sts_facebook_app_id',        $app_id );
            update_option( 'sts_facebook_app_secret',    $app_secret );

            delete_transient( 'sts_instagram_posts' );
            delete_transient( 'sts_instagram_token_expiry' );

            echo '<div class="notice notice-success"><p>ההגדרות נשמרו בהצלחה!</p></div>';
        }

        $access_token = $this->get_access_token();
        $business_id  = $this->get_business_id();
        $app_id       = $this->get_app_id();
        $app_secret   = $this->get_app_secret();
        ?>
        <div class="wrap" dir="rtl">
            <h1>הגדרות Instagram API</h1>

            <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                <h2>הגדרת Instagram Business API</h2>

                <form method="post" action="">
                    <?php wp_nonce_field( 'sts_instagram_api_settings_action' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="instagram_business_id">Instagram Business ID</label></th>
                            <td>
                                <input type="text" name="instagram_business_id" id="instagram_business_id"
                                       class="regular-text" value="<?php echo esc_attr( $business_id ); ?>"
                                       placeholder="17841000000000000">
                                <p class="description">ה-ID של חשבון העסק שלך ב-Instagram. ניתן למצוא ב-Facebook Graph API Explorer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="instagram_access_token">Access Token <span style="color:#666;font-weight:normal;">(אופציונלי)</span></label></th>
                            <td>
                                <input type="password" name="instagram_access_token" id="instagram_access_token"
                                       class="regular-text" value="<?php echo esc_attr( $access_token ); ?>">
                                <p class="description">
                                    Access Token מ-Facebook Graph API. יש לוודא שהטוקן כולל הרשאות: instagram_basic, instagram_manage_insights.
                                    <?php if ( ! empty( $app_id ) && ! empty( $app_secret ) ) : ?>
                                    <br><strong>שימו לב:</strong> הטוקן ירענן אוטומטית כאשר הוא פג תוקף.
                                    <?php else : ?>
                                    <br><strong>חשוב:</strong> כדי לאפשר רענון אוטומטי, יש להגדיר גם Facebook App ID ו-App Secret.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="facebook_app_id">Facebook App ID</label></th>
                            <td>
                                <input type="text" name="facebook_app_id" id="facebook_app_id"
                                       class="regular-text" value="<?php echo esc_attr( $app_id ); ?>"
                                       placeholder="1234567890123456">
                                <p class="description">Facebook App ID נדרש לרענון אוטומטי של הטוקן.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="facebook_app_secret">Facebook App Secret</label></th>
                            <td>
                                <input type="password" name="facebook_app_secret" id="facebook_app_secret"
                                       class="regular-text" value="<?php echo esc_attr( $app_secret ); ?>">
                                <p class="description">Facebook App Secret נדרש לרענון אוטומטי של הטוקן.</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="save_instagram_api_settings" class="button button-primary" value="שמור הגדרות">
                    </p>
                </form>
            </div>

            <?php
            $refresh_error = get_option( 'sts_instagram_token_refresh_error', false );
            $api_error     = get_option( 'sts_instagram_api_error', false );

            if ( $refresh_error && is_array( $refresh_error ) ) {
                $error_age = time() - ( isset( $refresh_error['timestamp'] ) ? $refresh_error['timestamp'] : 0 );
                if ( $error_age < 7 * DAY_IN_SECONDS ) {
                    echo '<div class="notice notice-error" style="max-width:800px;margin-top:20px;">';
                    echo '<p><strong>שגיאה ברענון טוקן Instagram:</strong></p>';
                    echo '<p>' . esc_html( $refresh_error['message'] ) . '</p>';
                    echo '<p style="font-size:12px;color:#666;">קוד: ' . esc_html( $refresh_error['code'] ) . ' | זמן: ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $refresh_error['timestamp'] ) . '</p>';
                    echo '</div>';
                } else {
                    delete_option( 'sts_instagram_token_refresh_error' );
                }
            }

            if ( $api_error && is_array( $api_error ) ) {
                $error_age = time() - ( isset( $api_error['timestamp'] ) ? $api_error['timestamp'] : 0 );
                if ( $error_age < 7 * DAY_IN_SECONDS ) {
                    echo '<div class="notice notice-warning" style="max-width:800px;margin-top:20px;">';
                    echo '<p><strong>שגיאה ב-Instagram API:</strong></p>';
                    echo '<p>' . esc_html( $api_error['message'] ) . '</p>';
                    echo '<p style="font-size:12px;color:#666;">קוד: ' . esc_html( $api_error['code'] ) . ' | זמן: ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $api_error['timestamp'] ) . '</p>';
                    echo '</div>';
                } else {
                    delete_option( 'sts_instagram_api_error' );
                }
            }

            if ( ! empty( $access_token ) && ! empty( $app_id ) && ! empty( $app_secret ) ) {
                echo '<div class="card" style="max-width:800px;margin-top:20px;padding:20px;background:#f9f9f9;">';
                echo '<h3>מידע על הטוקן</h3>';

                $token_status = $this->check_token_expiry( $access_token );
                if ( false !== $token_status ) {
                    if ( ! empty( $token_status['expired'] ) ) {
                        echo '<p style="color:#d32f2f;"><strong>הטוקן פג תוקף</strong></p>';
                    } elseif ( empty( $token_status['is_valid'] ) ) {
                        echo '<p style="color:#d32f2f;"><strong>הטוקן לא תקין</strong></p>';
                    } else {
                        echo '<p style="color:#388e3c;"><strong>הטוקן תקין</strong></p>';
                    }
                    if ( ! empty( $token_status['expires_at'] ) && $token_status['expires_at'] > 0 ) {
                        $days = round( ( $token_status['expires_at'] - time() ) / DAY_IN_SECONDS );
                        echo '<p style="font-size:12px;color:#666;">תוקף עד: ' . esc_html( date_i18n( get_option( 'date_format' ), $token_status['expires_at'] ) ) . ' (' . esc_html( $days ) . ' ימים)</p>';
                    } else {
                        echo '<p style="font-size:12px;color:#666;">טוקן לטווח ארוך</p>';
                    }
                } else {
                    echo '<p style="color:#f57c00;"><strong>לא ניתן לבדוק את סטטוס הטוקן</strong></p>';
                }

                if ( ! empty( $business_id ) ) {
                    echo '<p style="margin-top:15px;"><strong>בדיקת חיבור ל-API:</strong></p>';
                    $test_posts = $this->fetch_posts( 1 );
                    if ( false !== $test_posts && is_array( $test_posts ) ) {
                        echo '<p style="color:#388e3c;">החיבור ל-Instagram API עובד תקין (' . count( $test_posts ) . ' פוסטים)</p>';
                    } else {
                        echo '<p style="color:#d32f2f;">לא ניתן להתחבר ל-Instagram API.</p>';
                    }
                }

                echo '</div>';
            }
            ?>

            <?php if ( ! empty( $access_token ) && ! empty( $business_id ) ) : ?>
            <div class="card" style="max-width:800px;margin-top:20px;padding:20px;background:#e8f5e9;">
                <h3>Instagram API מוגדר</h3>
                <p>הגדרות ה-API נשמרו בהצלחה. בלוק ה-Instagram Feed יכול כעת להציג פוסטים.</p>
                <?php if ( empty( $app_id ) || empty( $app_secret ) ) : ?>
                <p style="color:#856404;"><strong>שימו לב:</strong> רענון אוטומטי לא מופעל. הגדירו Facebook App ID ו-App Secret.</p>
                <?php else : ?>
                <p style="color:#155724;"><strong>רענון אוטומטי מופעל.</strong></p>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="card" style="max-width:800px;margin-top:20px;padding:20px;background:#fff3cd;">
                <h3>Instagram API לא מוגדר</h3>
                <p>אנא הגדר את ה-Business ID ו-Access Token.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Credential helpers (options → constants fallback)
    // -------------------------------------------------------------------------

    public function get_access_token() {
        $token = get_option( 'sts_instagram_access_token', '' );
        if ( empty( $token ) && defined( 'STS_INSTAGRAM_ACCESS_TOKEN' ) ) {
            $token = STS_INSTAGRAM_ACCESS_TOKEN;
        }
        return $token;
    }

    public function get_business_id() {
        $id = get_option( 'sts_instagram_business_id', '' );
        if ( empty( $id ) && defined( 'STS_INSTAGRAM_BUSINESS_ID' ) ) {
            $id = STS_INSTAGRAM_BUSINESS_ID;
        }
        return $id;
    }

    public function get_app_id() {
        $id = get_option( 'sts_facebook_app_id', '' );
        if ( empty( $id ) && defined( 'STS_FACEBOOK_APP_ID' ) ) {
            $id = STS_FACEBOOK_APP_ID;
        }
        return $id;
    }

    public function get_app_secret() {
        $secret = get_option( 'sts_facebook_app_secret', '' );
        if ( empty( $secret ) && defined( 'STS_FACEBOOK_APP_SECRET' ) ) {
            $secret = STS_FACEBOOK_APP_SECRET;
        }
        return $secret;
    }

    // -------------------------------------------------------------------------
    // Token management
    // -------------------------------------------------------------------------

    public function check_token_expiry( $token ) {
        $app_id     = $this->get_app_id();
        $app_secret = $this->get_app_secret();

        if ( empty( $app_id ) || empty( $app_secret ) ) {
            return false;
        }

        $cache_key     = 'sts_instagram_token_expiry_' . md5( $token );
        $cached_result = get_transient( $cache_key );
        if ( false !== $cached_result ) {
            return $cached_result;
        }

        $debug_url = sprintf(
            'https://graph.facebook.com/v20.0/debug_token?input_token=%s&access_token=%s|%s',
            urlencode( $token ),
            urlencode( $app_id ),
            urlencode( $app_secret )
        );

        $response = wp_remote_get( $debug_url, array( 'timeout' => 15, 'sslverify' => true ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Instagram Token Debug Error: ' . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['error'] ) ) {
            error_log( 'Instagram Token Debug API Error: ' . ( $data['error']['message'] ?? 'Unknown' ) );
            return false;
        }

        if ( empty( $data['data'] ) ) {
            return false;
        }

        $token_data = $data['data'];
        $expires_at = isset( $token_data['expires_at'] ) ? intval( $token_data['expires_at'] ) : 0;
        $is_valid   = isset( $token_data['is_valid'] ) ? (bool) $token_data['is_valid'] : false;
        $is_expired = ( $expires_at > 0 ) ? ( $expires_at < time() ) : ! $is_valid;

        $result = array(
            'expired'    => $is_expired,
            'expires_at' => $expires_at,
            'is_valid'   => $is_valid,
        );

        set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        return $result;
    }

    public function refresh_token( $current_token ) {
        $app_id     = $this->get_app_id();
        $app_secret = $this->get_app_secret();

        if ( empty( $app_id ) || empty( $app_secret ) ) {
            error_log( 'Instagram Token Refresh: Missing App ID or App Secret' );
            return false;
        }

        $exchange_url = sprintf(
            'https://graph.facebook.com/v20.0/oauth/access_token?grant_type=fb_exchange_token&client_id=%s&client_secret=%s&fb_exchange_token=%s',
            urlencode( $app_id ),
            urlencode( $app_secret ),
            urlencode( $current_token )
        );

        $response = wp_remote_get( $exchange_url, array( 'timeout' => 15, 'sslverify' => true ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Instagram Token Refresh Error: ' . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['error'] ) ) {
            $msg  = $data['error']['message'] ?? 'Unknown error';
            $code = $data['error']['code']    ?? 0;
            error_log( 'Instagram Token Refresh API Error [' . $code . ']: ' . $msg );
            update_option( 'sts_instagram_token_refresh_error', array(
                'message'   => $msg,
                'code'      => $code,
                'timestamp' => time(),
            ) );
            return false;
        }

        if ( empty( $data['access_token'] ) ) {
            error_log( 'Instagram Token Refresh: No access_token in response' );
            return false;
        }

        $new_token = sanitize_text_field( $data['access_token'] );
        update_option( 'sts_instagram_access_token', $new_token );
        delete_transient( 'sts_instagram_token_expiry_' . md5( $current_token ) );
        delete_transient( 'sts_instagram_posts' );
        delete_option( 'sts_instagram_token_refresh_error' );
        error_log( 'Instagram Token Refresh: Success' );
        return $new_token;
    }

    private function get_or_refresh_token() {
        $token = $this->get_access_token();
        if ( empty( $token ) ) {
            return false;
        }

        $status = $this->check_token_expiry( $token );

        if ( false === $status ) {
            return $token;
        }

        if ( ! empty( $status['expired'] ) || empty( $status['is_valid'] ) ) {
            $new_token = $this->refresh_token( $token );
            return ( false !== $new_token ) ? $new_token : $token;
        }

        return $token;
    }

    // -------------------------------------------------------------------------
    // Post fetching
    // -------------------------------------------------------------------------

    public function fetch_posts( $limit = 6 ) {
        $access_token = $this->get_or_refresh_token();
        $business_id  = $this->get_business_id();

        if ( empty( $access_token ) || empty( $business_id ) ) {
            return false;
        }

        $cache_key    = 'sts_instagram_posts_' . md5( $business_id . $limit );
        $cached_posts = get_transient( $cache_key );
        if ( false !== $cached_posts ) {
            return $cached_posts;
        }

        $fields  = 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,like_count,comments_count';
        $api_url = sprintf(
            'https://graph.facebook.com/v20.0/%s/media?fields=%s&limit=%d&access_token=%s',
            urlencode( $business_id ),
            urlencode( $fields ),
            absint( $limit ),
            urlencode( $access_token )
        );

        $response = wp_remote_get( $api_url, array( 'timeout' => 20, 'sslverify' => true ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Instagram API Request Error: ' . $response->get_error_message() );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
            if ( ! empty( $data['error'] ) ) {
                $error_code    = intval( $data['error']['code'] ?? 0 );
                $error_message = $data['error']['message'] ?? 'Unknown error';
                error_log( 'Instagram API Error [' . $error_code . ']: ' . $error_message );

                // Token expired — try refresh once
                if ( $error_code === 190 ) {
                    $new_token = $this->refresh_token( $access_token );
                    if ( false !== $new_token ) {
                        $retry_url = sprintf(
                            'https://graph.facebook.com/v20.0/%s/media?fields=%s&limit=%d&access_token=%s',
                            urlencode( $business_id ),
                            urlencode( $fields ),
                            absint( $limit ),
                            urlencode( $new_token )
                        );
                        $retry_response = wp_remote_get( $retry_url, array( 'timeout' => 20, 'sslverify' => true ) );
                        if ( ! is_wp_error( $retry_response ) ) {
                            $retry_data = json_decode( wp_remote_retrieve_body( $retry_response ), true );
                            if ( ! empty( $retry_data['data'] ) && is_array( $retry_data['data'] ) ) {
                                $data = $retry_data;
                            } else {
                                return false;
                            }
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else {
                    update_option( 'sts_instagram_api_error', array(
                        'message'   => $error_message,
                        'code'      => $error_code,
                        'timestamp' => time(),
                    ) );
                    return false;
                }
            } else {
                return false;
            }
        }

        $posts = array();
        foreach ( $data['data'] as $post ) {
            $post['like_count']     = $post['like_count']     ?? 0;
            $post['comments_count'] = $post['comments_count'] ?? 0;
            $post['caption']        = $post['caption']        ?? '';
            $posts[] = $post;
        }
        $posts = array_slice( $posts, 0, $limit );

        set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
        return $posts;
    }
}
