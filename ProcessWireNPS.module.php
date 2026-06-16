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
            'version' => '1.3.0',
            'author' => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
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
        
        // Recent trend (last 90 days)
        $sql = "SELECT DATE(FROM_UNIXTIME(created)) as date, AVG(score) as avg_score 
                FROM {$tableName} 
                WHERE created >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
                GROUP BY DATE(FROM_UNIXTIME(created))
                ORDER BY date";
        $query = $database->prepare($sql);
        $query->execute();
        $trend = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        // Feedback rate
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN feedback IS NOT NULL AND feedback != '' THEN 1 ELSE 0 END) as with_feedback
                FROM {$tableName}";
        $query = $database->prepare($sql);
        $query->execute();
        $fbData = $query->fetch(\PDO::FETCH_ASSOC);
        $feedbackRate = $fbData['total'] > 0 ? round(($fbData['with_feedback'] / $fbData['total']) * 100) : 0;

        // Top pages by rating count
        $sql = "SELECT page_id, COUNT(*) as cnt, AVG(score) as avg_score
                FROM {$tableName}
                WHERE page_id IS NOT NULL AND page_id > 0
                GROUP BY page_id
                ORDER BY cnt DESC
                LIMIT 5";
        $query = $database->prepare($sql);
        $query->execute();
        $topPages = $query->fetchAll(\PDO::FETCH_ASSOC);

        // Monthly breakdown (last 6 months)
        $sql = "SELECT 
                    DATE_FORMAT(FROM_UNIXTIME(created), '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) as promoters,
                    SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END) as detractors,
                    ROUND((SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) - SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END)) / COUNT(*) * 100, 1) as nps
                FROM {$tableName}
                WHERE created >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 6 MONTH))
                GROUP BY DATE_FORMAT(FROM_UNIXTIME(created), '%Y-%m')
                ORDER BY month DESC";
        $query = $database->prepare($sql);
        $query->execute();
        $monthly = $query->fetchAll(\PDO::FETCH_ASSOC);

        // Last rating date
        $sql = "SELECT created FROM {$tableName} ORDER BY created DESC LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute();
        $lastRow = $query->fetch(\PDO::FETCH_ASSOC);
        $lastRating = $lastRow ? (int)$lastRow['created'] : 0;

        return [
            'total' => $total,
            'nps_score' => $npsScore,
            'avg_score' => $avgScore,
            'promoters' => $promoters,
            'passives' => (int)$npsData['passives'],
            'detractors' => $detractors,
            'distribution' => $distribution,
            'trend' => $trend,
            'feedback_rate' => $feedbackRate,
            'top_pages' => $topPages,
            'monthly' => $monthly,
            'last_rating' => $lastRating,
        ];
    }

    /**
     * Render statistics cards
     */
    protected function renderStatistics($stats) {
        $npsColor = $this->getNPSHexColor($stats['nps_score']);

        // Last rating ago
        $lastAgo = '-';
        if($stats['last_rating']) {
            $diff = time() - $stats['last_rating'];
            if($diff < 3600) $lastAgo = round($diff/60) . 'm ago';
            elseif($diff < 86400) $lastAgo = round($diff/3600) . 'h ago';
            else $lastAgo = round($diff/86400) . 'd ago';
        }

        // Top pages rows
        $topPagesHtml = '';
        foreach($stats['top_pages'] as $row) {
            $page = $row['page_id'] ? $this->wire('pages')->get((int)$row['page_id']) : null;
            if($page && $page->id) {
                $safeTitle = $this->wire('sanitizer')->entities($page->title);
                $pageUrl = $page->editUrl;
                $title = "<a href='{$pageUrl}' style='color:var(--pw-main-color);text-decoration:none;' target='_blank'>{$safeTitle}</a>";
            } else {
                $title = 'Page #' . $row['page_id'];
            }
            $avgColor = $row['avg_score'] >= 9 ? '#16a34a' : ($row['avg_score'] >= 7 ? '#ca8a04' : '#dc2626');
            $topPagesHtml .= "<tr>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);color:var(--pw-text-color);font-size:13px;'>{$title}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;font-weight:600;color:var(--pw-text-color);'>{$row['cnt']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;font-weight:700;color:{$avgColor};'>" . round($row['avg_score'], 1) . "</td>
            </tr>";
        }

        // Monthly rows
        $monthlyHtml = '';
        foreach($stats['monthly'] as $row) {
            $npsVal = $row['nps'];
            $npsC = $npsVal >= 50 ? '#16a34a' : ($npsVal >= 0 ? '#ca8a04' : '#dc2626');
            $monthlyHtml .= "<tr>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);color:var(--pw-muted-color);font-size:13px;'>{$row['month']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;color:var(--pw-text-color);'>{$row['total']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;color:#16a34a;font-weight:600;'>{$row['promoters']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;color:#dc2626;font-weight:600;'>{$row['detractors']}</td>
                <td style='padding:6px 10px;border-bottom:1px solid var(--pw-border-color);text-align:center;font-weight:700;color:{$npsC};'>{$npsVal}</td>
            </tr>";
        }

        return <<<HTML
<style>
.wirenps-stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--pw-text-color);
}
.wirenps-card {
    background: var(--pw-blocks-background);
    border: 1px solid var(--pw-border-color);
    border-radius: 4px;
    padding: 14px 10px;
    text-align: center;
    flex: 1;
    min-width: 0;
}
.wirenps-card-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--pw-muted-color);
    margin-bottom: 6px;
    white-space: nowrap;
}
.wirenps-card-desc {
    font-size: 11px;
    color: var(--pw-muted-color);
    margin-top: 4px;
}
.wirenps-cards-row {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}
.wirenps-insights {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
@media (max-width: 900px) {
    .wirenps-cards-row { flex-wrap: wrap; }
    .wirenps-cards-row .wirenps-card { min-width: calc(33% - 8px); }
    .wirenps-insights { grid-template-columns: 1fr; }
}
.wirenps-insight-card {
    background: var(--pw-blocks-background);
    border: 1px solid var(--pw-border-color);
    border-radius: 4px;
    overflow: hidden;
}
.wirenps-insight-card h4 {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--pw-muted-color);
    margin: 0;
    padding: 8px 12px;
    border-bottom: 1px solid var(--pw-border-color);
}
.wirenps-insight-card table { width: 100%; border-collapse: collapse; }
.wirenps-insight-card th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--pw-muted-color);
    padding: 6px 10px;
    text-align: left;
    border-bottom: 1px solid var(--pw-border-color);
    background: var(--pw-main-background);
}
.wirenps-insight-card th:not(:first-child) { text-align: center; }
.wirenps-charts-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 768px) { .wirenps-charts-wrap { grid-template-columns: 1fr; } }
.wirenps-chart-card {
    background: var(--pw-blocks-background);
    border: 1px solid var(--pw-border-color);
    border-radius: 4px;
    padding: 16px;
}
.wirenps-chart-card h3 {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--pw-muted-color);
    margin: 0 0 14px 0;
}
.wirenps-export-wrap {
    background: var(--pw-blocks-background);
    border: 1px solid var(--pw-border-color);
    border-radius: 4px;
    padding: 20px;
    display: inline-block;
}
.wirenps-export-wrap p { color: var(--pw-muted-color); margin: 0 0 14px 0; font-size: 13px; }
.wirenps-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 18px;
    height: 34px;
    background: var(--pw-button-background);
    color: var(--pw-button-color) !important;
    border: 1px solid transparent;
    border-radius: var(--pw-button-radius);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none !important;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.wirenps-btn:hover {
    background: var(--pw-button-hover-background);
    color: var(--pw-button-hover-color) !important;
    text-decoration: none !important;
}
</style>

<div class="wirenps-cards-row">
    <div class="wirenps-card">
        <div class="wirenps-card-label">NPS Score</div>
        <div class="wirenps-stat-value" style="color:{$npsColor};">{$stats['nps_score']}</div>
        <div class="wirenps-card-desc">{$this->getNPSRating($stats['nps_score'])}</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Total Ratings</div>
        <div class="wirenps-stat-value">{$stats['total']}</div>
        <div class="wirenps-card-desc">All time</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Avg Score</div>
        <div class="wirenps-stat-value">{$stats['avg_score']}</div>
        <div class="wirenps-card-desc">Out of 10</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Promoters</div>
        <div class="wirenps-stat-value" style="color:#16a34a;">{$stats['promoters']}</div>
        <div class="wirenps-card-desc">Score 9–10</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Passives</div>
        <div class="wirenps-stat-value" style="color:#ca8a04;">{$stats['passives']}</div>
        <div class="wirenps-card-desc">Score 7–8</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Detractors</div>
        <div class="wirenps-stat-value" style="color:#dc2626;">{$stats['detractors']}</div>
        <div class="wirenps-card-desc">Score 0–6</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Feedback Rate</div>
        <div class="wirenps-stat-value">{$stats['feedback_rate']}%</div>
        <div class="wirenps-card-desc">Left a comment</div>
    </div>
    <div class="wirenps-card">
        <div class="wirenps-card-label">Last Rating</div>
        <div class="wirenps-stat-value" style="font-size:1.35rem;">{$lastAgo}</div>
        <div class="wirenps-card-desc">Most recent</div>
    </div>
</div>

<div class="wirenps-insights">
    <div class="wirenps-insight-card">
        <h4>Top Pages</h4>
        <table>
            <thead><tr><th>Page</th><th>Ratings</th><th>Avg</th></tr></thead>
            <tbody>{$topPagesHtml}</tbody>
        </table>
    </div>
    <div class="wirenps-insight-card">
        <h4>Monthly Breakdown</h4>
        <table>
            <thead><tr><th>Month</th><th>Ratings</th><th>Promoters</th><th>Detractors</th><th>NPS</th></tr></thead>
            <tbody>{$monthlyHtml}</tbody>
        </table>
    </div>
</div>
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
        $table->setEncodeEntities(false);
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
            $pageTitle = '-';
            if($page && $page->id) {
                $safeTitle = $this->wire('sanitizer')->entities($page->title);
                $pageTitle = "<a href='{$page->editUrl}' target='_blank'>{$safeTitle}</a>";
            }

            $user = $rating['user_id'] ? $this->wire('users')->get($rating['user_id']) : null;
            $userName = 'Guest';
            if($user && $user->id && $user->id != 40) {
                $userEditUrl = $this->wire('config')->urls->admin . 'access/users/edit/?id=' . $user->id;
                $userName = "<a href='{$userEditUrl}'>{$user->name}</a>";
            }
            
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
<div class="wirenps-charts-wrap">

    <div class="wirenps-chart-card">
        <h3>Score Distribution</h3>
        <canvas id="wirenps-dist-chart"></canvas>
    </div>

    <div class="wirenps-chart-card">
        <h3>90-Day Trend</h3>
        <canvas id="wirenps-trend-chart"></canvas>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var distLabels = {$distributionLabels};
    var distData   = {$distributionData};
    var trendLabels = {$trendLabels};
    var trendData   = {$trendData};

    var C_GREEN  = '#16a34a';
    var C_YELLOW = '#ca8a04';
    var C_RED    = '#dc2626';
    var C_MAIN   = '#eb1d61';
    var C_GRID   = 'rgba(0,0,0,0.08)';
    var C_TEXT   = '#888';

    Chart.defaults.font.size = 12;

    var distCtx = document.getElementById('wirenps-dist-chart');
    if(distCtx) {
        new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: distLabels,
                datasets: [{
                    data: distData,
                    backgroundColor: distLabels.map(function(s) {
                        return s <= 6 ? C_RED : s <= 8 ? C_YELLOW : C_GREEN;
                    }),
                    borderWidth: 0,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: C_TEXT }, grid: { color: C_GRID } },
                    y: { beginAtZero: true, ticks: { color: C_TEXT }, grid: { color: C_GRID } }
                }
            }
        });
    }

    var trendCtx = document.getElementById('wirenps-trend-chart');
    if(trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Avg Score',
                    data: trendData,
                    borderColor: C_MAIN,
                    backgroundColor: 'rgba(235,29,97,0.08)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: C_MAIN,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: C_TEXT }, grid: { color: C_GRID } },
                    y: { beginAtZero: false, min: 0, max: 10, ticks: { color: C_TEXT }, grid: { color: C_GRID } }
                }
            }
        });
    }
})();
</script>
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
<div class="wirenps-export-wrap">
    <p>Download all ratings data in CSV format for further analysis.</p>
    <a href="{$exportUrl}" class="wirenps-btn" download>
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M10 14l-5-5h3V4h4v5h3l-5 5z"/><path d="M4 16h12v2H4v-2z"/></svg>
        Download CSV
    </a>
</div>
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
     * Get NPS hex color
     */
    protected function getNPSHexColor($score) {
        if($score >= 50) return '#16a34a';
        if($score >= 0)  return '#ca8a04';
        return '#dc2626';
    }

    /**
     * Get NPS color class (design system)
     */
    protected function getNPSColorClass($score) {
        if($score >= 50) return 'wirenps-color-nps-good';
        if($score >= 0)  return 'wirenps-color-nps-ok';
        return 'wirenps-color-nps-bad';
    }

    /**
     * @deprecated
     */
    protected function getNPSColor($score) {
        return $this->getNPSHexColor($score);
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