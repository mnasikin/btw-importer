[![Download Plugin](https://img.shields.io/badge/download_plugin-000?style=for-the-badge&logo=download&logoColor=white)](https://github.com/mnasikin/btw-importer/releases)

[![Changelog](https://img.shields.io/badge/changelog-fff?style=for-the-badge&logo=download&logoColor=black)](https://github.com/mnasikin/btw-importer/blob/main/changelog.md)  

# BtW Importer

**BtW Importer** migrates your Blogger/Blogspot content to WordPress with a single click using your `.atom` file.

A powerful yet simple migration tool, BtW Importer helps you seamlessly transfer posts, images, and formatting from Blogger (Blogspot) to WordPress. Whether you're a casual blogger or managing a large archive, this plugin handles the complex parts so you don’t have to.

## ⚔️ Note
Make sure to check your content after you import contents. Also, this plugin doesn't overwrite current post or pages, so if you've imported posts or pages and want to import again, kindly delete the previous imported posts, pages, and images.

*For some reason, Nginx-based server are slower when importing compared to Apache or Litespeed. If you're using Nginx, it's recommended to install in local wordpress, then upload the wordpress to hosting.*


## ✨ Features

- Scans and downloads embedded images  
- Replaces outdated Blogger URLs with WordPress-friendly links  
- Sets featured images using the first image in each post  
- Displays real-time progress during import  
- Supports image formats: `jpg`, `jpeg`, `png`, `gif`, `webp`, `bmp`, `svg`, `tiff`, `avif`, `ico`. Undownloaded images and videos still embedded, but with external files.
- Import content based on post type
- Keep external embedded content
- Posts or Pages date sync as date in the .atom file (eg. your blogspot post published on 2022/02/02, then the post in wordpress also 2022/02/02)
- Categories added or use existing category based on .atom file
- Only blogspot/google images downloaded, others external (saving your hosting storage, especially if you use external CDN)
- Only download originial size images (avoid duplicated)
- Automatically add 301 redirect from blogspot permalink to new wordpress URL to keep your SEO (only for post with `/YYYY/MM/slug.html` format)
- Redirect log page to check list of redirection has beed made, also option to clear redirection logs

## 📝 Requirements

- PHP `8.1` or later  
- `cURL` PHP extension  
- `allow_url_fopen` enabled 
- `SimpleXML`/`XML` PHP Extension 
- Writable `wp-content/uploads` folder (default configuration meets this)

## 📦 Installation

1. Upload the plugin files to `/wp-content/plugins/btw-importer`, or install via the WordPress plugin screen.  
2. Activate the plugin via **Plugins** in your WordPress dashboard.  
3. Access the **BtW Importer** menu from the dashboard sidebar.

## 📷 Screenshots
1. Importer Page
![Importer Page](https://ik.imagekit.io/vbsmdqxuemd/btw-importer/v4.0.0/screenshot-1.png)
2. Import Process
![Import Process](https://ik.imagekit.io/vbsmdqxuemd/btw-importer/v4.0.0/screenshot-2.png)
3. Done Importing
![Done Importing](https://ik.imagekit.io/vbsmdqxuemd/btw-importer/v4.0.0/screenshot-3.png)
4. Redirect Log
![Redirect Log](https://ik.imagekit.io/vbsmdqxuemd/btw-importer/v4.0.0/screenshot-4.png)


## 🚀 Usage

1. Download your `.atom` file:  
   Blogger → Settings → Back Up → Download → redirects to Google Takeout  
2. Open the BtW Importer menu in WordPress  
3. Read and check the agreement
3. Upload the `.atom` file from your local storage  
4. Extract the atom file in second step
5. Start the migration  
5. Monitor the live progress  
6. Done! Your Blogger content is now in WordPress

## 🧾 Changelog

The Changelog moved to dedicated [![Changelog](https://img.shields.io/badge/changelog-fff?style=for-the-badge&logo=download&logoColor=black)](https://github.com/mnasikin/btw-importer/blob/main/changelog.md)  
