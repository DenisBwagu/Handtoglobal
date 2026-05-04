<?php
require_once 'config.php';

$conn = getConnection();

echo "=== Seeding Tasks Database ===\n\n";

// Define levels and task templates
$levels = ['Bronze', 'Sliver', 'Gold', 'VIP 1'];

$task_templates = [
    'Bronze' => [
        ['Simple Brand Review', 'Review basic brand information and provide feedback'],
        ['Brand Name Check', 'Check brand name for accuracy and completeness'],
        ['Basic Brand Analysis', 'Analyze basic brand elements and structure'],
        ['Brand Overview', 'Provide basic overview of brand characteristics'],
        ['Brand Information Review', 'Review and verify brand information'],
        ['Brand Basic Check', 'Perform basic brand verification'],
        ['Brand Simple Analysis', 'Simple analysis of brand components'],
        ['Brand Basic Review', 'Basic review of brand elements'],
        ['Brand Initial Check', 'Initial brand verification process'],
        ['Brand Primary Review', 'Primary brand review and assessment']
    ],
    'Sliver' => [
        ['Brand Visibility', 'Assess brand visibility and market presence'],
        ['Brand Recognition', 'Evaluate brand recognition and awareness'],
        ['Brand Market Analysis', 'Analyze brand position in market'],
        ['Brand Competitor Review', 'Review brand against competitors'],
        ['Brand Performance', 'Assess brand performance metrics'],
        ['Brand Strategy Review', 'Review brand strategy and positioning'],
        ['Brand Impact Analysis', 'Analyze brand impact on target audience'],
        ['Brand Reach Assessment', 'Assess brand reach and coverage'],
        ['Brand Engagement Review', 'Review brand engagement levels'],
        ['Brand Growth Analysis', 'Analyze brand growth patterns']
    ],
    'Gold' => [
        ['Product Quality Review', 'Comprehensive product quality assessment'],
        ['Brand Trust Review', 'Evaluate brand trust and credibility'],
        ['Brand Loyalty Analysis', 'Analyze brand loyalty factors'],
        ['Brand Value Assessment', 'Assess overall brand value'],
        ['Brand Reputation Review', 'Review brand reputation and perception'],
        ['Brand Consistency Check', 'Check brand consistency across channels'],
        ['Brand Innovation Review', 'Review brand innovation and adaptation'],
        ['Brand Customer Experience', 'Evaluate customer experience with brand'],
        ['Brand Competitive Advantage', 'Assess competitive advantages'],
        ['Brand Strategic Review', 'Strategic brand review and planning']
    ],
    'VIP 1' => [
        ['Mixed Brands', 'Review multiple brands for comprehensive analysis'],
        ['Premium Brand Review', 'Premium brand assessment and evaluation'],
        ['Brand Portfolio Review', 'Review entire brand portfolio'],
        ['Brand Ecosystem Analysis', 'Analyze brand ecosystem and relationships'],
        ['Brand Integration Review', 'Review brand integration strategies'],
        ['Brand Global Assessment', 'Global brand assessment and analysis'],
        ['Brand Excellence Review', 'Excellence in brand management review'],
        ['Brand Leadership Review', 'Brand leadership and positioning review'],
        ['Brand Innovation Excellence', 'Innovation excellence in branding'],
        ['Brand Strategic Excellence', 'Strategic excellence in brand management']
    ]
];

// First, clear existing tasks to ensure clean state
try {
    $stmt = $conn->prepare("DELETE FROM tasks");
    $stmt->execute();
    echo "Cleared existing tasks\n";
} catch(PDOException $e) {
    echo "Error clearing tasks: " . $e->getMessage() . "\n";
}

// Seed 40 tasks per level (160 total)
foreach ($levels as $level) {
    echo "\nSeeding $level tasks (40 total):\n";
    
    $templates = $task_templates[$level];
    $template_count = count($templates);
    
    for ($i = 1; $i <= 40; $i++) {
        // Cycle through templates and add variety
        $template_index = ($i - 1) % $template_count;
        $base_title = $templates[$template_index][0];
        $base_description = $templates[$template_index][1];
        
        // Add numbering for uniqueness
        if ($i <= $template_count) {
            $title = "$i. $base_title";
        } else {
            $cycle_num = floor(($i - 1) / $template_count) + 1;
            $title = "$i. $base_title " . chr(64 + $cycle_num);
        }
        
        // Vary description slightly
        $description = $base_description . " - Task #$i for $level level";
        
        // Set type based on level
        $type = 'Name_items';
        if ($level === 'Gold' || $level === 'VIP 1') {
            $type = 'Premium_review';
        }
        
        // Set reward based on level
        $rewards = [
            'Bronze' => 5.00,
            'Sliver' => 10.00,
            'Gold' => 25.00,
            'VIP 1' => 50.00
        ];
        $reward = $rewards[$level];
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO tasks (title, description, level, type, reward, active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$title, $description, $level, $type, $reward]);
            echo "  Created: $title\n";
        } catch(PDOException $e) {
            echo "  Error creating task: " . $e->getMessage() . "\n";
        }
    }
}

// Verify the results
echo "\n=== Verification ===\n";
try {
    $stmt = $conn->prepare("SELECT level, COUNT(*) as count FROM tasks GROUP BY level ORDER BY level");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $total = 0;
    foreach ($results as $result) {
        echo "{$result['level']}: {$result['count']} tasks\n";
        $total += $result['count'];
    }
    echo "Total: $total tasks\n";
    
    // Show sample tasks per level
    echo "\n=== Sample Tasks ===\n";
    foreach ($levels as $level) {
        $stmt = $conn->prepare("SELECT id, title, type FROM tasks WHERE level = ? ORDER BY id LIMIT 3");
        $stmt->execute([$level]);
        $sample_tasks = $stmt->fetchAll();
        
        echo "\n$level samples:\n";
        foreach ($sample_tasks as $task) {
            echo "  {$task['id']}. {$task['title']} | {$task['type']}\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Error verifying: " . $e->getMessage() . "\n";
}

echo "\n=== Task Seeding Complete ===\n";
echo "Ready to update tasks.php page\n";
?>
