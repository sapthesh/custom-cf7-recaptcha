<?php
/**
 * Plugin Name:       Custom CF7 reCAPTCHA
 * Description:       Integrates Google reCAPTCHA v2 or v3 with Contact Form 7.
 * Version:           2.2.0
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

define( 'CUSTOM_CF7_RECAPTCHA_OPTIONS', 'custom_cf7_recaptcha_options' );

class Custom_CF7_ReCaptcha {

    const VERSION = '2.2.0';
    private $options;

    public function __construct() {
        $this->options = get_option( CUSTOM_CF7_RECAPTCHA_OPTIONS );

        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function init_plugin() {
        // Load plugin text domain for translation
        load_plugin_textdomain( 'custom-cf7-recaptcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        
        if ( ! defined( 'WPCF7_VERSION' ) || empty( $this->options['recaptcha_version'] ) ) {
            return;
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'wpcf7_form_elements', array( $this, 'inject_recaptcha_field' ) );
        add_filter( 'wpcf7_spam', array( $this, 'verify_recaptcha' ), 10, 2 );
    }

    public function enqueue_scripts() {
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
            $version = isset( $this->options['recaptcha_version'] ) ? $this->options['recaptcha_version'] : '';
            $lang    = ! empty( $this->options['language_code'] ) ? '&hl=' . $this->options['language_code'] : '';
            $site_key_v3 = isset( $this->options['v3_site_key'] ) ? $this->options['v3_site_key'] : '';
            $site_key_v2 = isset( $this->options['v2_site_key'] ) ? $this->options['v2_site_key'] : '';

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
    }

    public function inject_recaptcha_field( $content ) {
        $version = isset($this->options['recaptcha_version']) ? $this->options['recaptcha_version'] : '';
        if ( 'v3' === $version ) {
            $content .= '<input type="hidden" name="g-recaptcha-response-v3" class="g-recaptcha-response" value="" />';
        } elseif ( 'v2' === $version && ! empty( $this->options['v2_site_key'] ) ) {
            $theme = ! empty( $this->options['v2_theme'] ) ? $this->options['v2_theme'] : 'light';
            $field = '<div class="g-recaptcha" data-sitekey="' . esc_attr( $this->options['v2_site_key'] ) . '" data-theme="' . esc_attr( $theme ) . '"></div><br>';
            // Place field before the submit button
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

        $version = isset($this->options['recaptcha_version']) ? $this->options['recaptcha_version'] : '';
        $secret_key = ('v3' === $version) ? ($this->options['v3_secret_key'] ?? '') : ($this->options['v2_secret_key'] ?? '');
        $token_name = ('v3' === $version) ? 'g-recaptcha-response-v3' : 'g-recaptcha-response';
        
        // Unslash and sanitize the token from POST data
        $token = isset($_POST[$token_name]) ? sanitize_text_field(wp_unslash($_POST[$token_name])) : '';

        if ( empty( $secret_key ) || empty( $token ) ) { return true; }

        // Sanitize remote IP
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
        add_settings_field('recaptcha_version', __('reCAPTCHA Version', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'select', 'id' => 'recaptcha_version', 'options' => ['' => '-- Select Version --', 'v2' => __('Version 2 ("I\'m not a robot" Checkbox)', 'custom-cf7-recaptcha'), 'v3' => __('Version 3 (Invisible Score-Based)', 'custom-cf7-recaptcha')]]);
        add_settings_field('v2_site_key', __('reCAPTCHA v2 Site Key', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'v2_site_key']);
        add_settings_field('v2_secret_key', __('reCA
