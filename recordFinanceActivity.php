<?php
require_once __DIR__ . '/config.php';

/**
 * Records a financial activity in the finance_activities table
 * This function should be called whenever a balance change occurs in the system
 * 
 * @param int $user_id The user ID who is affected by this activity
 * @param int|null $admin_id The admin ID who performed this action (if applicable)
 * @param string $type The type of activity (deposit_credit, withdrawal_debit, bonus_credit, manual_debit, etc.)
 * @param string $category The category of the activity (deposit, withdrawal, bonus, deduction, task_reward, combo_reward, etc.)
 * @param float $amount The amount of the transaction (always positive, type determines credit/debit)
 * @param string $reason The reason for this transaction
 * @param float $balance_after The user's balance after this transaction
 * @param string $source_table The source table where this transaction originated
 * @param int|null $source_id The ID from the source table
 * @return bool True on success, false on failure
 */
function recordFinanceActivity($user_id, $admin_id, $type, $category, $amount, $reason, $balance_after, $source_table, $source_id = null) {
    try {
        $conn = getConnection();
        if (function_exists('htg_table_is_usable') && !htg_table_is_usable('finance_activities')) {
            return false;
        }
        
        // Validate inputs
        if (!is_numeric($user_id) || $user_id <= 0) {
            error_log("recordFinanceActivity: Invalid user_id: $user_id");
            return false;
        }
        
        if (!is_numeric($amount) || $amount <= 0) {
            error_log("recordFinanceActivity: Invalid amount: $amount");
            return false;
        }
        
        if (!is_numeric($balance_after)) {
            error_log("recordFinanceActivity: Invalid balance_after: $balance_after");
            return false;
        }
        
        // Validate type
        $valid_types = [
            'deposit_credit', 'deposit_debit', 
            'withdrawal_debit', 'withdrawal_credit',
            'task_reward_credit', 'task_reward_debit',
            'bonus_credit', 'bonus_debit',
            'combo_credit', 'combo_debit',
            'invitation_credit', 'invitation_debit',
            'manual_credit', 'manual_debit'
        ];
        
        if (!in_array($type, $valid_types)) {
            error_log("recordFinanceActivity: Invalid type: $type");
            return false;
        }
        
        // Insert the finance activity record
        $stmt = $conn->prepare("
            INSERT INTO finance_activities 
            (user_id, admin_id, type, category, amount, reason, balance_after, source_table, source_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $admin_id,
            $type,
            $category,
            $amount,
            $reason,
            $balance_after,
            $source_table,
            $source_id
        ]);
        
        return true;
        
    } catch(PDOException $e) {
        error_log("recordFinanceActivity: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates user balance and records the financial activity in a transaction
 * This is a safer way to update balances as it ensures both operations succeed or fail together
 * 
 * @param int $user_id The user ID
 * @param float $new_balance The new balance to set
 * @param float $amount_change The amount of change (positive for credit, negative for debit)
 * @param string $type The activity type
 * @param string $category The activity category
 * @param string $reason The reason for the change
 * @param string $source_table The source table
 * @param int|null $source_id The source ID
 * @param int|null $admin_id The admin ID performing the action
 * @return bool True on success, false on failure
 */
function updateUserBalanceWithActivity($user_id, $new_balance, $amount_change, $type, $category, $reason, $source_table, $source_id = null, $admin_id = null) {
    $conn = getConnection();
    
    try {
        $conn->beginTransaction();
        
        // Update user balance
        $stmt = $conn->prepare("UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);
        
        // Record the financial activity
        $absolute_amount = abs($amount_change);
        if (!recordFinanceActivity($user_id, $admin_id, $type, $category, $absolute_amount, $reason, $new_balance, $source_table, $source_id)) {
            throw new Exception("Failed to record finance activity");
        }
        
        $conn->commit();
        return true;
        
    } catch(Exception $e) {
        $conn->rollback();
        error_log("updateUserBalanceWithActivity: " . $e->getMessage());
        return false;
    }
}

echo "Finance activity helper functions loaded successfully.\n";
?>
