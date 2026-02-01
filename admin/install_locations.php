<?php
include "../db.php";

$messages = [];
$errors = [];

// Create States Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(100) NOT NULL UNIQUE,
            state_code VARCHAR(10),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'states' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating states: " . $e->getMessage();
}

// Create Cities Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            city_name VARCHAR(100) NOT NULL,
            state_id INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_state (state_id),
            UNIQUE KEY unique_city_state (city_name, state_id),
            FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'cities' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating cities: " . $e->getMessage();
}

// Insert Indian States
try {
    $pdo->exec("
        INSERT IGNORE INTO states (state_name, state_code) VALUES
        ('Andhra Pradesh', 'AP'),
        ('Arunachal Pradesh', 'AR'),
        ('Assam', 'AS'),
        ('Bihar', 'BR'),
        ('Chhattisgarh', 'CG'),
        ('Goa', 'GA'),
        ('Gujarat', 'GJ'),
        ('Haryana', 'HR'),
        ('Himachal Pradesh', 'HP'),
        ('Jharkhand', 'JH'),
        ('Karnataka', 'KA'),
        ('Kerala', 'KL'),
        ('Madhya Pradesh', 'MP'),
        ('Maharashtra', 'MH'),
        ('Manipur', 'MN'),
        ('Meghalaya', 'ML'),
        ('Mizoram', 'MZ'),
        ('Nagaland', 'NL'),
        ('Odisha', 'OD'),
        ('Punjab', 'PB'),
        ('Rajasthan', 'RJ'),
        ('Sikkim', 'SK'),
        ('Tamil Nadu', 'TN'),
        ('Telangana', 'TS'),
        ('Tripura', 'TR'),
        ('Uttar Pradesh', 'UP'),
        ('Uttarakhand', 'UK'),
        ('West Bengal', 'WB'),
        ('Delhi', 'DL'),
        ('Jammu and Kashmir', 'JK'),
        ('Ladakh', 'LA'),
        ('Puducherry', 'PY'),
        ('Chandigarh', 'CH'),
        ('Andaman and Nicobar Islands', 'AN'),
        ('Dadra and Nagar Haveli and Daman and Diu', 'DN'),
        ('Lakshadweep', 'LD')
    ");
    $messages[] = "Indian states inserted";
} catch (PDOException $e) {
    $errors[] = "Error inserting states: " . $e->getMessage();
}

// Insert Major Cities for each state
$citiesByState = [
    'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad', 'Thane', 'Solapur', 'Kolhapur', 'Amravati', 'Navi Mumbai', 'Sangli', 'Jalgaon', 'Akola', 'Latur', 'Dhule', 'Ahmednagar', 'Chandrapur', 'Parbhani', 'Ichalkaranji', 'Jalna'],
    'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bhavnagar', 'Jamnagar', 'Junagadh', 'Gandhinagar', 'Anand', 'Nadiad', 'Morbi', 'Mehsana', 'Bharuch', 'Vapi', 'Navsari', 'Veraval', 'Porbandar', 'Godhra', 'Bhuj', 'Palanpur'],
    'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru', 'Hubli', 'Dharwad', 'Belgaum', 'Gulbarga', 'Davanagere', 'Bellary', 'Shimoga', 'Tumkur', 'Raichur', 'Bijapur', 'Hospet', 'Hassan', 'Gadag', 'Udupi', 'Robertson Pet', 'Bhadravati', 'Chitradurga'],
    'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem', 'Tirunelveli', 'Tiruppur', 'Ranipet', 'Nagercoil', 'Thanjavur', 'Vellore', 'Kancheepuram', 'Erode', 'Tiruvannamalai', 'Pollachi', 'Rajapalayam', 'Sivakasi', 'Pudukkottai', 'Neyveli', 'Nagapattinam'],
    'Telangana' => ['Hyderabad', 'Warangal', 'Nizamabad', 'Khammam', 'Karimnagar', 'Ramagundam', 'Mahbubnagar', 'Nalgonda', 'Adilabad', 'Suryapet', 'Miryalaguda', 'Siddipet', 'Jagtial', 'Mancherial', 'Nirmal'],
    'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Nellore', 'Kurnool', 'Rajahmundry', 'Kakinada', 'Tirupati', 'Kadapa', 'Anantapur', 'Vizianagaram', 'Eluru', 'Ongole', 'Nandyal', 'Machilipatnam', 'Adoni', 'Tenali', 'Proddatur', 'Chittoor', 'Hindupur'],
    'Kerala' => ['Thiruvananthapuram', 'Kochi', 'Kozhikode', 'Thrissur', 'Kollam', 'Palakkad', 'Alappuzha', 'Kannur', 'Kottayam', 'Kasaragod', 'Malappuram', 'Pathanamthitta', 'Idukki', 'Wayanad', 'Ernakulam'],
    'Delhi' => ['New Delhi', 'North Delhi', 'South Delhi', 'East Delhi', 'West Delhi', 'Central Delhi', 'North East Delhi', 'North West Delhi', 'South East Delhi', 'South West Delhi', 'Shahdara'],
    'Uttar Pradesh' => ['Lucknow', 'Kanpur', 'Ghaziabad', 'Agra', 'Varanasi', 'Meerut', 'Prayagraj', 'Bareilly', 'Aligarh', 'Moradabad', 'Saharanpur', 'Gorakhpur', 'Noida', 'Firozabad', 'Jhansi', 'Muzaffarnagar', 'Mathura', 'Rampur', 'Shahjahanpur', 'Farrukhabad', 'Mau', 'Hapur', 'Etawah', 'Mirzapur', 'Bulandshahr', 'Sambhal', 'Amroha', 'Hardoi', 'Fatehpur', 'Raebareli'],
    'Rajasthan' => ['Jaipur', 'Jodhpur', 'Kota', 'Bikaner', 'Ajmer', 'Udaipur', 'Bhilwara', 'Alwar', 'Bharatpur', 'Sikar', 'Pali', 'Sri Ganganagar', 'Kishangarh', 'Baran', 'Dhaulpur', 'Tonk'],
    'Madhya Pradesh' => ['Indore', 'Bhopal', 'Jabalpur', 'Gwalior', 'Ujjain', 'Sagar', 'Dewas', 'Satna', 'Ratlam', 'Rewa', 'Murwara', 'Singrauli', 'Burhanpur', 'Khandwa', 'Bhind', 'Chhindwara', 'Guna', 'Shivpuri', 'Vidisha', 'Chhatarpur'],
    'West Bengal' => ['Kolkata', 'Howrah', 'Durgapur', 'Asansol', 'Siliguri', 'Bardhaman', 'Malda', 'Baharampur', 'Habra', 'Kharagpur', 'Shantipur', 'Dankuni', 'Dhulian', 'Ranaghat', 'Haldia', 'Raiganj', 'Krishnanagar', 'Nabadwip', 'Medinipur', 'Jalpaiguri'],
    'Bihar' => ['Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur', 'Purnia', 'Darbhanga', 'Bihar Sharif', 'Arrah', 'Begusarai', 'Katihar', 'Munger', 'Chhapra', 'Samastipur', 'Hajipur', 'Sasaram', 'Dehri', 'Siwan', 'Motihari', 'Nawada', 'Bagaha'],
    'Punjab' => ['Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala', 'Bathinda', 'Mohali', 'Pathankot', 'Hoshiarpur', 'Batala', 'Moga', 'Malerkotla', 'Khanna', 'Phagwara', 'Muktsar', 'Barnala', 'Rajpura', 'Firozpur', 'Kapurthala'],
    'Haryana' => ['Faridabad', 'Gurgaon', 'Panipat', 'Ambala', 'Yamunanagar', 'Rohtak', 'Hisar', 'Karnal', 'Sonipat', 'Panchkula', 'Bhiwani', 'Sirsa', 'Bahadurgarh', 'Jind', 'Thanesar', 'Kaithal', 'Rewari', 'Palwal'],
    'Odisha' => ['Bhubaneswar', 'Cuttack', 'Rourkela', 'Brahmapur', 'Sambalpur', 'Puri', 'Balasore', 'Bhadrak', 'Baripada', 'Jharsuguda', 'Jeypore', 'Barbil', 'Rayagada', 'Paradip'],
    'Jharkhand' => ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro Steel City', 'Deoghar', 'Hazaribagh', 'Giridih', 'Ramgarh', 'Medininagar', 'Chirkunda'],
    'Assam' => ['Guwahati', 'Silchar', 'Dibrugarh', 'Jorhat', 'Nagaon', 'Tinsukia', 'Tezpur', 'Bongaigaon', 'Dhubri', 'Diphu'],
    'Chhattisgarh' => ['Raipur', 'Bhilai', 'Bilaspur', 'Korba', 'Durg', 'Rajnandgaon', 'Raigarh', 'Jagdalpur', 'Ambikapur', 'Chirmiri'],
    'Uttarakhand' => ['Dehradun', 'Haridwar', 'Roorkee', 'Haldwani', 'Rudrapur', 'Kashipur', 'Rishikesh', 'Pithoragarh', 'Ramnagar', 'Nainital'],
    'Himachal Pradesh' => ['Shimla', 'Solan', 'Dharamshala', 'Mandi', 'Palampur', 'Baddi', 'Nahan', 'Paonta Sahib', 'Sundernagar', 'Kullu'],
    'Goa' => ['Panaji', 'Margao', 'Vasco da Gama', 'Mapusa', 'Ponda', 'Bicholim', 'Curchorem', 'Sanquelim', 'Cuncolim', 'Quepem'],
    'Jammu and Kashmir' => ['Srinagar', 'Jammu', 'Anantnag', 'Baramulla', 'Sopore', 'Kathua', 'Udhampur', 'Poonch', 'Rajouri', 'Kupwara'],
    'Chandigarh' => ['Chandigarh'],
    'Puducherry' => ['Puducherry', 'Karaikal', 'Mahe', 'Yanam']
];

try {
    $stateStmt = $pdo->prepare("SELECT id FROM states WHERE state_name = ?");
    $cityStmt = $pdo->prepare("INSERT IGNORE INTO cities (city_name, state_id) VALUES (?, ?)");

    foreach ($citiesByState as $stateName => $cities) {
        $stateStmt->execute([$stateName]);
        $stateId = $stateStmt->fetchColumn();

        if ($stateId) {
            foreach ($cities as $cityName) {
                $cityStmt->execute([$cityName, $stateId]);
            }
        }
    }
    $messages[] = "Cities inserted for all states";
} catch (PDOException $e) {
    $errors[] = "Error inserting cities: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Location Database - Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #3498db;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 5px;
        }
        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Location Database - Installation</h1>

        <h3>Installation Results:</h3>

        <?php foreach ($messages as $msg): ?>
            <div class="success">✓ <?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="error">✗ <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <?php
            $stateCount = $pdo->query("SELECT COUNT(*) FROM states")->fetchColumn();
            $cityCount = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
            ?>
            <div class="stats">
                <div class="stat-box">
                    <div class="number"><?= $stateCount ?></div>
                    <div>States/UTs</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?= $cityCount ?></div>
                    <div>Cities</div>
                </div>
            </div>

            <div class="summary">
                <strong>Installation Complete!</strong><br>
                States and cities database has been set up with Indian locations.
            </div>

            <a href="locations.php" class="btn">Manage Locations</a>
            <a href="/crm/add.php" class="btn">Go to CRM</a>
        <?php else: ?>
            <div class="summary" style="background: #fff3cd;">
                <strong>Some errors occurred.</strong><br>
                Please check the error messages above and try again.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
