<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>
<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->prepare("
        UPDATE suppliers SET
            supplier_name=?,
            contact_person=?,
            phone=?,
            email=?,
            address1=?,
            address2=?,
            city=?,
            pincode=?,
            state=?,
            gstin=?
        WHERE id=?
    ")->execute([
        $_POST['supplier_name'],
        $_POST['contact_person'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['address1'],
        $_POST['address2'],
        $_POST['city'],
        $_POST['pincode'],
        $_POST['state'],
        $_POST['gstin'],
        $id
    ]);

    header("Location: index.php");
    exit;
}
?>

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
    citySelect.innerHTML = '<option value="">Loading...</option>';

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select City --</option>';
        return;
    }

    fetch('../api/get_cities.php?state=' + encodeURIComponent(stateName))
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';
            const cities = data.cities || [];
            if (Array.isArray(cities) && cities.length > 0) {
                cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.city_name;
                    option.textContent = city.city_name;
                    if (selectedCity && city.city_name === selectedCity) {
                        option.selected = true;
                    }
                    citySelect.appendChild(option);
                });
            } else if (selectedCity) {
                // Keep current city if no cities found
                const option = document.createElement('option');
                option.value = selectedCity;
                option.textContent = selectedCity;
                option.selected = true;
                citySelect.appendChild(option);
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            citySelect.innerHTML = '<option value="">-- Select City --</option>';
            if (selectedCity) {
                const option = document.createElement('option');
                option.value = selectedCity;
                option.textContent = selectedCity;
                option.selected = true;
                citySelect.appendChild(option);
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.querySelector('select[name="state"]');
    const currentCity = '<?= htmlspecialchars($supplier['city'] ?? '') ?>';

    // Load cities for the current state on page load
    if (stateSelect.value) {
        loadCities(stateSelect.value, currentCity);
    }

    stateSelect.addEventListener('change', function() {
        loadCities(this.value);
    });
});
</script>
<div class="content">
    <h1>Edit Supplier</h1>
    <form method="post">
        Supplier Code<br>
        <input value="<?= htmlspecialchars($supplier['supplier_code']) ?>" readonly><br><br>

        Name<br>
        <input name="supplier_name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>" required><br><br>

        Contact Person<br>
        <input name="contact_person" value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>"><br><br>

        Phone<br>
        <input name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>"><br><br>

        Email<br>
        <input name="email" type="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>"><br><br>

        Address Line 1<br>
        <input name="address1" value="<?= htmlspecialchars($supplier['address1'] ?? '') ?>"><br><br>

        Address Line 2<br>
        <input name="address2" value="<?= htmlspecialchars($supplier['address2'] ?? '') ?>"><br><br>

        State<br>
        <select name="state" id="state_select" onchange="loadCities(this.value)">
            <option value="">-- Select State --</option>
            <?php
            try {
                $states = $pdo->query("SELECT id, state_name FROM states WHERE is_active = 1 ORDER BY state_name")->fetchAll();
                foreach ($states as $state) {
                    $selected = ($supplier['state'] === $state['state_name']) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($state['state_name']) . '" ' . $selected . '>' . htmlspecialchars($state['state_name']) . '</option>';
                }
            } catch (PDOException $e) {
                // Table might not exist
            }
            ?>
        </select><br><br>

        City<br>
        <select id="city_select" name="city">
            <option value="">-- Select City --</option>
            <?php if (!empty($supplier['city'])): ?>
            <option value="<?= htmlspecialchars($supplier['city']) ?>" selected><?= htmlspecialchars($supplier['city']) ?></option>
            <?php endif; ?>
        </select><br><br>

        Pincode<br>
        <input name="pincode" value="<?= htmlspecialchars($supplier['pincode'] ?? '') ?>" maxlength="10"><br><br>

        GSTIN<br>
        <input name="gstin" value="<?= htmlspecialchars($supplier['gstin'] ?? '') ?>" placeholder="15-character GSTIN"><br><br>

        <button>Update</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
