<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();
$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers");

/* =========================
   HANDLE ADD SUPPLIER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = $_POST['supplier_code'];
    $name = $_POST['supplier_name'];
    $contact = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    

    try {
        $pdo->prepare("
            INSERT INTO suppliers
            (supplier_code, supplier_name, contact_person, phone, email, address)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$code, $name, $contact, $phone, $email, $address]);

        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $error = "Supplier code must be unique";
    }
}

/* =========================
   SUPPLIER LIST
========================= */
$suppliers = $pdo->query("
    SELECT * FROM suppliers
    ORDER BY supplier_name
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Suppliers</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>
</body>
</html>


<body>

<div class="content">
    <h1>Suppliers</h1>

    <?php if (!empty($error)): ?>
        <script>alert("<?= htmlspecialchars($error) ?>");</script>
    <?php endif; ?>

    <!-- ADD SUPPLIER FORM -->
    <form method="post">
        Code <input name="supplier_code" required>
        Name <input name="supplier_name" required>
        Contact <input name="contact_person">
        Phone <input name="phone">
        Email <input name="email">
        Address <input name="address">
        <br><br>
        <button>Add Supplier</button>
    </form>

    <hr>

    <!-- SUPPLIER TABLE -->
    <table border="1" cellpadding="8">
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Email</th>
            <th>Action</th>
            
        </tr>

        <?php while ($s = $suppliers->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($s['supplier_code']) ?></td>
            <td><?= htmlspecialchars($s['supplier_name']) ?></td>
            <td><?= htmlspecialchars($s['contact_person']) ?></td>
            <td><?= htmlspecialchars($s['phone']) ?></td>
            <td><?= htmlspecialchars($s['address']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td>
                <a class="btn btn-secondary" href="edit.php?id=<?= $s['id'] ?>">Edit</a> |
                <a class="btn btn-secondary" href="delete.php?id=<?= $s['id'] ?>"
                   onclick="return confirm('Delete supplier?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
