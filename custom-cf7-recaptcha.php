<?php
/**
 * Plugin Name:       Custom CF7 reCAPTCHA
 * Description:       Integrates Google reCAPTCHA v2 or v3 with Contact Form 7.
 * Version:           2.2.1
 * Author:            Sapthesh V
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-cf7-recaptcha
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure the plugin class is not already defined to prevent fatal errors.
if ( ! class_exists( 'Custom_CF7_ReCaptcha' ) ) {

    define( 'CUSTOM_CF7_RECAPTCHA_OPTIONS', 'custom_cf7_recaptcha_options' );

    class Custom_CF7_ReCaptcha {

        const VERSION = '2.2.1';
        private static $instance;
        private $options;

        public static function get_instance() {
            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->options = get_option( CUSTOM_CF7_RECAPTCHA_OPTIONS, array() );

            add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
            add_action( 'admin_init', array( $this, 'settings_init' ) );
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }

        public function init_plugin() {
            load_plugin_textdomain( 'custom-cf7-recaptcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

            if ( ! defined( 'WPCF7_VERSION' ) || empty( $this->options['recaptcha_version'] ) ) {
                return;
            }

            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_filter( 'wpcf7_form_elements', array( $this, 'inject_recaptcha_field' ) );
            add_filter( 'wpcf7_spam', array( $this, 'verify_recaptcha' ), 10, 2 );
        }

        public function enqueue_scripts() {
            $version = $this->options['recaptcha_version'] ?? '';
            $lang    = ! empty( $this->options['language_code'] ) ? '&hl=' . $this->options['language_code'] : '';
            $site_key_v3 = $this->options['v3_site_key'] ?? '';
            $site_key_v2 = $this->options['v2_site_key'] ?? '';

            if ( 'v3' === $version && ! empty( $site_key_v3 ) ) {
                wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key_v3) . $lang, array('jquery'), self::VERSION, true);
                $inline_script = "
                document.addEventListener('wpcf7submit', function(event) {
                    var form = event.target;
                    if (typeof grecaptcha === 'undefined') return;
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . esc_js($site_key_v3) . "', {action: 'contact'}).then(function(token) {
                            var tokenInput = form.querySelector('input[name=\"g-recaptcha-response-v3\"]');
                            if (tokenInput) { tokenInput.value = token; }
                        });
                    });
                }, true);";
                wp_add_inline_script( 'google-recaptcha-v3', $inline_script );
            } elseif ( 'v2' === $version && ! empty( $site_key_v2 ) ) {
                wp_enqueue_script('google-recaptcha-v2', 'https://www.google.com/recaptcha/api.js?render=explicit' . $lang, array(), self::VERSION, true);
            }
        }

        public function inject_recaptcha_field( $content ) {
            $version = $this->options['recaptcha_version'] ?? '';
            if ( 'v3' === $version ) {
                $content .= '<input type="hidden" name="g-recaptcha-response-v3" class="g-recaptcha-response" value="" />';
            } elseif ( 'v2' === $version && ! empty( $this->options['v2_site_key'] ) ) {
                $theme = $this->options['v2_theme'] ?? 'light';
                $field = '<div class="g-recaptcha" data-sitekey="' . esc_attr( $this->options['v2_site_key'] ) . '" data-theme="' . esc_attr( $theme ) . '"></div><br>';
                if ( preg_match( '/(<input[^>]*type="submit"[^>]*>)/', $content, $matches ) ) {
                    $content = str_replace( $matches[0], $field . $matches[0], $content );
                } else {
                    $content = preg_replace( '/(<\/form>)/', $field . '$1', $content );
                }
            }
            return $content;
        }

        public function verify_recaptcha( $spam, $submission ) {
            if ( $spam || empty( $_POST ) ) { return $spam; }

            $version = $this->options['recaptcha_version'] ?? '';
            $secret_key = ('v3' === $version) ? ($this->options['v3_secret_key'] ?? '') : ($this->options['v2_secret_key'] ?? '');
            $token_name = ('v3' === $version) ? 'g-recaptcha-response-v3' : 'g-recaptcha-response';
            $token = isset($_POST[$token_name]) ? sanitize_text_field(wp_unslash($_POST[$token_name])) : '';

            if ( empty( $secret_key ) || empty( $token ) ) { return true; }

            $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array( 'secret' => $secret_key, 'response' => $token, 'remoteip' => $remote_ip )
            ));

            if ( is_wp_error( $response ) ) { return true; }
            $result = json_decode( wp_remote_retrieve_body( $response ) );
            if ( ! $result || ! isset( $result->success ) || ! $result->success ) { return true; }
            if ( 'v3' === $version && isset( $result->score ) && $result->score < 0.5 ) { return true; }

            return $spam;
        }

        public function add_admin_menu() {
            add_options_page(__('Custom CF7 reCAPTCHA', 'custom-cf7-recaptcha'), __('Custom CF7 reCAPTCHA', 'custom-cf7-recaptcha'), 'manage_options', 'custom_cf7_recaptcha', array( $this, 'options_page' ));
        }

        public function enqueue_admin_scripts($hook) {
            if ('settings_page_custom_cf7_recaptcha' != $hook) { return; }
            
            $inline_js = "
            jQuery(document).ready(function($) {
                function toggleRecaptchaFields() {
                    var version = $('#recaptcha_version').val();
                    var v2_fields = $('#v2_site_key, #v2_secret_key, #v2_theme').closest('tr');
                    var v3_fields = $('#v3_site_key, #v3_secret_key').closest('tr');
                    if (version === 'v2') { v2_fields.show(); v3_fields.hide(); } 
                    else if (version === 'v3') { v2_fields.hide(); v3_fields.show(); } 
                    else { v2_fields.hide(); v3_fields.hide(); }
                }
                toggleRecaptchaFields();
                $('#recaptcha_version').on('change', toggleRecaptchaFields);
            });";
            wp_enqueue_script('jquery');
            wp_add_inline_script('jquery', $inline_js);
        }
        
        public function settings_init() {
            register_setting( 'customCf7Recaptcha', CUSTOM_CF7_RECAPTCHA_OPTIONS, array('sanitize_callback' => array($this, 'sanitize_options')) );
            add_settings_section('custom_cf7_recaptcha_section', __('Google reCAPTCHA Settings', 'custom-cf7-recaptcha'), null, 'customCf7Recaptcha');
            
            // Register fields
            $fields = [
                'recaptcha_version' => ['label' => __('reCAPTCHA Version', 'custom-cf7-recaptcha'), 'type' => 'select', 'options' => ['' => '-- Select Version --', 'v2' => __('Version 2 ("I\'m not a robot" Checkbox)', 'custom-cf7-recaptcha'), 'v3' => __('Version 3 (Invisible Score-Based)', 'custom-cf7-recaptcha')]],
                'v2_site_key'       => ['label' => __('reCAPTCHA v2 Site Key', 'custom-cf7-recaptcha'), 'type' => 'text'],
                'v2_secret_key'     => ['label' => __('reCAPTCHA v2 Secret Key', 'custom-cf7-recaptcha'), 'type' => 'text'],
                'v2_theme'          => ['label' => __('reCAPTCHA v2 Theme', 'custom-cf7-recaptcha'), 'type' => 'select', 'options' => ['light' => __('Light', 'custom-cf7-recaptcha'), 'dark' => __('Dark', 'custom-cf7-recaptcha')]],
                'v3_site_key'       => ['label' => __('reCAPTCHA v3 Site Key', 'custom-cf7-recaptcha'), 'type' => 'text'],
                'v3_secret_key'     => ['label' => __('reCAPTCHA v3 Secret Key', 'custom-cf7-recaptcha'), 'type' => 'text'],
                'language_code'     => ['label' => __('Language Code', 'custom-cf7-recaptcha'), 'type' => 'text', 'desc' => __('e.g., "en", "es", "fr". Leave blank for auto-detect.', 'custom-cf7-recaptcha')]
            ];

            foreach ($fields as $id => $field) {
                add_settings_field($id, $field['label'], array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', array_merge($field, ['id' => $id]));
            }
        }

        public function sanitize_options($input) {
            $sanitized_input = array();
            $schema = [
                'recaptcha_version' => ['type' => 'in_array', 'options' => ['v2', 'v3'], 'default' => ''],
                'v2_site_key'       => ['type' => 'text'],
                'v2_secret_key'     => ['type' => 'text'],
                'v2_theme'          => ['type' => 'in_array', 'options' => ['light', 'dark'], 'default' => 'light'],
                'v3_site_key'       => ['type' => 'text'],
                'v3_secret_key'     => ['type' => 'text'],
                'language_code'     => ['type' => 'text']
            ];

            foreach ($schema as $key => $props) {
                if (!isset($input[$key])) continue;
                if ($props['type'] === 'text') {
                    $sanitized_input[$key] = sanitize_text_field($input[$key]);
                } elseif ($props['type'] === 'in_array') {
                    $sanitized_input[$key] = in_array($input[$key], $props['options']) ? $input[$key] : $props['default'];
                }
            }
            return $sanitized_input;
        }

        public function render_field( $args ) {
            $id    = $args['id'];
            $value = $this->options[$id] ?? '';

            switch ($args['type']) {
                case 'text':
                    printf('<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">', esc_attr($id), esc_attr(CUSTOM_CF7_RECAPTCHA_OPTIONS), esc_attr($value));
                    break;
                case 'select':
                    printf('<select id="%1$s" name="%2$s[%1$s]">', esc_attr($id), esc_attr(CUSTOM_CF7_RECAPTCHA_OPTIONS));
                    foreach ($args['options'] as $key => $label) {
                        printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($value, $key, false), esc_html($label));
                    }
                    echo "</select>";
                    break;
            }
            if (!empty($args['desc'])) { printf('<p class="description">%s</p>', esc_html($args['desc'])); }
        }

        public function options_page() { ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form action="options.php" method="post">
                    <?php settings_fields( 'customCf7Recaptcha' ); ?>
                    <?php do_settings_sections( 'customCf7Recaptcha' ); ?>
                    <?php submit_button( 'Save Settings' ); ?>
                </form>
            </div>
        <?php }
    }

    // Initialize the plugin using the singleton pattern.
    Custom_CF7_ReCaptcha::get_instance();
}
