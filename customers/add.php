<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

/* --- Generate next Customer ID --- */
$max = $pdo->query("
    SELECT MAX(CAST(SUBSTRING(customer_id,6) AS UNSIGNED))
    FROM customers
    WHERE customer_id LIKE 'CUST-%'
")->fetchColumn();

$next = $max ? ((int)$max + 1) : 1;
$customer_id = 'CUST-' . $next;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $contact = $_POST['contact'];

    // Check if customer with same phone number already exists
    if (!empty($contact)) {
        $existingCustomer = $pdo->prepare("SELECT customer_id, customer_name FROM customers WHERE contact = ?");
        $existingCustomer->execute([$contact]);
        $existing = $existingCustomer->fetch();

        if ($existing) {
            setModal("Duplicate Customer", "A customer with phone number '$contact' already exists: " . $existing['customer_name'] . " (" . $existing['customer_id'] . ")");
            header("Location: add.php");
            exit;
        }
    }

    $pdo->prepare("
        INSERT INTO customers
        (customer_id, company_name, customer_name, contact, email, address1, address2, city, pincode, state, gstin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $customer_id,
        $_POST['company_name'],
        $_POST['customer_name'],
        $_POST['contact'],
        $_POST['email'],
        $_POST['address1'],
        $_POST['address2'],
        $_POST['city'],
        $_POST['pincode'],
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

<script>
function loadCities(stateName) {
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
                citySelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading cities:', error));
}

function checkExistingCustomer() {
    const contact = document.querySelector('input[name="contact"]').value;
    const lookupResult = document.getElementById('lookup_result');

    if (!contact || contact.length < 10) {
        lookupResult.innerHTML = '';
        return;
    }

    fetch(`/api/check_customer.php?contact=${encodeURIComponent(contact)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                lookupResult.innerHTML = `
                    <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107;">
                        <strong>Customer Found:</strong> ${data.customer_name} (${data.customer_id})<br>
                        <button type="button" class="btn btn-secondary" onclick="loadExistingCustomer('${contact}')" style="margin-top: 5px;">Load Existing Data</button>
                    </div>
                `;
            } else {
                lookupResult.innerHTML = '<div style="color: #28a745; padding: 5px;">✓ No duplicate found</div>';
            }
        })
        .catch(error => {
            console.error('Error checking customer:', error);
            lookupResult.innerHTML = '';
        });
}

function loadExistingCustomer(contact) {
    fetch(`/api/get_customer.php?contact=${encodeURIComponent(contact)}`)
        .then(response => response.json())
        .then(data => {
            if (data) {
                document.querySelector('input[name="company_name"]').value = data.company_name || '';
                document.querySelector('input[name="customer_name"]').value = data.customer_name || '';
                document.querySelector('input[name="email"]').value = data.email || '';
                document.querySelector('input[name="address1"]').value = data.address1 || '';
                document.querySelector('input[name="address2"]').value = data.address2 || '';
                document.querySelector('input[name="pincode"]').value = data.pincode || '';
                document.querySelector('input[name="gstin"]').value = data.gstin || '';

                // Set state
                const stateSelect = document.querySelector('select[name="state"]');
                if (data.state) {
                    stateSelect.value = data.state;
                    // Load cities for this state
                    loadCities(data.state);
                    // Set city after cities are loaded
                    setTimeout(() => {
                        document.getElementById('city_select').value = data.city || '';
                    }, 500);
                }
            }
        })
        .catch(error => console.error('Error loading customer data:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.querySelector('select[name="state"]');
    stateSelect.addEventListener('change', function() {
        loadCities(this.value);
    });

    // Add debounce to contact field
    const contactInput = document.querySelector('input[name="contact"]');
    let timeout;
    contactInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(checkExistingCustomer, 500);
    });
});
</script>

<div class="content">
    <h1>Add Customer</h1>

    <form method="post">

        Customer ID<br>
        <input value="<?= htmlspecialchars($customer_id) ?>" readonly><br><br>

        Company Name<br>
        <input name="company_name" required><br><br>

        Customer Name<br>
        <input name="customer_name" required><br><br>

        Contact (Phone)<br>
        <input name="contact" placeholder="Enter 10-digit phone number">
        <div id="lookup_result"></div><br>

        Email<br>
        <input name="email" type="email"><br><br>

        Address Line 1<br>
        <input name="address1"><br><br>

        Address Line 2<br>
        <input name="address2"><br><br>

        City<br>
        <select id="city_select" name="city">
            <option value="">-- Select City --</option>
        </select><br><br>

        Pincode<br>
        <input name="pincode" maxlength="10"><br><br>

        State<br>
        <select name="state">
            <option value="">-- Select State --</option>
            <?php
            $states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
            foreach ($states as $state) {
                echo '<option value="' . htmlspecialchars($state['state_name']) . '">' . htmlspecialchars($state['state_name']) . '</option>';
            }
            ?>
        </select><br><br>

        GSTIN<br>
        <input name="gstin" placeholder="15-character GSTIN"><br><br>

        <button>Add Customer</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>

    </form>
</div>
</body>
</html>
