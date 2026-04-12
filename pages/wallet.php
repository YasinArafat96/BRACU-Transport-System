<?php
/**
 * Wallet page for University Bus Booking System
 * Manages user wallet and transactions
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Redirect admins away from wallet page
if (isAdmin($pdo, $_SESSION['id'])) {
    $_SESSION['info'] = "Wallet functionality is for students only.";
    header("Location: admin.php");
    exit;
}

$user_id = $_SESSION['id'];

// SIMPLE AUTO TOP-UP - Direct POST handling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Check if this is a top-up request
    if (isset($_POST['topup_amount']) && isset($_POST['topup_method'])) {
        $amount = (float)$_POST['topup_amount'];
        $payment_method = $_POST['topup_method'];
        
        if ($amount <= 0) {
            $_SESSION['error'] = "Invalid amount";
            header("Location: wallet.php");
            exit();
        }
        
        // DIRECTLY UPDATE BALANCE - no complex validation
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update wallet balance - SIMPLE AND DIRECT
            $stmt = $pdo->prepare("UPDATE wallet SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$amount, $user_id]);
            
            // Record transaction
            $desc = "Added $" . $amount . " via " . $payment_method;
            $trans_stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
            $trans_stmt->execute([$user_id, $amount, $desc]);
            
            $pdo->commit();
            
            // Update session balance
            $_SESSION['balance'] = $_SESSION['balance'] + $amount;
            
            $_SESSION['success'] = "$" . number_format($amount, 2) . " added to your wallet via " . $payment_method . "!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to add funds. Please try again.";
        }
        
        header("Location: wallet.php");
        exit();
    }
}

// Get wallet balance
$balance = refreshWalletBalance($pdo, $user_id);

// Get transaction history
$transactions = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_time DESC LIMIT 10");
$transactions->execute([$user_id]);
$transactions = $transactions->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">My Wallet</h1>

<!-- Current Balance Card -->
<div class="card" style="text-align: center; background: linear-gradient(135deg, var(--primary), var(--dark)); color: white;">
    <h2 style="color: white;"><i class="fas fa-wallet"></i> Current Balance</h2>
    <p style="font-size: 4rem; font-weight: bold; margin: 20px 0;">$<?php echo number_format($balance, 2); ?></p>
</div>

<!-- SIMPLE AUTO TOP-UP CARD -->
<div class="card" style="border: 2px solid var(--secondary);">
    <h2 style="color: var(--secondary);"><i class="fas fa-bolt"></i> Quick Top-Up</h2>
    <p>Select amount and payment method - funds added instantly!</p>
    
    <!-- Amount Selector -->
    <div style="margin: 30px 0;">
        <h3>1. Choose Amount</h3>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="amount-btn" onclick="setAmount(10)">$10</button>
            <button type="button" class="amount-btn" onclick="setAmount(20)">$20</button>
            <button type="button" class="amount-btn" onclick="setAmount(50)">$50</button>
            <button type="button" class="amount-btn" onclick="setAmount(100)">$100</button>
            <button type="button" class="amount-btn" onclick="setAmount(200)">$200</button>
            <button type="button" class="amount-btn" onclick="setAmount(500)">$500</button>
        </div>
        
        <div style="margin-top: 20px;">
            <label for="custom_amount">Or enter custom amount:</label>
            <input type="number" id="custom_amount" class="form-control" 
                   min="5" step="5" placeholder="Enter amount (min $5)" style="width: 200px; margin: 10px auto;">
        </div>
        
        <div style="margin-top: 20px; font-size: 1.2rem;">
            Selected Amount: <strong id="selected_amount_display">$0</strong>
            <input type="hidden" id="selected_amount" value="0">
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div style="margin: 30px 0;">
        <h3>2. Choose Payment Method</h3>
        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
            
            <!-- bKash -->
            <form method="post" class="payment-form" id="bkash_form">
                <input type="hidden" name="topup_amount" id="bkash_amount" value="0">
                <input type="hidden" name="topup_method" value="bKash">
                <button type="submit" class="payment-btn bkash" onclick="return validateAmount('bkash_amount')">
                    <i class="fas fa-mobile-alt"></i>
                    <span>bKash</span>
                </button>
            </form>
            
            <!-- Nagad -->
            <form method="post" class="payment-form" id="nagad_form">
                <input type="hidden" name="topup_amount" id="nagad_amount" value="0">
                <input type="hidden" name="topup_method" value="Nagad">
                <button type="submit" class="payment-btn nagad" onclick="return validateAmount('nagad_amount')">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Nagad</span>
                </button>
            </form>
            
            <!-- Rocket -->
            <form method="post" class="payment-form" id="rocket_form">
                <input type="hidden" name="topup_amount" id="rocket_amount" value="0">
                <input type="hidden" name="topup_method" value="Rocket">
                <button type="submit" class="payment-btn rocket" onclick="return validateAmount('rocket_amount')">
                    <i class="fas fa-rocket"></i>
                    <span>Rocket</span>
                </button>
            </form>
            
            <!-- Credit Card -->
            <form method="post" class="payment-form" id="card_form">
                <input type="hidden" name="topup_amount" id="card_amount" value="0">
                <input type="hidden" name="topup_method" value="Credit Card">
                <button type="submit" class="payment-btn card" onclick="return validateAmount('card_amount')">
                    <i class="fas fa-credit-card"></i>
                    <span>Card</span>
                </button>
            </form>
            
        </div>
    </div>
    
    <div class="notification info" style="background: #e8f4fd; color: #0d47a1; padding: 15px; border-radius: 8px;">
        <i class="fas fa-info-circle"></i> 
        <strong>Demo Mode:</strong> Just select amount and click any payment button. Money will be added instantly!
    </div>
</div>

<!-- Transaction History -->
<div class="card">
    <h2><i class="fas fa-history"></i> Recent Transactions</h2>
    
    <?php if (count($transactions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo date("M j, Y g:i A", strtotime($transaction['transaction_time'])); ?></td>
                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                    <td class="<?php echo $transaction['type'] == 'credit' ? 'text-success' : 'text-danger'; ?>" style="font-weight: bold;">
                        <?php echo $transaction['type'] == 'credit' ? '+' : '-'; ?>
                        $<?php echo number_format($transaction['amount'], 2); ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $transaction['type'] == 'credit' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ucfirst($transaction['type']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-exchange-alt fa-4x" style="color: #ddd; margin-bottom: 15px;"></i>
            <p>No transactions yet. Use the Quick Top-Up above to add funds!</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Set amount from preset buttons
function setAmount(amount) {
    document.getElementById('selected_amount').value = amount;
    document.getElementById('selected_amount_display').textContent = '$' + amount;
    document.getElementById('custom_amount').value = amount;
    
    // Update all hidden inputs
    document.getElementById('bkash_amount').value = amount;
    document.getElementById('nagad_amount').value = amount;
    document.getElementById('rocket_amount').value = amount;
    document.getElementById('card_amount').value = amount;
}

// Custom amount input
document.getElementById('custom_amount').addEventListener('input', function() {
    let amount = this.value;
    if (amount && amount >= 5) {
        document.getElementById('selected_amount').value = amount;
        document.getElementById('selected_amount_display').textContent = '$' + amount;
        
        // Update all hidden inputs
        document.getElementById('bkash_amount').value = amount;
        document.getElementById('nagad_amount').value = amount;
        document.getElementById('rocket_amount').value = amount;
        document.getElementById('card_amount').value = amount;
    } else {
        document.getElementById('selected_amount_display').textContent = '$0';
    }
});

// Validate amount before submit
function validateAmount(inputId) {
    let amount = document.getElementById(inputId).value;
    if (!amount || amount < 5) {
        alert('Please select an amount (minimum $5)');
        return false;
    }
    return true;
}

// Initialize with default amount
setAmount(10);
</script>

<style>
.amount-btn {
    background: white;
    border: 2px solid var(--primary);
    color: var(--primary);
    padding: 15px 25px;
    font-size: 1.2rem;
    font-weight: bold;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 80px;
}

.amount-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.payment-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px 30px;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 120px;
    color: white;
}

.payment-btn i {
    font-size: 2rem;
}

.payment-btn.bkash {
    background: #e2136e;
}
.payment-btn.nagad {
    background: #f15a29;
}
.payment-btn.rocket {
    background: #702f8b;
}
.payment-btn.card {
    background: var(--primary);
}

.payment-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    filter: brightness(1.1);
}

.payment-form {
    display: inline-block;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}
.status-active {
    background-color: var(--secondary);
    color: white;
}
.status-inactive {
    background-color: var(--danger);
    color: white;
}
.text-success {
    color: var(--secondary);
}
.text-danger {
    color: var(--danger);
}
.notification.info {
    background: #e3f2fd;
    color: #0d47a1;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #2196f3;
}

@media (max-width: 768px) {
    .amount-btn {
        padding: 10px 15px;
        font-size: 1rem;
    }
    .payment-btn {
        padding: 15px 20px;
        min-width: 100px;
    }
}
</style>

<?php
require_once '../includes/footer.php';
?>