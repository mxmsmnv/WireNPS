<?php namespace ProcessWire;

/**
 * WireNPS AJAX Endpoint Template
 * 
 * INSTALLATION:
 * 1. Save this file as: /site/templates/wirenps-ajax.php
 * 2. In admin, create new template "wirenps-ajax" using this file
 * 3. Create new page with this template:
 *    - Name: wirenps-ajax
 *    - Parent: Home (or root)
 *    - Status: Hidden
 * 4. Done! Endpoint will be: https://yoursite.com/wirenps-ajax/
 */

// Kill all output buffers
while(ob_get_level()) {
    ob_end_clean();
}

// Disable all error output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Check if POST request (not GET)
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST request required']);
    exit;
}

// Get WireNPS module
$wirenps = $modules->get('WireNPS');

if(!$wirenps) {
    echo json_encode(['success' => false, 'error' => 'Module not found']);
    exit;
}

try {
    // Get POST data
    $score = (int)$input->post('score');
    $feedback = $sanitizer->text($input->post('feedback'));
    $pageId = (int)$input->post('page_id');
    
    // Validate score
    if($score < 0 || $score > 10) {
        echo json_encode(['success' => false, 'error' => 'Invalid score']);
        exit;
    }
    
    // Check if already submitted (simple cookie check)
    if(isset($_COOKIE['wirenps_submitted'])) {
        echo json_encode(['success' => false, 'error' => 'Already submitted']);
        exit;
    }
    
    // Prepare data
    $data = [
        'score' => $score,
        'feedback' => $feedback,
        'page_id' => $pageId,
        'user_id' => $user->isLoggedin() ? $user->id : 40, // Guest user ID = 40
        'session_id' => session_id(),
        'ip_address' => $session->getIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created' => time()
    ];
    
    // Save to database
    $database = wire('database');
    $sql = "INSERT INTO wirenps_ratings 
            (score, feedback, page_id, user_id, session_id, ip_address, user_agent, created) 
            VALUES (:score, :feedback, :page_id, :user_id, :session_id, :ip_address, :user_agent, :created)";
    
    $query = $database->prepare($sql);
    $result = $query->execute([
        ':score' => $data['score'],
        ':feedback' => $data['feedback'],
        ':page_id' => $data['page_id'],
        ':user_id' => $data['user_id'],
        ':session_id' => $data['session_id'],
        ':ip_address' => $data['ip_address'],
        ':user_agent' => $data['user_agent'],
        ':created' => $data['created']
    ]);
    
    if($result) {
        // Get WireNPS module for language detection
        $lang = 'en';
        if($wirenps) {
            if(method_exists($wirenps, 'detectLanguage')) {
                $lang = $wirenps->detectLanguage();
            }
        }
        
        // Get thank you message in appropriate language
        $thankYouMsg = 'Thank you for your feedback!';
        if($lang === 'de') {
            $thankYouMsg = 'Vielen Dank für Ihr Feedback!';
        } elseif($lang === 'fr') {
            $thankYouMsg = 'Merci pour votre avis!';
        } elseif($lang === 'zh') {
            $thankYouMsg = '感谢您的反馈！';
        }
        
        // Success
        echo json_encode([
            'success' => true,
            'message' => $thankYouMsg
        ]);
    } else {
        // Database error
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
} catch(\Exception $e) {
    // Log error
    @wire('log')->save('wirenps-errors', $e->getMessage());
    
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}

exit;
