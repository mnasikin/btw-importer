[![Download Plugin](https://img.shields.io/badge/download_plugin-000?style=for-the-badge&logo=download&logoColor=white)](https://github.com/mnasikin/btw-importer/archive/refs/heads/v1.1.0.zip)

# BtW Importer

**BtW Importer** migrates your Blogger/Blogspot content to WordPress with a single click using your `.atom` file.

A powerful yet simple migration tool, BtW Importer helps you seamlessly transfer posts, images, and formatting from Blogger (Blogspot) to WordPress. Whether you're a casual blogger or managing a large archive, this plugin handles the complex parts so you don’t have to.

## ✨ Features

- Scans and downloads embedded images  
- Replaces outdated Blogger URLs with WordPress-friendly links  
- Sets featured images using the first image in each post  
- Displays real-time progress during import  
- Supports image formats: `jpg`, `jpeg`, `png`, `gif`, `webp`, `bmp`, `svg`, `tiff`, `avif`, `ico`. Undownloaded images and videos still embedded, but with external files.

## 📝 Requirements

- PHP `7.4` or later  
- cURL PHP extension  
- `allow_url_fopen` enabled  
- Writable `wp-content/uploads` folder (default configuration meets this)

## 📦 Installation

1. Upload the plugin files to `/wp-content/plugins/btw-importer`, or install via the WordPress plugin screen.  
2. Activate the plugin via **Plugins** in your WordPress dashboard.  
3. Access the **BtW Importer** menu from the dashboard sidebar.

## 📷 Screenshots

![Process Screenshot](https://raw.githubusercontent.com/mnasikin/btw-importer/refs/heads/main/assets/screenshot.png)

## 🚀 Usage

1. Download your `.atom` file:  
   `Blogger → Settings → Back Up → Download → redirects to Google Takeout`
2. Open the **BtW Importer** menu in WordPress  
3. Upload the `.atom` file from your local storage  
4. Click **Start Import**  
5. Monitor the live progress  
6. Done! Your Blogger content is now in WordPress

## 🧾 Changelog

### 1.0.0 - 2025-07-10
- Fix Pages imported as Posts. Should now correctly import pages as WordPress Pages


### 1.0.0 – 2025-07-08
- Initial release  
- Replaced `parse_url()` with `wp_parse_url()`  
- Used `wp_delete_file()` instead of `unlink()`  
- Sanitized input using `wp_unslash()`  
- Sanitized content with `wp_kses_post()`

## 📢 Upgrade Notice

### 1.0.0
Initial release of BtW Importer with Blogger `.atom` file support, media handling, and migration enhancements.

---
