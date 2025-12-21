# WireNPS Installation Guide

Complete installation instructions for WireNPS module.

## Requirements

- **ProcessWire:** 3.0 or higher
- **PHP:** 8.2 or higher
- **MySQL/MariaDB:** 5.7+ or 10.2+
- **Server:** Apache or Nginx

## Installation Methods

### Method 1: Direct Download (Recommended)

1. **Download** the latest release:
   ```
   https://github.com/mxmsmnv/WireNPS/releases/latest
   ```

2. **Extract** ZIP to `/site/modules/WireNPS/`

3. **Login** to ProcessWire admin

4. **Navigate** to:
   ```
   Modules > Refresh
   ```

5. **Find** "WireNPS - Net Promoter Score"

6. **Click** Install

7. **Configure** the module (see Configuration section below)

### Method 2: Git Clone

```bash
cd /path/to/your/site/site/modules/
git clone https://github.com/mxmsmnv/WireNPS.git
```

Then install via admin interface as above.

### Method 3: Composer (Future)

```bash
composer require mxmsmnv/wirenps
```

*Note: Composer package coming soon*

## Post-Installation Setup

### Step 1: Create AJAX Handler Template

**File:** `/site/templates/wirenps-ajax.php`

Copy this file from the module directory:

```bash
cp /site/modules/WireNPS/wirenps-ajax.php /site/templates/wirenps-ajax.php
```

Or create it manually:

```php
<?php namespace ProcessWire;

// WireNPS AJAX Handler
// This template handles NPS form submissions

// Prevent direct access
if(!defined("PROCESSWIRE")) exit();

// Check if POST request
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST request required']);
    exit;
}

// Get WireNPS module
$wirenps = $modules->get('WireNPS');

if(!$wirenps) {
    echo json_encode(['success' => false, 'error' => 'WireNPS module not found']);
    exit;
}

// Process the submission
$wirenps->processSubmission();
```

### Step 2: Create AJAX Template in Admin

1. **Templates** > **Add New**
   - Name: `wirenps-ajax`
   - Label: "WireNPS AJAX Handler"
   - File: `wirenps-ajax.php`
   
2. **Settings:**
   - Allow page numbers: No
   - Allow URL Segments: No
   - Cache: No
   
3. **Save**

### Step 3: Create AJAX Page

1. **Pages** > **Add New**

2. **Settings:**
   - Title: `WireNPS AJAX Handler`
   - Name: `wirenps-ajax`
   - Template: `wirenps-ajax`
   - Parent: Home (root)
   
3. **Status:**
   - Hidden: ✓ (check this)
   
4. **Save**

### Step 4: Configure Module

1. **Modules** > **Configure** > **WireNPS**

2. **Second Language:**
   - Select: German / French / Chinese
   - Default: German

3. **Behavior Settings:**
   - Show Delay: 5000 ms (5 seconds)
   - Cookie Expiry: 30 days
   - Allow Multiple: Unchecked (users can only submit once)
   - Show to Guests: Unchecked (only logged-in users)

4. **Text & Labels (English):**
   - Question: "How likely would you recommend our service to your friends?"
   - Low Label: "Not at all likely"
   - High Label: "Extremely likely"
   - Feedback Placeholder: "We appreciate your feedback (optional)"
   - Submit Button: "SUBMIT"
   - Thank You: "Thank you for your feedback!"

5. **Second Language Configuration:**
   - Customize texts for selected language
   - Leave empty to use defaults

6. **Score Configuration:**
   - Detractor Max: 6
   - Passive Max: 8
   - (Promoters are automatically 9-10)

7. **Enabled Templates:**
   - Leave empty = all templates
   - Or select specific templates

8. **Save**

## Verification

### Test Installation

1. **Clear cookies** in browser:
   ```javascript
   document.cookie = 'wirenps_submitted=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;'
   ```

2. **Visit** your site homepage

3. **Wait** 5 seconds

4. **Popup should appear** ✓

5. **Select score** and click Submit

6. **Check admin:**
   ```
   Setup > NPS Statistics
   ```

7. **Verify submission** appears in table

### Debug Mode

Open browser console (F12) and check for:

```
[WireNPS-PHP] Config set from PHP: {...}
[WireNPS] Script loaded
[WireNPS] window.wireNPSConfig available: true
[WireNPS] Config loaded: {...}
```

If any errors appear, see Troubleshooting section.

## Configuration Examples

### Example 1: English + German

```
Second Language: German (Deutsch)

English:
- Question: "How likely would you recommend our service?"
- Submit: "SUBMIT"
- Thank You: "Thank you for your feedback!"

German:
- Question: "Wie wahrscheinlich ist es, dass Sie uns weiterempfehlen?"
- Submit: "SENDEN"
- Thank You: "Vielen Dank für Ihr Feedback!"
```

### Example 2: Public Mode (Guest Access)

```
Show to Guests: ✓ (checked)
```

Now both logged-in users and guests can submit ratings.  
Guests will have `user_id = 40` in database.

### Example 3: Multiple Submissions Allowed

```
Allow Multiple Submissions: ✓ (checked)
```

Users can submit multiple ratings.  
Cookie will NOT be set.

### Example 4: Specific Templates Only

```
Enabled Templates:
- basic-page ✓
- blog-post ✓
```

Popup will only appear on these templates.

## Uninstallation

### Remove Module

1. **Modules** > **Uninstall** > WireNPS

2. **Database table preserved** (wirenps_ratings)

3. **Delete files** manually if needed:
   ```bash
   rm -rf /site/modules/WireNPS/
   rm /site/templates/wirenps-ajax.php
   ```

4. **Delete AJAX page** (optional):
   ```
   Pages > wirenps-ajax > Delete
   ```

### Remove Database Table

If you want to completely remove all data:

```sql
DROP TABLE IF EXISTS wirenps_ratings;
```

⚠️ **WARNING:** This deletes all NPS ratings permanently!

## Troubleshooting

### Problem: Popup Not Appearing

**Check:**
- Template is enabled in configuration
- Not in admin area
- User hasn't submitted (check cookie)
- AJAX page exists at `/wirenps-ajax/`

**Test:**
```javascript
console.log(window.wireNPSConfig);
// Should show object with pageId, delay, etc.
```

### Problem: Page ID Not Transmitting

**Check console:**
```javascript
[WireNPS] Page ID from window.wireNPSConfig.pageId: X
```

If showing `undefined` or `1`:
- Update to WireNPS 1.2.0+
- Check that script loads WITHOUT `defer`

### Problem: CSV Export Errors

**Solution:**
- Update to PHP 8.2+
- Update to WireNPS 1.2.0+

### Problem: Allow Multiple Not Working

**Check:**
- "Allow Multiple Submissions" is checked
- Console shows: `[WireNPS] Multiple submissions allowed`
- Clear browser cache

### Problem: Database Error

**Check:**
- MySQL/MariaDB version 5.7+
- PHP PDO extension enabled
- User has database privileges

### Problem: Language Not Switching

**Check:**
1. Second language configured in settings
2. Browser language matches (check Accept-Language header)
3. ProcessWire user language (if logged in)
4. Fallback to English if no match

## Security Notes

### File Permissions

```bash
chmod 755 /site/modules/WireNPS/
chmod 644 /site/modules/WireNPS/*.php
chmod 644 /site/templates/wirenps-ajax.php
```

### Database Security

- Module uses prepared statements (SQL injection safe)
- All input sanitized via ProcessWire sanitizer
- XSS protection on feedback text

### Privacy Compliance

**GDPR:**
- IP collection is optional
- User agent tracking is optional
- Data can be deleted on request
- CSV export for data portability

## Performance Optimization

### Caching

AJAX template should NOT be cached:

```php
// In wirenps-ajax.php template settings:
Cache Time: 0
```

### CDN

Assets are loaded from local module directory.  
No external CDN required.

### Database Indexes

Indexes created automatically on install:
- `session_id` (for duplicate detection)
- `created` (for time-based queries)

## Support

**Issues:** https://github.com/mxmsmnv/WireNPS/issues  
**Email:** maxim@smnv.org

## Next Steps

After installation:
1. [Configuration Guide](CONFIGURATION.md)
2. [Usage Examples](USAGE.md)
3. [API Documentation](API.md)

---

**Installation complete!** 🎉

Visit your site and test the NPS widget.
