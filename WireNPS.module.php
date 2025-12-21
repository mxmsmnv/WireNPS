<?php namespace ProcessWire;

/**
 * WireNPS - Net Promoter Score Module for ProcessWire
 * 
 * Collect NPS ratings and feedback from site visitors with a clean popup interface
 * 
 * @author Maxim
 * @version 1.0.0
 * @license MIT
 */

class WireNPS extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return [
            'title' => 'WireNPS - Net Promoter Score',
            'summary' => 'Collect NPS ratings and feedback with a clean popup interface',
            'version' => '1.2.0',
            'author' => 'Maxim',
            'icon' => 'star',
            'autoload' => true,
            'singular' => true,
            'requires' => 'ProcessWire>=3.0.0'
        ];
    }

    const TABLE_NAME = 'wirenps_ratings';

    /**
     * Default configuration
     */
    public function __construct() {
        parent::__construct();
        
        $this->set('enabledTemplates', []);
        $this->set('minScore', 0);
        $this->set('maxScore', 10);
        $this->set('detractorMax', 6);
        $this->set('passiveMax', 8);
        $this->set('questionText', 'How likely would you recommend our service to your friends?');
        $this->set('questionTextFR', 'Dans quelle mesure recommanderiez-vous notre service à vos amis?');
        $this->set('lowLabel', 'Not at all likely');
        $this->set('lowLabelFR', 'pas du tout probable');
        $this->set('highLabel', 'Extremely likely');
        $this->set('highLabelFR', 'extrêmement probable');
        $this->set('feedbackPlaceholder', 'We appreciate your feedback (optional)');
        $this->set('feedbackPlaceholderFR', 'Nous serions reconnaissants si vous pouviez expliquer votre note en quelques mots. (facultatif)');
        $this->set('submitButton', 'Submit');
        $this->set('submitBtnFR', 'ENVOYER');
        $this->set('thankYouMessage', 'Thank you for your feedback!');
        $this->set('thankYouMessageFR', 'Merci pour votre avis!');
        $this->set('showDelay', 5000);
        $this->set('cookieExpiry', 30);
        $this->set('requireAuth', false);
        $this->set('allowMultiple', false);
        $this->set('collectIP', true);
        $this->set('collectUserAgent', true);
    }

    /**
     * Initialize module
     */
    public function init() {
        // Add hooks for frontend output
        $this->addHookAfter('Page::render', $this, 'addNPSWidget');
        
        // AJAX handling is done by dedicated page template (wirenps-ajax.php)
        // No hook needed here
    }

    /**
     * Ready hook
     */
    public function ready() {
        // Admin page is automatically created by ProcessWireNPS module
    }

    /**
     * Add NPS widget to page output
     */
    public function addNPSWidget(HookEvent $event) {
        $page = $event->object;
        
        // Don't show in admin
        if($page->template == 'admin') {
            return;
        }
        
        // Check public/private mode
        $user = $this->wire('user');
        if(!$this->showToGuests && !$user->isLoggedin()) {
            return; // Private mode - only logged in users
        }
        
        // Check if NPS is enabled for this template
        if(!$this->isEnabledForTemplate($page->template->name)) {
            return;
        }
        
        // Check if user has already submitted
        if(!$this->allowMultiple && $this->hasUserSubmitted()) {
            return;
        }
        
        $output = $event->return;
        
        // Add CSS and JS
        $assets = $this->getAssets();
        $widget = $this->getWidgetHTML();
        
        // Inject before </body>
        $output = str_replace('</body>', $assets . $widget . '</body>', $output);
        
        $event->return = $output;
    }

    /**
     * Get widget HTML
     */
    protected function getWidgetHTML() {
        $lang = $this->detectLanguage();
        
        // Select texts based on detected language
        if($lang === 'ru') {
            $question = $this->questionTextFR ?: $this->questionText;
            $lowLabel = $this->lowLabelFR ?: $this->lowLabel;
            $highLabel = $this->highLabelFR ?: $this->highLabel;
            $placeholder = $this->feedbackPlaceholderFR ?: $this->feedbackPlaceholder;
            $submitBtn = $this->submitBtnFR ?: $this->submitButton;
        } elseif($lang === 'pl') {
            $question = $this->questionTextZH ?: $this->questionText;
            $lowLabel = $this->lowLabelZH ?: $this->lowLabel;
            $highLabel = $this->highLabelZH ?: $this->highLabel;
            $placeholder = $this->feedbackPlaceholderZH ?: $this->feedbackPlaceholder;
            $submitBtn = $this->submitBtnZH ?: $this->submitButton;
        } elseif($lang === 'de') {
            $question = $this->questionTextDE ?: $this->questionText;
            $lowLabel = $this->lowLabelDE ?: $this->lowLabel;
            $highLabel = $this->highLabelDE ?: $this->highLabel;
            $placeholder = $this->feedbackPlaceholderDE ?: $this->feedbackPlaceholder;
            $submitBtn = $this->submitBtnDE ?: $this->submitButton;
        } else {
            // English (default)
            $question = $this->questionText;
            $lowLabel = $this->lowLabel;
            $highLabel = $this->highLabel;
            $placeholder = $this->feedbackPlaceholder;
            $submitBtn = $this->submitButton;
        }
        
        $buttons = '';
        for($i = $this->minScore; $i <= $this->maxScore; $i++) {
            $colorClass = $this->getScoreColorClass($i);
            $buttons .= "<button type=\"button\" class=\"wirenps-score-btn {$colorClass}\" data-score=\"{$i}\">{$i}</button>";
        }
        
        return <<<HTML
<div id="wirenps-overlay" class="wirenps-overlay hidden">
    <div class="wirenps-modal">
        <button type="button" class="wirenps-close" aria-label="Close">&times;</button>
        
        <div class="wirenps-content">
            <h3 class="wirenps-question">{$question}</h3>
            
            <div class="wirenps-scores">
                {$buttons}
            </div>
            
            <div class="wirenps-labels">
                <span class="wirenps-label-low">{$lowLabel}</span>
                <span class="wirenps-label-high">{$highLabel}</span>
            </div>
            
            <div class="wirenps-feedback-container hidden">
                <textarea 
                    id="wirenps-feedback" 
                    class="wirenps-feedback" 
                    placeholder="{$placeholder}"
                    rows="4"
                ></textarea>
                
                <button type="button" class="wirenps-submit" id="wirenps-submit">
                    {$submitBtn}
                </button>
            </div>
            
            <div class="wirenps-thank-you hidden">
                <p>{$this->thankYouMessage}</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Get CSS and JS assets
     */
    protected function getAssets() {
        $moduleUrl = $this->wire('config')->urls->siteModules . 'WireNPS/';
        
        $config = [
            'delay' => (int)$this->showDelay,
            'cookieExpiry' => (int)$this->cookieExpiry,
            'ajaxUrl' => $this->wire('config')->urls->root . 'wirenps-ajax/', // Use dedicated page
            'pageId' => (int)$this->wire('page')->id, // Current page ID
            'allowMultiple' => (bool)$this->allowMultiple, // Allow multiple submissions
        ];
        
        $configJson = json_encode($config);
        
        return <<<HTML
<link rel="stylesheet" href="{$moduleUrl}WireNPS.css">
<script>
window.wireNPSConfig = {$configJson};
console.log('[WireNPS-PHP] Config set from PHP:', window.wireNPSConfig);
</script>
<script src="{$moduleUrl}WireNPS.js"></script>
HTML;
    }

    /**
     * Get color class for score
     */
    protected function getScoreColorClass($score) {
        if($score <= $this->detractorMax) {
            return 'wirenps-detractor';
        } elseif($score <= $this->passiveMax) {
            return 'wirenps-passive';
        } else {
            return 'wirenps-promoter';
        }
    }

    /**
     * Handle AJAX submission
     */
    public function handleAjaxSubmit(HookEvent $event) {
        $input = $this->wire('input');
        
        // Check if this is a WireNPS AJAX submission
        if(!$this->wire('config')->ajax) return;
        if($input->post('wirenps_action') !== 'submit') return;
        
        // Stop ALL output and page rendering
        $event->replace = true;
        
        // Kill any existing output
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer
        ob_start();
        
        // Process and output JSON
        $this->processSubmission();
        
        // Never reach here due to exit in processSubmission
    }

    /**
     * Process rating submission
     */
    protected function processSubmission() {
        // Kill ALL existing output buffers
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        // Disable ALL error output
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        
        // Suppress ALL warnings and notices
        error_reporting(0);
        
        // Set headers before ANY output
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            $input = $this->wire('input');
            
            // Validate input
            $score = (int)$input->post('score');
            $feedback = $this->wire('sanitizer')->text($input->post('feedback'));
            $pageId = (int)$input->post('page_id');
            
            if($score < $this->minScore || $score > $this->maxScore) {
                $response = ['success' => false, 'error' => 'Invalid score'];
                echo json_encode($response);
                exit;
            }
            
            // Check authentication if required
            if($this->requireAuth && !$this->wire('user')->isLoggedin()) {
                $response = ['success' => false, 'error' => 'Authentication required'];
                echo json_encode($response);
                exit;
            }
            
            // Check if already submitted
            if(!$this->allowMultiple && $this->hasUserSubmitted()) {
                $response = ['success' => false, 'error' => 'Already submitted'];
                echo json_encode($response);
                exit;
            }
            
            // Save to database
            $user = $this->wire('user');
            $userId = $user->isLoggedin() ? $user->id : 40; // Guest user ID = 40
            
            $result = $this->saveRating([
                'score' => $score,
                'feedback' => $feedback,
                'page_id' => $pageId,
                'user_id' => $userId,
                'session_id' => session_id(),
                'ip_address' => $this->collectIP ? $this->wire('session')->getIP() : null,
                'user_agent' => $this->collectUserAgent ? $_SERVER['HTTP_USER_AGENT'] : null,
                'created' => time()
            ]);
            
            if($result) {
                // Get thank you message in appropriate language
                $lang = $this->detectLanguage();
                $thankYouMsg = $this->thankYouMessage; // Default English
                
                if($lang === 'de' && $this->thankYouMessageDE) {
                    $thankYouMsg = $this->thankYouMessageDE;
                } elseif($lang === 'fr' && $this->thankYouMessageFR) {
                    $thankYouMsg = $this->thankYouMessageFR;
                } elseif($lang === 'zh' && $this->thankYouMessageZH) {
                    $thankYouMsg = $this->thankYouMessageZH;
                }
                
                $response = [
                    'success' => true, 
                    'message' => $thankYouMsg
                ];
            } else {
                $response = ['success' => false, 'error' => 'Database error'];
            }
            
            echo json_encode($response);
            
        } catch(\Exception $e) {
            // Log error silently
            @$this->wire('log')->save('wirenps-errors', $e->getMessage());
            
            $response = ['success' => false, 'error' => 'An error occurred'];
            echo json_encode($response);
        }
        
        exit;
    }

    /**
     * Save rating to database
     */
    protected function saveRating($data) {
        $database = $this->wire('database');
        
        $sql = "INSERT INTO " . self::TABLE_NAME . " 
                (score, feedback, page_id, user_id, session_id, ip_address, user_agent, created) 
                VALUES 
                (:score, :feedback, :page_id, :user_id, :session_id, :ip_address, :user_agent, :created)";
        
        try {
            $query = $database->prepare($sql);
            $query->execute([
                ':score' => $data['score'],
                ':feedback' => $data['feedback'],
                ':page_id' => $data['page_id'],
                ':user_id' => $data['user_id'],
                ':session_id' => $data['session_id'],
                ':ip_address' => $data['ip_address'],
                ':user_agent' => $data['user_agent'],
                ':created' => $data['created']
            ]);
            
            return true;
        } catch(\Exception $e) {
            $this->error("Failed to save rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has already submitted
     */
    protected function hasUserSubmitted() {
        $database = $this->wire('database');
        $sessionId = session_id();
        
        $sql = "SELECT COUNT(*) as count FROM " . self::TABLE_NAME . " 
                WHERE session_id = :session_id 
                OR (ip_address = :ip_address AND created > :time_limit)";
        
        $query = $database->prepare($sql);
        $query->execute([
            ':session_id' => $sessionId,
            ':ip_address' => $this->wire('session')->getIP(),
            ':time_limit' => time() - (86400 * $this->cookieExpiry)
        ]);
        
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Check if NPS is enabled for template
     */
    protected function isEnabledForTemplate($templateName) {
        if(empty($this->enabledTemplates)) {
            return true; // If no templates specified, enable for all
        }
        
        return in_array($templateName, $this->enabledTemplates);
    }

    /**
     * Detect user language
     */
    protected function detectLanguage() {
        $secondLang = $this->secondLanguage ?: 'de'; // Default to German
        
        // Check ProcessWire language
        if($this->wire('user')->language && $this->wire('user')->language->name) {
            $langName = strtolower($this->wire('user')->language->name);
            
            // Check for second language
            if($secondLang === 'de' && in_array($langName, ['german', 'de', 'deutsch'])) {
                return 'de';
            }
            if($secondLang === 'fr' && in_array($langName, ['french', 'fr', 'français', 'francais'])) {
                return 'fr';
            }
            if($secondLang === 'zh' && in_array($langName, ['chinese', 'zh', '中文', 'zh-cn', 'zh-tw'])) {
                return 'zh';
            }
        }
        
        // Check browser language
        if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            
            // Return if matches second language
            if($browserLang === $secondLang) {
                return $secondLang;
            }
        }
        
        return 'en';
    }

    /**
     * Install module - create database table
     */
    public function ___install() {
        $database = $this->wire('database');
        
        $sql = "CREATE TABLE IF NOT EXISTS " . self::TABLE_NAME . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            score TINYINT UNSIGNED NOT NULL,
            feedback TEXT,
            page_id INT UNSIGNED,
            user_id INT UNSIGNED,
            session_id VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            created INT UNSIGNED NOT NULL,
            INDEX score_idx (score),
            INDEX page_idx (page_id),
            INDEX created_idx (created)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $database->exec($sql);
            $this->message("Database table created successfully");
        } catch(\Exception $e) {
            $this->error("Failed to create database table: " . $e->getMessage());
        }
    }

    /**
     * Uninstall module - remove database table
     */
    public function ___uninstall() {
        $database = $this->wire('database');
        
        try {
            $database->exec("DROP TABLE IF EXISTS " . self::TABLE_NAME);
            $this->message("Database table removed successfully");
        } catch(\Exception $e) {
            $this->error("Failed to remove database table: " . $e->getMessage());
        }
    }

    /**
     * Module configuration
     */
    public static function getModuleConfigInputfields(array $data) {
        $inputfields = new InputfieldWrapper();
        $modules = wire('modules');
        
        // Enabled Templates
        $field = $modules->get('InputfieldAsmSelect');
        $field->name = 'enabledTemplates';
        $field->label = __('Enabled Templates');
        $field->description = __('Select templates where NPS widget should appear (leave empty for all)');
        $field->icon = 'file-text-o';
        
        foreach(wire('templates') as $template) {
            if($template->name[0] !== '_') {
                $field->addOption($template->name, $template->name);
            }
        }
        
        if(isset($data['enabledTemplates'])) {
            $field->value = $data['enabledTemplates'];
        }
        
        $inputfields->add($field);
        
        // Score Range
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('Score Configuration');
        $fieldset->icon = 'sliders';
        
        $field = $modules->get('InputfieldInteger');
        $field->name = 'detractorMax';
        $field->label = __('Detractor Max Score');
        $field->description = __('Maximum score for detractors (red)');
        $field->value = isset($data['detractorMax']) ? $data['detractorMax'] : 6;
        $field->columnWidth = 33;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldInteger');
        $field->name = 'passiveMax';
        $field->label = __('Passive Max Score');
        $field->description = __('Maximum score for passives (yellow)');
        $field->value = isset($data['passiveMax']) ? $data['passiveMax'] : 8;
        $field->columnWidth = 33;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // Text Configuration
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('Text & Labels (English)');
        $fieldset->icon = 'language';
        $fieldset->collapsed = Inputfield::collapsedNo;
        
        $field = $modules->get('InputfieldText');
        $field->name = 'questionText';
        $field->label = __('Question Text');
        $field->value = isset($data['questionText']) ? $data['questionText'] : 'How likely would you recommend our service to your friends?';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'lowLabel';
        $field->label = __('Low Score Label');
        $field->value = isset($data['lowLabel']) ? $data['lowLabel'] : 'Not at all likely';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'highLabel';
        $field->label = __('High Score Label');
        $field->value = isset($data['highLabel']) ? $data['highLabel'] : 'Extremely likely';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'feedbackPlaceholder';
        $field->label = __('Feedback Placeholder');
        $field->value = isset($data['feedbackPlaceholder']) ? $data['feedbackPlaceholder'] : 'We appreciate your feedback (optional)';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'submitButton';
        $field->label = __('Submit Button');
        $field->value = isset($data['submitButton']) ? $data['submitButton'] : 'SUBMIT';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'thankYouMessage';
        $field->label = __('Thank You Message');
        $field->value = isset($data['thankYouMessage']) ? $data['thankYouMessage'] : 'Thank you for your feedback!';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // Second Language Selection
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('Second Language');
        $fieldset->icon = 'globe';
        $fieldset->collapsed = Inputfield::collapsedNo;
        
        $field = $modules->get('InputfieldSelect');
        $field->name = 'secondLanguage';
        $field->label = __('Select Second Language');
        $field->description = __('Choose which language to use as alternative to English');
        $field->addOption('de', 'German (Deutsch)');
        $field->addOption('fr', 'French (Français)');
        $field->addOption('zh', 'Chinese (中文)');
        $field->value = isset($data['secondLanguage']) ? $data['secondLanguage'] : 'de';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // French Labels
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('French Language (Français)');
        $fieldset->icon = 'language';
        $fieldset->collapsed = Inputfield::collapsedYes;
        
        $field = $modules->get('InputfieldText');
        $field->name = 'questionTextFR';
        $field->label = __('Question Text');
        $field->value = isset($data['questionTextFR']) ? $data['questionTextFR'] : 'Dans quelle mesure recommanderiez-vous notre service à vos amis?';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'feedbackPlaceholderFR';
        $field->label = __('Feedback Placeholder');
        $field->value = isset($data['feedbackPlaceholderFR']) ? $data['feedbackPlaceholderFR'] : 'Nous serions reconnaissants si vous pouviez expliquer votre note en quelques mots. (facultatif)';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'submitBtnFR';
        $field->label = __('Submit Button');
        $field->value = isset($data['submitBtnFR']) ? $data['submitBtnFR'] : 'ENVOYER';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'thankYouMessageFR';
        $field->label = __('Thank You Message');
        $field->value = isset($data['thankYouMessageFR']) ? $data['thankYouMessageFR'] : 'Merci pour votre avis!';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'lowLabelFR';
        $field->label = __('Low Score Label');
        $field->value = isset($data['lowLabelFR']) ? $data['lowLabelFR'] : 'pas du tout probable';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'highLabelFR';
        $field->label = __('High Score Label');
        $field->value = isset($data['highLabelFR']) ? $data['highLabelFR'] : 'extrêmement probable';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // Chinese Labels
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('Chinese Language (中文)');
        $fieldset->icon = 'language';
        $fieldset->collapsed = Inputfield::collapsedYes;
        
        $field = $modules->get('InputfieldText');
        $field->name = 'questionTextZH';
        $field->label = __('Question Text');
        $field->value = isset($data['questionTextZH']) ? $data['questionTextZH'] : '您向朋友推荐我们服务的可能性有多大？';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'feedbackPlaceholderZH';
        $field->label = __('Feedback Placeholder');
        $field->value = isset($data['feedbackPlaceholderZH']) ? $data['feedbackPlaceholderZH'] : '如果您能用几句话解释您的评分，我们将不胜感激。（可选）';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'submitBtnZH';
        $field->label = __('Submit Button');
        $field->value = isset($data['submitBtnZH']) ? $data['submitBtnZH'] : '提交';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'thankYouMessageZH';
        $field->label = __('Thank You Message');
        $field->value = isset($data['thankYouMessageZH']) ? $data['thankYouMessageZH'] : '感谢您的反馈！';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'lowLabelZH';
        $field->label = __('Low Score Label');
        $field->value = isset($data['lowLabelZH']) ? $data['lowLabelZH'] : '完全不可能';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'highLabelZH';
        $field->label = __('High Score Label');
        $field->value = isset($data['highLabelZH']) ? $data['highLabelZH'] : '非常可能';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // German Labels
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('German Language (Deutsch)');
        $fieldset->icon = 'language';
        $fieldset->collapsed = Inputfield::collapsedYes;
        
        $field = $modules->get('InputfieldText');
        $field->name = 'questionTextDE';
        $field->label = __('Question Text');
        $field->value = isset($data['questionTextDE']) ? $data['questionTextDE'] : 'Wie wahrscheinlich ist es, dass Sie unseren Service Ihren Freunden weiterempfehlen?';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'feedbackPlaceholderDE';
        $field->label = __('Feedback Placeholder');
        $field->value = isset($data['feedbackPlaceholderDE']) ? $data['feedbackPlaceholderDE'] : 'Wir wären dankbar, wenn Sie Ihre Bewertung in ein paar Worten erklären. (optional)';
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'submitBtnDE';
        $field->label = __('Submit Button');
        $field->value = isset($data['submitBtnDE']) ? $data['submitBtnDE'] : 'SENDEN';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'thankYouMessageDE';
        $field->label = __('Thank You Message');
        $field->value = isset($data['thankYouMessageDE']) ? $data['thankYouMessageDE'] : 'Vielen Dank für Ihr Feedback!';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'lowLabelDE';
        $field->label = __('Low Score Label');
        $field->value = isset($data['lowLabelDE']) ? $data['lowLabelDE'] : 'überhaupt nicht wahrscheinlich';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldText');
        $field->name = 'highLabelDE';
        $field->label = __('High Score Label');
        $field->value = isset($data['highLabelDE']) ? $data['highLabelDE'] : 'sehr wahrscheinlich';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        // Behavior Settings
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = __('Behavior Settings');
        $fieldset->icon = 'cog';
        
        $field = $modules->get('InputfieldInteger');
        $field->name = 'showDelay';
        $field->label = __('Show Delay (ms)');
        $field->description = __('Delay before showing the popup');
        $field->value = isset($data['showDelay']) ? $data['showDelay'] : 5000;
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldInteger');
        $field->name = 'cookieExpiry';
        $field->label = __('Cookie Expiry (days)');
        $field->description = __('Days before asking the same user again');
        $field->value = isset($data['cookieExpiry']) ? $data['cookieExpiry'] : 30;
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldCheckbox');
        $field->name = 'allowMultiple';
        $field->label = __('Allow Multiple Submissions');
        $field->description = __('Allow users to submit multiple ratings');
        if(isset($data['allowMultiple']) && $data['allowMultiple']) $field->checked = true;
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $modules->get('InputfieldCheckbox');
        $field->name = 'showToGuests';
        $field->label = __('Show to Guests (Public Mode)');
        $field->description = __('Allow non-logged in users to submit ratings (User ID will be 40 for guests)');
        if(isset($data['showToGuests']) && $data['showToGuests']) $field->checked = true;
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $inputfields->add($fieldset);
        
        return $inputfields;
    }
}
