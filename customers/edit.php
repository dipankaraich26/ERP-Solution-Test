<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

$customer_id = $_GET['customer_id'] ?? null;
$errors = [];

if (!$customer_id) {
    setModal("Failed to edit customer", "Customer not specified");
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id=?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    setModal("Failed to edit customer", "Customer not found");
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        UPDATE customers
        SET company_name=?, customer_name=?, contact=?, email=?, address1=?, address2=?, city=?, pincode=?, state=?, gstin=?
        WHERE customer_id=?
    ");
    $stmt->execute([
        $_POST['company_name'],
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['email'],
        $_POST['address1'],
        $_POST['address2'],
        $_POST['city'],
        $_POST['pincode'],
        $_POST['state'],
        $_POST['gstin'],
        $customer_id
    ]);

    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Customer</title>
    <link rel="stylesheet" href="/assets/style.css">
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

function loadCities(stateName, selectedCity = null) {
    const citySelect = document.getElementById('city_select');

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select City --</option>';
        return;
    }

    fetch(`/api/get_cities.php?state=${encodeURIComponent(stateName)}`)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';
            data.forEach(city => {
                const option = document.createElement('option');
                option.value = city.city_name;
                option.textContent = city.city_name;
                if (selectedCity && city.city_name === selectedCity) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading cities:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.querySelector('select[name="state"]');
    const currentCity = '<?= htmlspecialchars($customer['city'] ?? '') ?>';

    // Load cities for the current state on page load
    if (stateSelect.value) {
        loadCities(stateSelect.value, currentCity);
    }

    stateSelect.addEventListener('change', function() {
        loadCities(this.value);
    });
});
</script>
<body>

<div class="content">
    <h1>Edit Customer</h1>

    <form method="post">
        Customer ID<br>
        <input value="<?= htmlspecialchars($customer['customer_id']) ?>" readonly><br><br>

        Company Name<br>
        <input name="company_name" value="<?= htmlspecialchars($customer['company_name']) ?>" required><br><br>

        Customer Name<br>
        <input name="customer_name" value="<?= htmlspecialchars($customer['customer_name']) ?>" required><br><br>

        Contact<br>
        <input name="contact" value="<?= htmlspecialchars($customer['contact'] ?? '') ?>"><br><br>

        Email<br>
        <input name="email" type="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>"><br><br>

        Address Line 1<br>
        <input name="address1" value="<?= htmlspecialchars($customer['address1'] ?? '') ?>"><br><br>

        Address Line 2<br>
        <input name="address2" value="<?= htmlspecialchars($customer['address2'] ?? '') ?>"><br><br>

        City<br>
        <select id="city_select" name="city">
            <option value="">-- Select City --</option>
            <option value="<?= htmlspecialchars($customer['city'] ?? '') ?>" selected><?= htmlspecialchars($customer['city'] ?? '') ?></option>
        </select><br><br>

        Pincode<br>
        <input name="pincode" value="<?= htmlspecialchars($customer['pincode'] ?? '') ?>" maxlength="10"><br><br>

        State<br>
        <select name="state">
            <option value="">-- Select State --</option>
            <?php
            $states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
            foreach ($states as $state) {
                $selected = ($customer['state'] === $state['state_name']) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($state['state_name']) . '" ' . $selected . '>' . htmlspecialchars($state['state_name']) . '</option>';
            }
            ?>
        </select><br><br>

        GSTIN<br>
        <input name="gstin" value="<?= htmlspecialchars($customer['gstin'] ?? '') ?>" placeholder="15-character GSTIN"><br><br>

        <button type="submit">Update</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

</body>
</html>
