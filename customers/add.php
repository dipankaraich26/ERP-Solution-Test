<?php
include "../db.php";
include "../includes/sidebar.php";

/* --- Generate next Customer ID --- */
$max = $pdo->query("
    SELECT MAX(CAST(SUBSTRING(customer_id,6) AS UNSIGNED))
    FROM customers
    WHERE customer_id LIKE 'CUST-%'
")->fetchColumn();

$next = $max ? ((int)$max + 1) : 1;
$customer_id = 'CUST-' . $next;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->prepare("
        INSERT INTO customers 
        (customer_id, company_name, customer_name, contact, address, state, gstin)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $customer_id,
        $_POST['company_name'],
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['address'],
        $_POST['state'],
        $_POST['gstin']
    ]);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Customer</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
<div class="content">
    <h1>Add Customer</h1>

    <form method="post">

        Customer ID<br>
        <input value="<?= htmlspecialchars($customer_id) ?>" readonly><br><br>

        Company Name<br>
        <input name="company_name" required><br><br>

        Customer Name<br>
        <input name="customer_name" required><br><br>

        Contact<br>
        <input name="contact"><br><br>

        Address<br>
        <textarea name="address" rows="3"></textarea><br><br>

        State<br>
        <input name="state"><br><br>

        GSTIN<br>
        <input name="gstin" placeholder="15-character GSTIN"><br><br>

        <button>Add Customer</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>

    </form>
</div>
</body>
</html>
