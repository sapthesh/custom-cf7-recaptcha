# Custom CF7 reCAPTCHA 

Integrates Google reCAPTCHA v2 or v3 with Contact Form 7, replacing the need for third-party reCAPTCHA plugins. 

<a href="https://hits.sh/github.com/sapthesh/custom-cf7-recaptcha/"><img alt="Hits" src="https://hits.sh/github.com/sapthesh/custom-cf7-recaptcha.svg?view=today-total&style=for-the-badge&color=fe7d37"/></a>

## Description

This plugin provides a secure and customizable integration of Google reCAPTCHA with Contact Form 7. It allows you to choose between reCAPTCHA v2 ("I'm not a robot" checkbox) and the invisible, score-based reCAPTCHA v3.

You can configure all settings, including site keys, secret keys, language, and theme (for v2), from a dedicated settings page in the WordPress admin area.

## Features

-   **Dual Version Support:** Choose between reCAPTCHA v2 and v3.
-   **Customizable Keys:** Easily enter your site and secret keys.
-   **Language Support:** Set a specific language for the reCAPTCHA widget.
-   **Theme Selection:** Choose between a light or dark theme for the v2 checkbox.
-   **Secure Backend Verification:** All form submissions are verified on the server-side against Google's API.
-   **Score-Based Protection (v3):** Submissions with a score below the threshold are marked as spam.

## Installation

1.  Download the `custom-cf7-recaptcha.php` file.
2.  Upload the file to your `/wp-content/plugins/` directory, or upload the zip file through the WordPress plugins screen.
3.  Activate the plugin through the 'Plugins' screen in WordPress.
4.  Navigate to **Settings > Custom CF7 reCAPTCHA**.
5.  Select your desired reCAPTCHA version and enter your Site Key and Secret Key.
6.  Configure the language and theme options as needed.
7.  Save your settings. The reCAPTCHA will now appear on your Contact Form 7 forms.

## Requirements

-   WordPress 5.0 or higher
-   Contact Form 7 plugin installed and activated
-   A Google reCAPTCHA account with API keys
