# Podcast Voicenote Recorder for Gravity Forms

A Gravity Forms Add-On that adds a **Voice Recorder** field. Visitors record an
audio message in their browser (no plugins, no app) and submit it as part of any
Gravity Forms form. The recording is stored with the entry, so you get all of
Gravity Forms' entry management, notifications, exports, and conditional logic
for free.

## Requirements

* WordPress 5.8+
* PHP 7.2+
* **Gravity Forms 2.5+** (this is an add-on and will not do anything without it)

## Installation

1. Upload the `podcast-voicenote-recorder` folder to `wp-content/plugins/`
   (or install the zipped plugin via **Plugins → Add New → Upload Plugin**).
2. Activate **Podcast Voicenote Recorder for Gravity Forms**.
3. Make sure Gravity Forms is installed and active. If it isn't, an admin
   notice will remind you.

## Usage

### Add the field to a form

1. Edit a form in Gravity Forms.
2. In the form editor, open the **Advanced Fields** group and add the
   **Voice Recorder** field.
3. Configure the label, description, whether it's required, and conditional
   logic as you would any other field.
4. Embed the form on a page as usual (`[gravityform id="1" ...]` or the block).

When a visitor submits, the recording is saved to the form's upload directory
and the entry stores a link to the audio file.

### Review submissions

Submissions appear as normal Gravity Forms entries (**Forms → Entries**):

* The entries list shows a **Listen** link.
* The single entry view embeds an audio player plus a download link.
* Use the `{Voice Recorder:ID}` merge tag in notifications and confirmations to
  include the recording URL.

## Settings

Configure global defaults under **Forms → Settings → Voicenote Recorder**:

| Setting | Default | Description |
| :--- | :--- | :--- |
| Maximum recording length | 300 seconds | Recording auto-stops at this length. |
| Maximum file size | 50 MB | Larger submissions are rejected. |
| Daily per-IP limit | enabled, 5/day | Blocks repeat submissions from the same IP. |
| Background / accent colors | `#082C45` / `#F7D677` | Recorder UI colors. |

> The maximum file size also depends on your server's `upload_max_filesize` and
> `post_max_size` PHP settings — set those at least as high as your limit.

## How it works

* The **Voice Recorder** field (`GF_Field_Voicenote`) renders a self-contained
  recorder UI and a hidden file input.
* `assets/js/recorder.js` uses the `MediaRecorder` API to capture WebM audio,
  then injects the result into the hidden file input via the `DataTransfer`
  API, so the recording submits through Gravity Forms' standard file pipeline.
* On submission the field validates size/type and moves the file into the
  form's upload directory using `GFFormsModel::get_file_upload_path()`, storing
  the URL as the entry value.
* Rate limiting is enforced via the `gform_validation` filter and recorded on
  `gform_after_submission` (per IP, per day).

## File overview

| File | Role |
| :--- | :--- |
| `podcast-voicenote-recorder.php` | Plugin bootstrap; loads the add-on and registers the field on `gform_loaded`. |
| `includes/class-pvr-addon.php` | `GFAddOn` subclass: settings, asset enqueuing, rate limiting. |
| `includes/class-gf-field-voicenote.php` | `GF_Field` subclass: the Voice Recorder field, validation, storage, and display. |
| `assets/js/recorder.js` | Browser recording logic. |
| `assets/css/recorder.css` | Recorder UI styles (no external dependencies). |

## Notes & limitations

* Browser recording requires a modern browser (Chrome, Firefox, Edge, Safari)
  served over **HTTPS** (a `getUserMedia` requirement).
* Best used on single-page forms. Carrying a recorded file across the pages of
  a multi-page form is not yet supported.
* Audio is captured as WebM, which is what the field accepts and stores.
