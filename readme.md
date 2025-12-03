Podcast Voicenote Recorder Plugin

This is a custom WordPress plugin designed to allow podcast listeners to record and submit voice messages directly through a frontend interface. Files are securely saved to your WordPress uploads directory, and an email notification is sent upon every successful submission.

Features

Custom Branding: Uses your preferred color palette (#082C45, #F7D677, #F7F4EB).

Max Recording Duration: Enforces a 5-minute (300 seconds) limit on all frontend recordings.

Admin Dashboard: Provides a dedicated "Voicenote Submissions" page to review, play, download, and delete submitted audio files.

Email Notification: Sends an email to the site administrator (admin_email) upon successful file upload.

Rate Limiting: Securely limits submissions to 5 per day per IP address, using the WordPress Options API.

Installation

Create Plugin Folder: In your local WordPress development environment, create a new folder named podcast-voicenote-recorder inside wp-content/plugins/.

Add Files: Place the two required PHP files inside this folder:

podcast-voicenote-recorder.php (Main plugin file and frontend logic)

voicenotes-upload-handler.php (Backend processing, file saving, rate limiting, and email)

Zip and Upload (Optional): Compress the podcast-voicenote-recorder folder into a .zip file. You can then upload and install this zip file directly via the WordPress admin dashboard (Plugins > Add New > Upload Plugin).

Activate: Activate the Podcast Voicenote Recorder plugin.

Usage

1. Embedding the Recorder

To display the voice recorder on any page or post, simply use the following shortcode in a Classic Editor or Shortcode Block:

[podcast_voicenote_recorder]

2. Managing Submissions

After activation, a new menu item will appear in your WordPress dashboard sidebar:

Go to Dashboard Menu > Voicenotes.

On this page, you can:

View the Submission Date (corrected to your site's timezone).

Listen to the audio file directly in the browser.

Download the .webm file.

Delete the file from the server.

Configuration Details

File Storage Location

All successfully uploaded files are saved to the following secure location:

wp-content/uploads/podcast_voicenotes/

Rate Limiting

The rate limiting is hardcoded as follows:

Limit: 5 submissions per day.

Tracking: Tracks by the user's IP address.

Storage: The submission counts are stored securely in your database using the WordPress options table (pvr_rate_limit_submissions key).

If a user hits the limit, the submission will be blocked, and the frontend will display an error message.

Troubleshooting: Server Configuration

If you experience "400 Bad Request" or file size errors when submitting a recording, you must check your server's PHP configuration:

upload_max_filesize

post_max_size

These values must be set higher than the maximum expected file size (e.g., set both to 32M).

Technical File Overview

File

Role

Key Functions

podcast-voicenote-recorder.php

Main Plugin / Frontend

Shortcode rendering, UI, JavaScript logic (Max Duration), Admin Page (pvr_voicenote_page, pvr_handle_delete_voicenote).

voicenotes-upload-handler.php

Backend Endpoint

File saving, Rate Limit Check/Update, Email notification (wp_mail).