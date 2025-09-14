<?php
include 'db.php';

echo "🔧 Updating tbl_gamification table structure...\n";

// Add missing columns to existing table
$alterQueries = [
    "ALTER TABLE tbl_gamification ADD COLUMN longest_streak INT DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN weekly_streak INT DEFAULT 0", 
    "ALTER TABLE tbl_gamification ADD COLUMN monthly_streak INT DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN level INT DEFAULT 1",
    "ALTER TABLE tbl_gamification ADD COLUMN achievements JSON",
    "ALTER TABLE tbl_gamification ADD COLUMN last_attendance_date DATE",
    "ALTER TABLE tbl_gamification ADD COLUMN perfect_months INT DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN early_arrivals INT DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN overtime_hours DECIMAL(5,2) DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN team_contributions INT DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN wellness_score DECIMAL(3,1) DEFAULT 0",
    "ALTER TABLE tbl_gamification ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($alterQueries as $query) {
    try {
        $conn->exec($query);
        echo "✓ Applied: " . substr($query, 0, 50) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "- Column already exists: " . substr($query, 0, 50) . "...\n";
        } else {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

// Initialize default values for existing records
echo "\n📊 Updating existing records with default values...\n";
$conn->exec("UPDATE tbl_gamification SET level = 1 WHERE level IS NULL OR level = 0");
$conn->exec("UPDATE tbl_gamification SET longest_streak = streak WHERE longest_streak IS NULL OR longest_streak < streak");

echo "✅ Table structure updated successfully!\n";

// Show final structure
$stmt = $conn->query('DESCRIBE tbl_gamification');
echo "\n📋 Final table structure:\n";
while($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
