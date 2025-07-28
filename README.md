# Prompt Manager WordPress Plugin

A comprehensive WordPress plugin for managing, publishing, and protecting AI-generated prompts with advanced NSFW content protection.

## 🔒 **Advanced NSFW Protection System**

This plugin implements a **server-side image protection system** that completely prevents unauthorized access to NSFW images:

### **How It Works:**

1. **Protected Directory**: All blurred images are stored in `/wp-content/uploads/prompt-manager-protected/`
2. **Server-Side Filtering**: All image URLs are intercepted and replaced with protected URLs
3. **Login Verification**: Images are served through WordPress with real-time login checks
4. **Crawler Protection**: Search engines and bots only see blurred versions
5. **Direct Access Blocking**: `.htaccess` rules prevent direct file access

### **Security Features:**

- ✅ **Impossible to bypass** - No CSS manipulation can reveal original images
- ✅ **Crawler-proof** - Google and other bots only index blurred versions
- ✅ **Server-side verification** - All access goes through WordPress authentication
- ✅ **Maximum blur** - 10 iterations with 3% downscaling makes images unrecognizable
- ✅ **Protected storage** - Original images are never directly accessible

## Features

### Custom Post Type

- **Post Type**: `prompt`
- **Slug**: `/prompts/`
- **Supports**: Title, editor, thumbnail, custom fields
- **REST API**: Enabled

### Custom Taxonomies

- **prompt_category** (hierarchical)
- **platform** (hierarchical)
- **model** (hierarchical)
- **prompt_tag** (non-hierarchical)

### Custom Meta Fields

- Prompt Text, Negative Prompt, Sampler, Seed
- CFG Scale, Steps, Clip Skip, Aspect Ratio
- Resolution, Model Hash, Style, Used For
- Free Prompt (checkbox)

### NSFW Protection System

- **Server-side image protection**
- **Protected URL generation**
- **Automatic blur generation**
- **Login-based access control**
- **Crawler protection**

### Shortcodes

**Meta Fields:**

- `[prompt_text]`, `[negative_prompt]`, `[sampler]`, etc.
- `[is_free]`

**Taxonomies:**

- `[prompt_category]`, `[platform]`, `[model]`, `[prompt_tag]`

**Protected Images:**

- `[protected_image attachment_id="123"]`
- `[featured_image]`
- `[nsfw]` - Returns 1 if NSFW

### NSFW Monitor

Admin interface for managing NSFW content:

- View all protected prompts
- Check protection status
- Regenerate protection
- Monitor image counts

## Installation

1. Upload plugin files to `/wp-content/plugins/prompt-manager/`
2. Activate the plugin
3. Configure prompts under "Prompts" menu
4. Mark content as NSFW to enable protection

## File Structure

```
prompt-manager/
├── prompt-manager.php # Main plugin file
├── assets/
│ ├── css/style.css # Plugin styles
│ └── fallback-blur.png # Fallback blur image
├── includes/
│ ├── template-functions.php # Theme integration functions
│ ├── image-protection.php # Image protection system
│ └── image-blocker.php # Direct access blocker
├── logs/
│ └── blur.log # Protection activity log
└── README.md # This file
```

## Block Development

This plugin's Gutenberg blocks are built using `@wordpress/scripts`. To compile the block assets, run:

```bash
npm install
npm run build
```

Use `npm start` during development to automatically rebuild when `src/index.js` changes.

### Available Blocks

- Prompt Display
- Prompt Gallery
- NSFW Warning
- Protected Image
- Prompt Search
- Analytics Summary
- Random Prompt
- Prompt Submission
- Protected Download
- Prompt Slider
- Advance Query - Query prompts with sorting, category, offset and optional NSFW filter


## Usage

### Creating Protected Prompts

1. Create new prompt
2. Add images and content
3. Check "Mark as NSFW" to enable protection
4. All images automatically become protected

### Theme Integration

Use template functions in your theme:

```php
// Display protected featured image
echo prompt_manager_featured_image();

// Show NSFW warning
echo prompt_manager_nsfw_warning();

// Display protected gallery
echo prompt_manager_display_gallery();

// Check if user can view content
if (prompt_manager_can_view_full_content()) {
// Show full content
}
```

### Using Shortcodes

```
[featured_image class="my-class"]
[protected_image attachment_id="123"]
[prompt_text]
[nsfw]
```

## Security Benefits

### **Prevents Demonetization**

- Search engines only index blurred versions
- No way to access original NSFW images without login
- Complete protection from automated crawlers

### **User Experience**

- Seamless experience for logged-in users
- Clear login prompts for visitors
- Fast image serving through WordPress

### **Technical Protection**

- Server-side URL filtering
- Protected file storage
- .htaccess blocking rules
- Real-time authentication checks

## Logging

All protection activities are logged to `/logs/blur.log`:

- Image blur generation
- Protection setup/removal
- Access attempts
- Error conditions

## Support

The plugin provides comprehensive NSFW protection that cannot be bypassed through browser manipulation or direct file access. All images are served through WordPress with proper authentication checks.
