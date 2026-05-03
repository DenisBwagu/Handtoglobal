<?php
// Direct Database Setup Execution
echo "Starting HandToGlobal Database Setup...\n";

try {
    // Step 1: Connect to MySQL
    echo "Step 1: Connecting to MySQL on port 3307...\n";
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);;
    echo "✓ MySQL connection successful!\n";

    // Step 2: Clean existing databases
    echo "\nStep 2: Cleaning existing databases...\n";
    $databases = ['globalhand', 'handtoglobal'];
    
    foreach ($databases as $db_name) {
        try {
            $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
            if ($stmt->rowCount() > 0) {
                echo "Found database: $db_name - Cleaning...\n";
                
                $pdo->exec("USE `$db_name`");
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($tables)) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        echo "  Dropped table: $table\n";
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                }
                
                $pdo->exec("DROP DATABASE IF EXISTS `$db_name`");
                echo "✓ Cleaned and dropped database: $db_name\n";
            } else {
                echo "Database $db_name doesn't exist\n";
            }
        } catch (Exception $e) {
            echo "Error cleaning $db_name: " . $e->getMessage() . "\n";
        }
    }

    // Step 3: Create fresh database
    echo "\nStep 3: Creating fresh database...\n";
    $pdo->exec("CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE handtoglobal");
    echo "✓ Created fresh handtoglobal database\n";

    // Step 4: Import schema
    echo "\nStep 4: Importing database schema...\n";
    $sqlFile = __DIR__ . '/db.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("db.sql file not found");
    }
    
    $sql = file_get_contents($sqlFile);
    $sql = str_replace('`globalhand`', '`handtoglobal`', $sql);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    foreach ($statements as $statement) {
        if (empty($statement) || preg_match('/^(--|\/\*|#)/', $statement)) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            if (preg_match('/CREATE TABLE `?(\w+)/', $statement, $matches)) {
                echo "  Created table: " . $matches[1] . "\n";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "  Warning: " . substr($e->getMessage(), 0, 80) . "...\n";
            }
        }
    }
    
    echo "✓ Executed $executed SQL statements successfully!\n";

    // Step 5: Create admin account
    echo "\nStep 5: Creating admin account...\n";
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $stmt->execute(['admin@handtoglobal.com', $hashedPassword]);
    echo "✓ Admin account created: admin@handtoglobal.com\n";

    // Step 6: Verification
    echo "\nStep 6: Verifying installation...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Found " . count($tables) . " tables\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks");
    $stmt->execute();
    $taskCount = $stmt->fetch()['count'];
    echo "✓ Created $taskCount sample tasks\n";
    
    echo "\n🎉 HandToGlobal setup completed successfully!\n";
    echo "\nLogin Credentials:\n";
    echo "Email: admin@handtoglobal.com\n";
    echo "Password: admin123\n";
    echo "Admin Panel: http://localhost/globalhand/admin/\n";
    echo "User Panel: http://localhost/globalhand/\n";

} catch (Exception $e) {
    echo "\n❌ Setup Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Ensure XAMPP MySQL is running on port 3307\n";
    echo "2. Check that db.sql file exists\n";
    echo "3. Try restarting MySQL service\n";
}
?>
