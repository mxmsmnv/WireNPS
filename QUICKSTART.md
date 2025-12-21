# WireNPS Quick Start Guide

Get WireNPS up and running in 5 minutes.

## Prerequisites

- ProcessWire 3.0+ installed
- PHP 8.2+ running
- Admin access to your site

## Installation (3 minutes)

### Step 1: Install Module (1 min)

1. Download WireNPS ZIP
2. Extract to `/site/modules/WireNPS/`
3. **Modules** > **Refresh**
4. Click **Install** next to "WireNPS"

### Step 2: Create AJAX Handler (1 min)

Copy template file:
```bash
cp /site/modules/WireNPS/wirenps-ajax.php /site/templates/wirenps-ajax.php
```

Create template in admin:
- **Templates** > **Add New**
- Name: `wirenps-ajax`
- Save

Create page:
- **Pages** > **Add New**
- Title: "WireNPS AJAX"
- Name: `wirenps-ajax`
- Template: `wirenps-ajax`
- Status: Hidden ✓
- Save

### Step 3: Configure (1 min)

**Modules** > **Configure** > **WireNPS**

Minimum settings:
- Second Language: German (or leave default)
- Show to Guests: ✓ (if you want public access)
- **Save**

Done! ✓

## Test It (2 minutes)

### 1. Clear Cookie

Open browser console (F12):
```javascript
document.cookie = 'wirenps_submitted=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;'
location.reload()
```

### 2. Wait 5 Seconds

Popup should appear on your homepage.

### 3. Submit Rating

- Click a score (0-10)
- Add feedback (optional)
- Click SUBMIT

### 4. Check Admin

**Setup** > **NPS Statistics**

Your submission should appear in the table!

## Basic Configuration

### Public vs Private

**Private Mode** (default):
```
Show to Guests: ❌
```
Only logged-in users see popup.

**Public Mode:**
```
Show to Guests: ✅
```
Everyone sees popup (guests get user_id=40).

### Allow Multiple Submissions

**Single Submission** (default):
```
Allow Multiple Submissions: ❌
```
User can submit only once per cookie expiry period.

**Multiple Submissions:**
```
Allow Multiple Submissions: ✅
```
User can submit unlimited times.

### Popup Timing

```
Show Delay: 5000 ms = 5 seconds
Cookie Expiry: 30 days
```

Popup appears 5 seconds after page load.  
Won't appear again for 30 days (if Allow Multiple is off).

## Multilingual Setup

### German Example

1. **Modules** > **Configure** > **WireNPS**

2. **Second Language:** German (Deutsch)

3. **German Language Section:**
   - Question: "Wie wahrscheinlich empfehlen Sie uns?"
   - Submit: "SENDEN"
   - Thank You: "Vielen Dank!"

4. **Save**

Users with German browser language will see German text.

### Language Priority

1. ProcessWire user language (if logged in)
2. Browser Accept-Language header
3. English (fallback)

## Viewing Results

**Setup** > **NPS Statistics**

Dashboard shows:
- **NPS Score** (calculated automatically)
- **Total Ratings**
- **Promoters / Passives / Detractors**
- **Recent Submissions**
- **Charts** (Score Distribution, 30-Day Trend)

### Export Data

Click **Download CSV** to export all ratings.

## Troubleshooting

### Popup Not Showing?

**Check console (F12):**
```javascript
console.log(window.wireNPSConfig);
```

Should show:
```javascript
{
  delay: 5000,
  pageId: X,
  allowMultiple: true/false,
  ajaxUrl: "/wirenps-ajax/"
}
```

### Already Submitted?

Clear cookie:
```javascript
document.cookie = 'wirenps_submitted=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;'
location.reload()
```

Or enable "Allow Multiple Submissions" in config.

### Page ID Not Saving?

Update to WireNPS 1.2.0+  
(Fixed in latest version)

## Advanced Configuration

### Specific Templates Only

**Modules** > **Configure** > **WireNPS**

**Enabled Templates:**
- Select: `basic-page`, `blog-post`
- Leave empty for all templates

### Custom Texts

**English Section:**
- Customize all text labels
- Question, buttons, messages

**Second Language Section:**
- Customize for German/French/Chinese

### Score Thresholds

**Score Configuration:**
- Detractor Max: 6 (scores 0-6)
- Passive Max: 8 (scores 7-8)
- Promoters: 9-10 (automatic)

## Next Steps

After basic setup:

1. **Customize design** - Edit WireNPS.css
2. **Add analytics** - Export CSV to analyze
3. **Set up languages** - Configure all 4 languages
4. **Read full docs** - See README.md

## Support

**Issues:** https://github.com/mxmsmnv/WireNPS/issues  
**Email:** maxim@smnv.org

---

**That's it!** 🎉

Your NPS widget is now live on your site.

Visit homepage and test it out!
