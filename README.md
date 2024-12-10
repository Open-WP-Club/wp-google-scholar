# Google Scholar Profile Display

A WordPress plugin that allows you to display your Google Scholar profile information on your website using a simple shortcode.

## Description

This plugin fetches and displays information from Google Scholar profiles, including:

- Profile avatar
- Basic information (name, affiliation)
- Publications list
- Citation metrics

The data can be automatically updated on a schedule or manually refreshed as needed.

## Installation

1. Download the plugin files and upload them to your `/wp-content/plugins/google-scholar-profile` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Scholar Profile to configure the plugin

## Configuration

### Required Settings

- **Profile ID**: Your Google Scholar profile ID (found in your profile URL)
  - Example: If your profile URL is `https://scholar.google.com/citations?user=XXXYYY`, then XXXYYY is your profile ID

### Optional Settings

- **Display Options**:
  - Show/hide avatar
  - Show/hide profile information
  - Show/hide publications list
- **Update Frequency**:
  - Daily
  - Weekly (default)
  - Monthly
  - Yearly

## Usage

### Basic Usage

Add the shortcode to any post or page:

```
[scholar_profile]
```

### Manual Updates

1. Go to Settings > Scholar Profile
2. Click the "Refresh Profile Data Now" button to manually update the profile data

## Styling

The plugin includes basic CSS styles that can be customized through your theme's stylesheet. Main CSS classes:

```css
.scholar-profile {}      /* Main container */
.scholar-avatar {}       /* Profile image container */
.scholar-info {}        /* Profile information section */
.scholar-publications {} /* Publications list section */
```

## Support

For support, feature requests, or bug reports, please visit the plugin's GitHub repository or contact the plugin author.

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```
