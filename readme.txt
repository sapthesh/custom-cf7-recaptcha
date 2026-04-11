=== Custom CF7 reCAPTCHA ===
Contributors: sapthesh
Tags: contact form 7, recaptcha, spam, security
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates Google reCAPTCHA v2 or v3 directly with Contact Form 7 for spam protection.

== Description ==

This plugin provides a secure and customizable integration of Google reCAPTCHA with Contact Form 7. It allows you to choose between reCAPTCHA v2 ("I'm not a robot" checkbox) and the invisible, score-based reCAPTCHA v3.

You can configure all settings, including site keys, secret keys, language, and theme (for v2), from a dedicated settings page in the WordPress admin area. This eliminates the need for a separate third-party reCAPTCHA plugin.

== Installation ==

1.  Upload `custom-cf7-recaptcha.php` to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Custom CF7 reCAPTCHA**.
4.  Select your reCAPTCHA version (v2 or v3).
5.  Enter your Google reCAPTCHA Site Key and Secret Key.
6.  (Optional) Set a language code and choose a theme for v2.
7.  Save your settings. reCAPTCHA will now be active on all your Contact Form 7 forms.

== Frequently Asked Questions ==

= Do I need another reCAPTCHA plugin? =

No. This plugin is designed to handle the integration by itself. You should disable other reCAPTCHA plugins to avoid conflicts.

= Where do I get my API keys? =

You can get your reCAPTCHA API keys from the [Google reCAPTCHA admin console](https://www.google.com/recaptcha/admin/).

== Changelog ==

= 2.0.0 =
* ADDED: Settings page to choose between reCAPTCHA v2 and v3.
* ADDED: Options for language and theme (for v2).
* ADDED: Support for reCAPTCHA v2 checkbox.
* IMPROVED: Code is now object-oriented and uses the Settings API.

= 1.0.0 =
* Initial release.
* Features reCAPTCHA v3 integration with score-based validation.
