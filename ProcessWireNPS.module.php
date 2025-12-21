<?php namespace ProcessWire;

/**
 * WireNPS Admin Page
 * View and analyze NPS ratings
 */

class ProcessWireNPS extends Process {

    public static function getModuleInfo() {
        return [
            'title' => 'WireNPS Statistics',
            'summary' => 'View and analyze NPS ratings',
            'version' => '1.2.0',
            'author' => 'Maxim',
            'icon' => 'bar-chart',
            'requires' => 'WireNPS',
            'permission' => 'wirenps-view',
            'page' => [
                'name' => 'wirenps',
                'parent' => 'setup',
                'title' => 'NPS Statistics'
            ]
        ];
    }

    /**
     * Execute - main admin page
     */
    public function ___execute() {
        $this->wire('modules')->get('JqueryWireTabs');
        
        $out = '';
        
        // Statistics
        $stats = $this->getStatistics();
        $out .= $this->renderStatistics($stats);
        
        // Tabs
        $tabs = new InputfieldWrapper();
        
        // Recent ratings tab
        $tab = new InputfieldWrapper();
        $tab->attr('id', 'ratings-tab');
        $tab->attr('title', $this->_('Recent Ratings'));
        $tab->add($this->renderRatingsTable());
        $tabs->add($tab);
        
        // Charts tab
        $tab = new InputfieldWrapper();
        $tab->attr('id', 'charts-tab');
        $tab->attr('title', $this->_('Charts'));
        $tab->add($this->renderCharts($stats));
        $tabs->add($tab);
        
        // Export tab
        $tab = new InputfieldWrapper();
        $tab->attr('id', 'export-tab');
        $tab->attr('title', $this->_('Export'));
        $tab->add($this->renderExport());
        $tabs->add($tab);
        
        $out .= $tabs->render();
        
        return $out;
    }

    /**
     * Get NPS statistics
     */
    protected function getStatistics() {
        $database = $this->wire('database');
        $tableName = WireNPS::TABLE_NAME;
        
        // Total ratings
        $sql = "SELECT COUNT(*) as total FROM {$tableName}";
        $query = $database->prepare($sql);
        $query->execute();
        $total = $query->fetch(\PDO::FETCH_ASSOC)['total'];
        
        // Score distribution
        $sql = "SELECT score, COUNT(*) as count FROM {$tableName} GROUP BY score ORDER BY score";
        $query = $database->prepare($sql);
        $query->execute();
        $distribution = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        // NPS calculation
        $sql = "SELECT 
                    SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) as promoters,
                    SUM(CASE WHEN score >= 7 AND score <= 8 THEN 1 ELSE 0 END) as passives,
                    SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END) as detractors
                FROM {$tableName}";
        $query = $database->prepare($sql);
        $query->execute();
        $npsData = $query->fetch(\PDO::FETCH_ASSOC);
        
        // Calculate NPS score
        $promoters = (int)$npsData['promoters'];
        $detractors = (int)$npsData['detractors'];
        $npsScore = $total > 0 ? round((($promoters - $detractors) / $total) * 100, 1) : 0;
        
        // Average score
        $sql = "SELECT AVG(score) as avg_score FROM {$tableName}";
        $query = $database->prepare($sql);
        $query->execute();
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        $avgScore = $result['avg_score'] ? round($result['avg_score'], 2) : 0;
        
        // Recent trend (last 30 days)
        $sql = "SELECT DATE(FROM_UNIXTIME(created)) as date, AVG(score) as avg_score 
                FROM {$tableName} 
                WHERE created >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                GROUP BY DATE(FROM_UNIXTIME(created))
                ORDER BY date";
        $query = $database->prepare($sql);
        $query->execute();
        $trend = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'nps_score' => $npsScore,
            'avg_score' => $avgScore,
            'promoters' => $promoters,
            'passives' => (int)$npsData['passives'],
            'detractors' => $detractors,
            'distribution' => $distribution,
            'trend' => $trend
        ];
    }

    /**
     * Render statistics cards
     */
    protected function renderStatistics($stats) {
        $npsColor = $this->getNPSColor($stats['nps_score']);
        
        return <<<HTML
<div class="wirenps-stats-grid">
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">NPS Score</div>
        <div class="wirenps-stat-value" style="color: {$npsColor};">{$stats['nps_score']}</div>
        <div class="wirenps-stat-desc">{$this->getNPSRating($stats['nps_score'])}</div>
    </div>
    
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">Total Ratings</div>
        <div class="wirenps-stat-value">{$stats['total']}</div>
        <div class="wirenps-stat-desc">All time</div>
    </div>
    
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">Average Score</div>
        <div class="wirenps-stat-value">{$stats['avg_score']}</div>
        <div class="wirenps-stat-desc">Out of 10</div>
    </div>
    
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">Promoters</div>
        <div class="wirenps-stat-value" style="color: #22c55e;">{$stats['promoters']}</div>
        <div class="wirenps-stat-desc">Score 9-10</div>
    </div>
    
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">Passives</div>
        <div class="wirenps-stat-value" style="color: #eab308;">{$stats['passives']}</div>
        <div class="wirenps-stat-desc">Score 7-8</div>
    </div>
    
    <div class="wirenps-stat-card">
        <div class="wirenps-stat-label">Detractors</div>
        <div class="wirenps-stat-value" style="color: #ef4444;">{$stats['detractors']}</div>
        <div class="wirenps-stat-desc">Score 0-6</div>
    </div>
</div>

<style>
.wirenps-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin: 20px 0 40px 0;
}

.wirenps-stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.wirenps-stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 8px;
}

.wirenps-stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 4px;
}

.wirenps-stat-desc {
    font-size: 12px;
    color: #9ca3af;
}
</style>
HTML;
    }

    /**
     * Render ratings table
     */
    protected function renderRatingsTable() {
        $database = $this->wire('database');
        $tableName = WireNPS::TABLE_NAME;
        
        $limit = 50;
        $start = (int)$this->wire('input')->get('start', 0);
        
        // Get ratings
        $sql = "SELECT * FROM {$tableName} ORDER BY created DESC LIMIT {$start}, {$limit}";
        $query = $database->prepare($sql);
        $query->execute();
        $ratings = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get total count
        $sql = "SELECT COUNT(*) as total FROM {$tableName}";
        $query = $database->prepare($sql);
        $query->execute();
        $total = $query->fetch(\PDO::FETCH_ASSOC)['total'];
        
        $table = $this->wire('modules')->get('MarkupAdminDataTable');
        $table->headerRow([
            $this->_('Date'),
            $this->_('Score'),
            $this->_('Type'),
            $this->_('Feedback'),
            $this->_('Page'),
            $this->_('User')
        ]);
        
        foreach($ratings as $rating) {
            $type = $this->getRatingType($rating['score']);
            $typeColor = $this->getTypeColor($type);
            $date = date('Y-m-d H:i', $rating['created']);
            
            $page = $rating['page_id'] ? $this->wire('pages')->get($rating['page_id']) : null;
            $pageTitle = $page && $page->id ? $page->title : '-';
            
            $user = $rating['user_id'] ? $this->wire('users')->get($rating['user_id']) : null;
            $userName = $user && $user->id ? $user->name : 'Guest';
            
            $feedback = $rating['feedback'] ? $this->wire('sanitizer')->entities(substr($rating['feedback'], 0, 100)) : '-';
            
            // Use entityEncode for proper display
            $table->row([
                $date,
                $rating['score'],
                $type, // Plain text, no HTML
                $feedback,
                $pageTitle,
                $userName
            ]);
        }
        
        $fieldset = $this->wire('modules')->get('InputfieldMarkup');
        $fieldset->label = $this->_('Recent Ratings');
        $fieldset->value = $table->render();
        
        // Pagination
        if($total > $limit) {
            $pager = $this->wire('modules')->get('MarkupPagerNav');
            $fieldset->value .= $pager->render([
                'numPageLinks' => 10,
                'totalItems' => $total,
                'itemsPerPage' => $limit,
                'currentPage' => floor($start / $limit) + 1
            ]);
        }
        
        return $fieldset;
    }

    /**
     * Render charts
     */
    protected function renderCharts($stats) {
        $distributionData = json_encode(array_column($stats['distribution'], 'count'));
        $distributionLabels = json_encode(array_column($stats['distribution'], 'score'));
        
        $trendData = json_encode(array_column($stats['trend'], 'avg_score'));
        $trendLabels = json_encode(array_column($stats['trend'], 'date'));
        
        $fieldset = $this->wire('modules')->get('InputfieldMarkup');
        $fieldset->label = $this->_('Visual Analytics');
        
        $fieldset->value = <<<HTML
<div class="wirenps-charts">
    <div class="wirenps-chart-container">
        <h3>Score Distribution</h3>
        <canvas id="distributionChart"></canvas>
    </div>
    
    <div class="wirenps-chart-container">
        <h3>30-Day Trend</h3>
        <canvas id="trendChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Distribution Chart
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
new Chart(distributionCtx, {
    type: 'bar',
    data: {
        labels: {$distributionLabels},
        datasets: [{
            label: 'Number of Ratings',
            data: {$distributionData},
            backgroundColor: function(context) {
                const score = context.parsed.x;
                if(score <= 6) return '#ef4444';
                if(score <= 8) return '#eab308';
                return '#22c55e';
            }
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: {$trendLabels},
        datasets: [{
            label: 'Average Score',
            data: {$trendData},
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { 
                beginAtZero: true,
                max: 10
            }
        }
    }
});
</script>

<style>
.wirenps-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    margin-top: 20px;
}

.wirenps-chart-container {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}

.wirenps-chart-container h3 {
    margin-top: 0;
    color: #111827;
}
</style>
HTML;
        
        return $fieldset;
    }

    /**
     * Render export options
     */
    protected function renderExport() {
        $fieldset = $this->wire('modules')->get('InputfieldMarkup');
        $fieldset->label = $this->_('Export Data');
        
        $exportUrl = $this->wire('page')->url . 'export/';
        
        $fieldset->value = <<<HTML
<div class="wirenps-export">
    <h3>Export Options</h3>
    <p>Download all ratings data in CSV format for further analysis.</p>
    
    <a href="{$exportUrl}" class="ui-button ui-priority-secondary" download>
        <i class="fa fa-download"></i> Download CSV
    </a>
</div>

<style>
.wirenps-export {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 30px;
}
</style>
HTML;
        
        return $fieldset;
    }

    /**
     * Execute export
     */
    public function ___executeExport() {
        $database = $this->wire('database');
        $tableName = WireNPS::TABLE_NAME;
        
        $sql = "SELECT * FROM {$tableName} ORDER BY created DESC";
        $query = $database->prepare($sql);
        $query->execute();
        $ratings = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wirenps-export-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'Score', 'Type', 'Feedback', 'Page ID', 'User ID', 'IP Address', 'Created'], ',', '"', '\\');
        
        // Data
        foreach($ratings as $rating) {
            fputcsv($output, [
                $rating['id'],
                $rating['score'],
                $this->getRatingType($rating['score']),
                $rating['feedback'],
                $rating['page_id'],
                $rating['user_id'],
                $rating['ip_address'],
                date('Y-m-d H:i:s', $rating['created'])
            ], ',', '"', '\\');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get rating type
     */
    protected function getRatingType($score) {
        if($score <= 6) return 'Detractor';
        if($score <= 8) return 'Passive';
        return 'Promoter';
    }

    /**
     * Get type color
     */
    protected function getTypeColor($type) {
        switch($type) {
            case 'Promoter': return '#22c55e';
            case 'Passive': return '#eab308';
            case 'Detractor': return '#ef4444';
            default: return '#6b7280';
        }
    }

    /**
     * Get NPS color
     */
    protected function getNPSColor($score) {
        if($score >= 50) return '#22c55e';
        if($score >= 0) return '#eab308';
        return '#ef4444';
    }

    /**
     * Get NPS rating
     */
    protected function getNPSRating($score) {
        if($score >= 70) return 'Excellent';
        if($score >= 50) return 'Great';
        if($score >= 30) return 'Good';
        if($score >= 0) return 'Needs Improvement';
        return 'Poor';
    }
}
