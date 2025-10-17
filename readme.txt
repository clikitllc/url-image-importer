=== URL Image Importer ===
Contributors: bww
Tags: import image, image import, import image to media library, media library
Requires at least: 5.3
Tested up to: 6.7.1
Stable tag: 1.0.6
Requires PHP: 7.4
License: GPLv2 or higher
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import images from URL's directly into your WordPress Media Library to use across your entire site! 

== Description ==

URL Image Importer allows you to effortlessly import images from a URL directly into your Media Library. Simply paste one or multiple image links, and it will handle the rest, importing them all with ease!

The plugin fetches images directly from external links, validates them, and adds them to your Media Library—saving you time and effort. It’s perfect for quickly adding assets to your site without the hassle of downloading files to your computer and manually uploading them to WordPress.


### URL Image Importer Plugin Features

- Import any image directly into your WordPress Media Library from a URL—no file uploads required.
- Works seamlessly with any hosting environment or server setup.
- Automatically validate and save images, ensuring they’re ready to use in your content.
- Get smart recommendations based on available space in your temporary uploads directory
- Works with any server or hosting provider
- Upload any size file directly to a connected Infinite Uploads cloud account
- Uploads directory disk utility for quickly analyzing storage usage in your media library


### Import Images to your Media Library

Paste in a public accessible URL with a compatible file extension and enjoy media management ease

### Bulk Import Support

Allows you to paste multiple URLs at once to import several images simultaneously, without timing out. It processes one at a time, recursively importing them. 

### Uploads Disk Utility

The URL Image Importer plugin includes a media library disk utility that shows a breakdown of the files in your uploads directory by type and size. See how many images, videos, archives, documents, code, and other files (like audio) there are and how much space they're taking up.

### FTP/SFTP Client-free File Uploading

Upload files right to the WordPress media library from URLs without additional credentials and settings. Skip the protocol settings, server names, port numbers, usernames, long passwords, and private keys. Grab the image & paste the URL in!

### Compatible with Big File Uploads

Bypass the upload limits on your server, set by your hosting provider, that prevent you from uploading large files to your media library.


### Wanna make your media library infinitely scalable? Move your big files and uploads directory to the cloud.

Big File Uploads is built to work with [Infinite Uploads](https://wordpress.org/plugins/infinite-uploads/) to make your site's upload directory infinitely scalable. A large WordPress media library can slow down your server and run up the cost of bandwidth and storage with your hosting provider. Move your uploads directory to the Infinite Uploads cloud to save on storage and bandwidth and improve site performance and security. Learn more about [Infinite Uploads cloud storage and content delivery network](https://infiniteuploads.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=URLII_readme&utm_term=promo).


### Privacy

This plugin does not collect or share any data. Site admins can optionally subscribe to email updates which is subject to our [Privacy Policy](https://infiniteuploads.com/privacy/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=bfu_readme&utm_term=privacy).

== Frequently Asked Questions ==

= What file types can be uploaded? =

    •   JPEG/JPG (.jpeg, .jpg)
    •   PNG (.png)
    •   GIF (.gif)
    •   ICO (.ico)
    •   WebP (.webp) (since WordPress 5.8)
    •   SVG (.svg)

= Can videos (mp4) be uploaded =

Not at the moment, support for that is coming soon

= How large of a file can I import =

As large as your maximum upload size is set to, or how ever much your server can support

= Is it compatible with Big File Uploads & Infinite Uploads? =

Yes.

= Is Infinite Uploads required for URL Image Importer to work? =

No. [Infinite Uploads](https://wordpress.org/plugins/infinite-uploads/) is an optional service to offload your media files to the cloud and make your WordPress website storage infinitely scalable. Perfect for sites that need to store many large file uploads.


== Screenshots ==

1. Set maximum upload file size.
2. Customize upload size by user role.
3. Disk utility for analyzing storage usage.
4. Increase upload size for built-in file uploader.

== Installation ==
1.After installing and activating the plugin, navigate to Media > Import Image from URL in the WordPress admin panel.
2.Enter the URL of the image you want to import.
3.Submit the form. If successful, the image is added to the Media Library, and you get a link to edit it.

== Changelog ==

= 1.0.6 - 10/17/2025 =
- Added PSR-4 autoloading with Composer for improved code organization
- Added CSV import functionality for batch image imports
- Added WordPress XML export file import support
- Improved batch import UI - removed individual success messages for cleaner CSV/XML imports
- Added namespace support: UrlImageImporter\Core, \Admin, \FileScan, \Importer, \Ajax, \Utils
- Code quality improvements and bug fixes

= 1.0 - 1/23/2025 =
- Initial release

== About Us ==
Infinite Uploads builds WordPress plugins and is a premium cloud storage provider and content delivery network (CDN) for all your WordPress media files. Learn more here:
[infiniteuploads.com](https://infiniteuploads.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=bfu_readme&utm_term=about_us)

Learn how to manage large files on our blog:
[Infinite Uploads Blog, Tips, Tricks, How-tos, and News](https://infiniteuploads.com/blog/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=URLII_readme&utm_term=blog)

Enjoy!

== Contact and Credits ==

Maintained by the cloud architects and WordPress engineers at [Infinite Uploads](https://infiniteuploads.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=URLII_readme&utm_term=credits).
