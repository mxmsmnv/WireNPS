# WireNPS Hooks & Customisation Examples

This file contains practical examples of how to extend and customise WireNPS using ProcessWire's hook system.

## Available Hooks

WireNPS provides several hookable methods for extending functionality:

- `WireNPS::saveRating` - After a rating is saved
- `WireNPS::addNPSWidget` - Modify widget HTML before display
- `WireNPS::processSubmission` - Custom submission processing
- `WireNPS::getWidgetHTML` - Modify widget markup
- `WireNPS::handleAjaxSubmit` - Custom AJAX handling

## Hook Examples

### 1. Email Notification on Low Scores

Send email alerts when detractors (score 0-6) submit ratings:

```php
// In /site/ready.php or custom module

$wire->addHookAfter('WireNPS::saveRating', function($event) {
    $rating = $event->arguments(0);
    
    // Check if detractor score
    if($rating['score'] <= 6) {
        $mail = wireMail();
        $mail->to('support@yoursite.com');
        $mail->from('noreply@yoursite.com');
        $mail->subject('Low NPS Score Alert - Action Required');
        
        $page = wire('pages')->get($rating['page_id']);
        $user = wire('users')->get($rating['user_id']);
        
        $body = "Low NPS Score Received:\n\n";
        $body .= "Score: {$rating['score']}/10\n";
        $body .= "Feedback: {$rating['feedback']}\n";
        $body .= "Page: {$page->url}\n";
        $body .= "User: " . ($user->id ? $user->name : 'Guest') . "\n";
        $body .= "Time: " . date('Y-m-d H:i:s', $rating['created']) . "\n";
        
        $mail->body($body);
        $mail->send();
    }
});
```

### 2. Slack Integration

Post NPS ratings to Slack channel:

```php
$wire->addHookAfter('WireNPS::saveRating', function($event) {
    $rating = $event->arguments(0);
    
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    // Determine colour based on score
    if($rating['score'] >= 9) {
        $color = 'good'; // Green
        $emoji = ':grinning:';
    } elseif($rating['score'] >= 7) {
        $color = 'warning'; // Yellow
        $emoji = ':neutral_face:';
    } else {
        $color = 'danger'; // Red
        $emoji = ':disappointed:';
    }
    
    $page = wire('pages')->get($rating['page_id']);
    
    $payload = [
        'text' => "{$emoji} New NPS Rating: {$rating['score']}/10",
        'attachments' => [[
            'color' => $color,
            'fields' => [
                [
                    'title' => 'Feedback',
                    'value' => $rating['feedback'] ?: '_No feedback provided_',
                    'short' => false
                ],
                [
                    'title' => 'Page',
                    'value' => $page->title,
                    'short' => true
                ],
                [
                    'title' => 'Score',
                    'value' => "{$rating['score']}/10",
                    'short' => true
                ]
            ]
        ]]
    ];
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
});
```

### 3. Store Additional Custom Data

Add custom fields to rating data:

```php
$wire->addHookBefore('WireNPS::saveRating', function($event) {
    $data = $event->arguments(0);
    
    // Add referrer URL
    if(isset($_SERVER['HTTP_REFERER'])) {
        $data['referrer'] = $_SERVER['HTTP_REFERER'];
    }
    
    // Add device type
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if(preg_match('/mobile/i', $userAgent)) {
        $data['device'] = 'mobile';
    } elseif(preg_match('/tablet/i', $userAgent)) {
        $data['device'] = 'tablet';
    } else {
        $data['device'] = 'desktop';
    }
    
    // Add browser language
    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $data['language'] = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    }
    
    $event->arguments(0, $data);
});

// You'll need to modify the database table to include these columns
```

### 4. Conditional Display Logic

Show NPS widget only for specific conditions:

```php
$wire->addHookBefore('WireNPS::addNPSWidget', function($event) {
    $page = $event->object;
    
    // Don't show on checkout pages
    if($page->template == 'checkout') {
        $event->replace = true;
        return;
    }
    
    // Only show to users who've been logged in > 7 days
    if(wire('user')->isLoggedin()) {
        $created = wire('user')->created;
        $daysSinceRegistration = (time() - $created) / 86400;
        
        if($daysSinceRegistration < 7) {
            $event->replace = true;
            return;
        }
    }
    
    // Only show during business hours (9 AM - 5 PM)
    $hour = (int)date('G');
    if($hour < 9 || $hour > 17) {
        $event->replace = true;
        return;
    }
});
```

### 5. Custom Widget Text Based on User

Personalise the NPS question based on user data:

```php
$wire->addHookAfter('WireNPS::getWidgetHTML', function($event) {
    $html = $event->return;
    $user = wire('user');
    
    if($user->isLoggedin()) {
        $name = $user->get('first_name') ?: $user->name;
        $customQuestion = "Hi {$name}, how likely would you recommend our service?";
        
        // Replace default question with personalised one
        $html = preg_replace(
            '/<h3 class="wirenps-question">.*?<\/h3>/',
            "<h3 class=\"wirenps-question\">{$customQuestion}</h3>",
            $html
        );
        
        $event->return = $html;
    }
});
```

### 6. Google Analytics Integration

Track NPS submissions in Google Analytics:

```php
$wire->addHookAfter('WireNPS::saveRating', function($event) {
    $rating = $event->arguments(0);
    
    // This creates a server-side GA event
    // You'll need Google Analytics Measurement Protocol
    
    $measurementId = 'G-XXXXXXXXXX';
    $apiSecret = 'YOUR_API_SECRET';
    
    $data = [
        'client_id' => session_id(),
        'events' => [[
            'name' => 'nps_rating',
            'params' => [
                'score' => $rating['score'],
                'type' => $rating['score'] >= 9 ? 'promoter' : ($rating['score'] >= 7 ? 'passive' : 'detractor'),
                'page_id' => $rating['page_id'],
                'has_feedback' => !empty($rating['feedback'])
            ]
        ]]
    ];
    
    $url = "https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
});
```

### 7. Database Cleanup - Delete Old Ratings

Automatically remove ratings older than 1 year:

```php
// Add to a cron job or scheduled task

$wire->addHook('LazyCron::everyDay', function($event) {
    $database = wire('database');
    $tableName = WireNPS::TABLE_NAME;
    
    $oneYearAgo = time() - (365 * 24 * 60 * 60);
    
    $sql = "DELETE FROM {$tableName} WHERE created < :time_limit";
    $query = $database->prepare($sql);
    $query->execute([':time_limit' => $oneYearAgo]);
    
    $deletedCount = $query->rowCount();
    
    if($deletedCount > 0) {
        wire('log')->save('wirenps', "Deleted {$deletedCount} old ratings");
    }
});
```

### 8. Auto-Thank Users with High Scores

Send thank you email to promoters:

```php
$wire->addHookAfter('WireNPS::saveRating', function($event) {
    $rating = $event->arguments(0);
    
    // Only for promoters (9-10)
    if($rating['score'] >= 9) {
        $user = wire('users')->get($rating['user_id']);
        
        if($user->id && $user->email) {
            $mail = wireMail();
            $mail->to($user->email);
            $mail->from('noreply@yoursite.com');
            $mail->subject('Thank You for Your Feedback!');
            
            $body = "Hi {$user->name},\n\n";
            $body .= "Thank you so much for your fantastic rating of {$rating['score']}/10! ";
            $body .= "We're thrilled to hear you're enjoying our service.\n\n";
            $body .= "Your feedback means a lot to us and helps us continue to improve.\n\n";
            $body .= "Best regards,\nThe Team";
            
            $mail->body($body);
            $mail->send();
        }
    }
});
```

### 9. A/B Testing Different Questions

Test different question variations:

```php
$wire->addHookBefore('WireNPS::getWidgetHTML', function($event) {
    // Randomly assign users to variant A or B
    $variant = (mt_rand(0, 1) === 0) ? 'A' : 'B';
    
    // Store variant in session
    if(!isset($_SESSION['nps_variant'])) {
        $_SESSION['nps_variant'] = $variant;
    } else {
        $variant = $_SESSION['nps_variant'];
    }
    
    $wirenps = $event->object;
    
    if($variant === 'A') {
        $wirenps->set('questionText', 'How likely are you to recommend us?');
    } else {
        $wirenps->set('questionText', 'Would you recommend our service to friends?');
    }
});

// Track which variant was used
$wire->addHookBefore('WireNPS::saveRating', function($event) {
    $data = $event->arguments(0);
    $data['ab_variant'] = $_SESSION['nps_variant'] ?? 'unknown';
    $event->arguments(0, $data);
});
```

### 10. Prevent Spam Submissions

Add basic spam protection:

```php
$wire->addHookBefore('WireNPS::processSubmission', function($event) {
    $input = wire('input');
    
    // Honeypot check (add hidden field in JS)
    if($input->post('honeypot')) {
        $event->replace = true;
        $event->return = json_encode(['success' => false, 'error' => 'Spam detected']);
        return;
    }
    
    // Time-based check (submission too fast)
    $sessionStartTime = $_SESSION['nps_widget_shown'] ?? time();
    $timeSinceShown = time() - $sessionStartTime;
    
    if($timeSinceShown < 3) { // Less than 3 seconds
        $event->replace = true;
        $event->return = json_encode(['success' => false, 'error' => 'Too fast']);
        return;
    }
    
    // Rate limiting by IP
    $ip = $input->ip();
    $cacheKey = "nps_rate_limit_{$ip}";
    $cache = wire('cache');
    
    $submissionCount = $cache->get($cacheKey) ?: 0;
    
    if($submissionCount >= 5) { // Max 5 per hour
        $event->replace = true;
        $event->return = json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
        return;
    }
    
    $cache->save($cacheKey, $submissionCount + 1, 3600); // 1 hour
});
```

## Custom Widget Placement

### Show Widget on Specific Button Click

```php
// In your template
echo "<button id='trigger-nps'>Share Feedback</button>";

// In your JavaScript
document.getElementById('trigger-nps').addEventListener('click', function() {
    var overlay = document.getElementById('wirenps-overlay');
    overlay.classList.remove('hidden');
    overlay.classList.add('wirenps-show');
});
```

### Embed Widget Inline (Not as Popup)

```php
$wire->addHookAfter('WireNPS::getWidgetHTML', function($event) {
    $html = $event->return;
    
    // Remove overlay wrapper for inline display
    $html = str_replace('wirenps-overlay hidden', 'wirenps-inline', $html);
    
    $event->return = $html;
});

// Add CSS for inline display
// .wirenps-inline { position: relative; background: transparent; }
// .wirenps-inline .wirenps-modal { box-shadow: none; }
```

## Advanced Analytics Hooks

### Track Response Time

```php
$wire->addHook('Page::render', function($event) {
    if(!isset($_SESSION['nps_widget_shown_time'])) {
        $_SESSION['nps_widget_shown_time'] = time();
    }
});

$wire->addHookBefore('WireNPS::saveRating', function($event) {
    $data = $event->arguments(0);
    
    $shownTime = $_SESSION['nps_widget_shown_time'] ?? time();
    $responseTime = time() - $shownTime;
    
    $data['response_time'] = $responseTime; // Seconds to respond
    
    $event->arguments(0, $data);
    unset($_SESSION['nps_widget_shown_time']);
});
```

## Testing Hooks

### Debug Mode

```php
$wire->addHookAfter('WireNPS::saveRating', function($event) {
    if(wire('config')->debug) {
        $rating = $event->arguments(0);
        wire('log')->save('wirenps-debug', json_encode($rating));
    }
});
```

---

## Notes

- All hooks should be added in `/site/ready.php` or a custom autoload module
- Hooks execute in order they're defined
- Use `addHookBefore` to modify before execution
- Use `addHookAfter` to act on results
- `$event->replace = true` prevents original method execution
- Always test hooks thoroughly before production

## Documentation

For more information on ProcessWire hooks:
https://processwire.com/docs/modules/hooks/
