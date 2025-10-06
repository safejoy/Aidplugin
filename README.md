# Safe Joy Dynamic Submissions

A WordPress plugin for **Safe Joy Org** that lets you create custom submission forms via shortcodes.  
Users can enter their **name, description, and multiple helpful links** through a popup form.  
Submissions are saved in the WordPress admin dashboard, organized by form.  

---

## Features
- Create custom **forms** in the WordPress admin (`Safe Joy Forms`).
- Each form generates a **shortcode** you can paste into posts or pages.
- Shortcode usage:  
  ```text
  [safejoy form="myform"]

    Frontend: Renders a Submit Info button. Clicking it opens a modal form.

    Fields:

        Name

        Description

        Multiple links (users can add as many as they want).

    Admin dashboard:

        View all submissions under Safe Joy Submissions.

        Submissions show which form they belong to.

        Links are saved and displayed as clickable URLs.

## Installation

    Download or clone this repository.

    Place the plugin folder into:

    wp-content/plugins/safejoy-dynamic-submissions

    Activate Safe Joy Dynamic Submissions from the WordPress Plugins menu.

## Usage
1. Create a Form

    Go to Safe Joy Forms in the WordPress dashboard.

    Click Add New.

    Enter a title (e.g., donations, support, volunteers).
    This title becomes the shortcode key.

2. Insert the Shortcode

    Edit any page or post.

    Paste the shortcode:

    [safejoy form="donations"]

    Replace "donations" with your form title.

3. Collect Submissions

    When users click the button and submit:

        Data is saved under Safe Joy Submissions in the dashboard.

        You’ll see name, description, links, and which form the submission came from.

## File Structure

safejoy-dynamic-submissions/
├── safejoy-dynamic-submissions.php   # Main plugin file
├── script.js                         # Frontend JS (modal + AJAX)
├── style.css                         # Modal + button styling
└── README.md                         # This documentation

## Shortcode Examples

[safejoy form="donations"]
[safejoy form="volunteers"]
[safejoy form="partners"]

Each shortcode corresponds to a Safe Joy Form you created in the dashboard.
## Security

    All user input is sanitized before saving.

    Links are cleaned with esc_url_raw().

    Works with both logged-in and guest users.

## License

This plugin is open-source under the MIT License
.
Created with ❤️ for the Safe Joy Org.
