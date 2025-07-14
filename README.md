# Google Scholar Profile Display

A WordPress plugin that allows you to display your Google Scholar profile information on your website using a simple shortcode.

## Description

This plugin fetches and displays information from Google Scholar profiles, including:

- Profile avatar
- Basic information (name, affiliation)
- Publications list with pagination
- Citation metrics
- Interactive sorting and filtering

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
  - Show/hide co-authors
- **Update Frequency**:
  - Daily
  - Weekly (default)
  - Monthly
  - Yearly
- **Max Publications**: Control how many publications to fetch (50, 100, 200, 500)

## Usage

### Basic Usage

Add the shortcode to any post or page:

```
[scholar_profile]
```

### Pagination Options

Control how many publications are displayed per page:

```
[scholar_profile per_page="10"]
[scholar_profile per_page="20"]
[scholar_profile per_page="50"]
```

**Default**: 20 publications per page
**Range**: 1-100 publications per page

### Sorting Options

You can sort publications by specifying sorting parameters:

```
[scholar_profile sort_by="year" sort_order="desc"]
[scholar_profile sort_by="citations" sort_order="desc"]
[scholar_profile sort_by="title" sort_order="asc"]
```

**Available sort_by values:**

- `year` - Sort by publication year
- `citations` - Sort by citation count
- `title` - Sort alphabetically by title

**Available sort_order values:**

- `desc` - Descending order (default)
- `asc` - Ascending order

### Combined Parameters

You can combine pagination and sorting options:

```
[scholar_profile per_page="15" sort_by="citations" sort_order="desc"]
```

### Interactive Features

**Sorting:**

- Click any column header to sort by that field
- Click again to reverse the sort order
- Visual indicators (arrows) show the current sort direction
- Fully accessible with keyboard navigation (Tab + Enter/Space)

**Pagination:**

- Navigate through pages using Previous/Next buttons
- Jump to specific pages using page numbers
- URL parameters are updated for bookmarkable pages
- Responsive design adapts to mobile devices

**URL Parameters:**

- `scholar_page` - Current page number
- `scholar_sort_by` - Current sort field
- `scholar_sort_order` - Current sort order

### Manual Updates

1. Go to Settings > Scholar Profile
2. Click the "Refresh Profile Data" button to manually update the profile data

## Advanced Features

### Performance Considerations

- **Pagination**: Large publication lists are automatically paginated for better performance
- **Client-side Sorting**: Sorting within the current page happens instantly
- **Server-side Sorting**: Full dataset sorting requires a page refresh
- **Responsive Design**: Optimized for desktop, tablet, and mobile viewing

### Accessibility

- Full keyboard navigation support
- ARIA labels for screen readers
- Semantic HTML structure
- High contrast design
- Focus indicators for interactive elements

## Styling

The plugin includes comprehensive CSS styles that can be customized through your theme's stylesheet. Main CSS classes:

```css
.scholar-profile {}              /* Main container */
.scholar-header {}               /* Profile header section */
.scholar-avatar {}               /* Profile image container */
.scholar-basic-info {}           /* Profile information section */
.scholar-publications {}         /* Publications section */
.scholar-publications-table {}   /* Publications table */
.scholar-pagination {}           /* Pagination navigation */
.scholar-pagination-wrapper {}   /* Pagination container */
.scholar-pagination-number {}    /* Individual page numbers */
.scholar-pagination-btn {}       /* Previous/Next buttons */
.scholar-metrics-box {}          /* Citation metrics */
.scholar-coauthors {}            /* Co-authors section */
```

### CSS Custom Properties

The plugin uses CSS custom properties for easy theming:

```css
:root {
  --scholar-primary-color: #1a73e8;
  --scholar-primary-hover: #1557b0;
  --scholar-border-color: #dadce0;
  --scholar-text-color: #202124;
  --scholar-text-secondary: #666;
  --scholar-background-light: #f8f9fa;
}
```

## Performance Tips

1. **Pagination**: Use smaller `per_page` values (10-20) for better initial load times
2. **Update Frequency**: Use weekly or monthly updates for large profiles
3. **Publication Limits**: Consider limiting max publications in settings for very large profiles
4. **Caching**:
