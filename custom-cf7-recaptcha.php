<?php
/**
 * Plugin Name: Custom CF7 reCAPTCHA
 * Description: Integrates Google reCAPTCHA v2 or v3 with Contact Form 7.
 * Version: 2.1.0
 * Author: Sapthesh V
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for easier access to option names
define( 'CUSTOM_CF7_RECAPTCHA_OPTIONS', 'custom_cf7_recaptcha_options' );

class Custom_CF7_ReCaptcha {

    private $options;

    public function __construct() {
        $this->options = get_option( CUSTOM_CF7_RECAPTCHA_OPTIONS );
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        // Enqueue admin scripts for the settings page
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function init_plugin() {
        if ( ! defined( 'WPCF7_VERSION' ) || empty( $this->options['recaptcha_version'] ) ) {
            return;
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'wpcf7_form_elements', array( $this, 'inject_recaptcha_field' ) );
        add_filter( 'wpcf7_spam', array( $this, 'verify_recaptcha' ), 10, 2 );
    }

    public function enqueue_scripts() {
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
            $version = $this->options['recaptcha_version'];
            $lang    = ! empty( $this->options['language_code'] ) ? '&hl=' . $this->options['language_code'] : '';

            if ( 'v3' === $version && ! empty( $this->options['v3_site_key'] ) ) {
                wp_enqueue_script(
                    'google-recaptcha-v3',
                    'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $this->options['v3_site_key'] ) . $lang,
                    array('jquery'), null, true
                );

                $inline_script = "
                document.addEventListener('wpcf7submit', function(event) {
                    var form = event.target;
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . esc_js( $this->options['v3_site_key'] ) . "', {action: 'contact'}).then(function(token) {
                            var tokenInput = form.querySelector('input[name=\"g-recaptcha-response-v3\"]');
                            if (tokenInput) { tokenInput.value = token; }
                        });
                    });
                }, true);";
                wp_add_inline_script( 'google-recaptcha-v3', $inline_script );

            } elseif ( 'v2' === $version && ! empty( $this->options['v2_site_key'] ) ) {
                wp_enqueue_script(
                    'google-recaptcha-v2',
                    'https://www.google.com/recaptcha/api.js?render=explicit' . $lang,
                    array(), null, true
                );
            }
        }
    }

    public function inject_recaptcha_field( $content ) {
        $version = $this->options['recaptcha_version'];
        if ( 'v3' === $version ) {
            $content .= '<input type="hidden" name="g-recaptcha-response-v3" class="g-recaptcha-response" value="" />';
        } elseif ( 'v2' === $version && ! empty( $this->options['v2_site_key'] ) ) {
            $theme = ! empty( $this->options['v2_theme'] ) ? $this->options['v2_theme'] : 'light';
            $field = '<div class="g-recaptcha" data-sitekey="' . esc_attr( $this->options['v2_site_key'] ) . '" data-theme="' . esc_attr( $theme ) . '"></div><br>';
            $content = preg_replace('/(<input type="submit"[^>]*>)/', $field . '$1', $content);
        }
        return $content;
    }

    public function verify_recaptcha( $spam, $submission ) {
        if ( $spam ) { return $spam; }

        $version = $this->options['recaptcha_version'];
        $secret_key = ('v3' === $version) ? $this->options['v3_secret_key'] : $this->options['v2_secret_key'];
        $token_name = ('v3' === $version) ? 'g-recaptcha-response-v3' : 'g-recaptcha-response';
        $token = isset( $_POST[$token_name] ) ? sanitize_text_field( $_POST[$token_name] ) : '';

        if ( empty( $secret_key ) || empty( $token ) ) { return true; }

        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array( 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] )
        ));

        if ( is_wp_error( $response ) ) { return true; }
        $result = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $result || ! isset( $result->success ) || ! $result->success ) { return true; }
        if ( 'v3' === $version && isset( $result->score ) && $result->score < 0.5 ) { return true; }

        return $spam;
    }

    public function add_admin_menu() {
        add_options_page('Custom CF7 reCAPTCHA', 'Custom CF7 reCAPTCHA', 'manage_options', 'custom_cf7_recaptcha', array( $this, 'options_page' ));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin's settings page
        if ('settings_page_custom_cf7_recaptcha' != $hook) {
            return;
        }
        
        $inline_js = "
        jQuery(document).ready(function($) {
            function toggleRecaptchaFields() {
                var version = $('#recaptcha_version').val();
                
                // The settings API wraps fields in a <tr>. We target the parent <tr>.
                var v2_fields = $('#v2_site_key, #v2_secret_key, #v2_theme').closest('tr');
                var v3_fields = $('#v3_site_key, #v3_secret_key').closest('tr');

                if (version === 'v2') {
                    v2_fields.show();
                    v3_fields.hide();
                } else if (version === 'v3') {
                    v2_fields.hide();
                    v3_fields.show();
                } else {
                    v2_fields.hide();
                    v3_fields.hide();
                }
            }

            // Initial check on page load
            toggleRecaptchaFields();

            // Bind change event
            $('#recaptcha_version').on('change', toggleRecaptchaFields);
        });
        ";
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $inline_js);
    }

    public function settings_init() {
        register_setting( 'customCf7Recaptcha', CUSTOM_CF7_RECAPTCHA_OPTIONS );

        add_settings_section('custom_cf7_recaptcha_section', __('Google reCAPTCHA Settings', 'custom-cf7-recaptcha'), null, 'customCf7Recaptcha');

        add_settings_field('recaptcha_version', __('reCAPTCHA Version', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'select', 'id' => 'recaptcha_version', 'options' => ['' => '-- Select Version --', 'v2' => 'Version 2 ("I\'m not a robot" Checkbox)', 'v3' => 'Version 3 (Invisible Score-Based)']]);
        
        // V2 Fields
        add_settings_field('v2_site_key', __('reCAPTCHA v2 Site Key', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'v2_site_key']);
        add_settings_field('v2_secret_key', __('reCAPTCHA v2 Secret Key', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'v2_secret_key']);
        add_settings_field('v2_theme', __('reCAPTCHA v2 Theme', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'select', 'id' => 'v2_theme', 'options' => ['light' => 'Light', 'dark' => 'Dark']]);

        // V3 Fields
        add_settings_field('v3_site_key', __('reCAPTCHA v3 Site Key', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'v3_site_key']);
        add_settings_field('v3_secret_key', __('reCAPTCHA v3 Secret Key', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'v3_secret_key']);

        // General Field
        add_settings_field('language_code', __('Language Code', 'custom-cf7-recaptcha'), array($this, 'render_field'), 'customCf7Recaptcha', 'custom_cf7_recaptcha_section', ['type' => 'text', 'id' => 'language_code', 'desc' => 'e.g., "en", "es", "fr". Leave blank for auto-detect.']);
    }

    public function render_field( $args ) {
        $id = $args['id'];
        $value = isset( $this->options[$id] ) ? esc_attr( $this->options[$id] ) : '';

        switch ($args['type']) {
            case 'text':
                echo "<input type='text' id='$id' name='" . CUSTOM_CF7_RECAPTCHA_OPTIONS . "[$id]' value='$value' class='regular-text'>";
                break;
            case 'select':
                echo "<select id='$id' name='" . CUSTOM_CF7_RECAPTCHA_OPTIONS . "[$id]'>";
                foreach ($args['options'] as $key => $label) {
                    echo "<option value='$key'" . selected($value, $key, false) . ">$label</option>";
                }
                echo "</select>";
                break;
        }

        if (!empty($args['desc'])) { echo "<p class='description'>" . esc_html($args['desc']) . "</p>"; }
    }

    public function options_page() { ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'customCf7Recaptcha' );
                do_settings_sections( 'customCf7Recaptcha' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
    <?php }
}

new Custom_CF7_ReCaptcha();
