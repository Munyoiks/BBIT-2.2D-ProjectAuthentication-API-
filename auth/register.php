<?php
session_start();
require_once "db_config.php"; 

// --- Helper: Format phone number to +254 standard ---
function formatPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (preg_match('/^0\d{9}$/', $phone)) return '+254' . substr($phone, 1);
    if (preg_match('/^254\d{9}$/', $phone)) return '+' . $phone;
    if (preg_match('/^\+254\d{9}$/', $phone)) return $phone;
    return false;
}

// --- Get available units for PRIMARY tenants (units without primary tenant) ---
function getAvailablePrimaryUnits($conn) {
    $units = [];
    
    // Get all units that have a primary tenant
    $query = "SELECT DISTINCT unit_number 
              FROM users 
              WHERE unit_number IS NOT NULL 
              AND unit_number != '' 
              AND is_primary_tenant = 1
              AND is_verified = 1";
    $result = $conn->query($query);
    
    $occupied_units = [];
    while ($row = $result->fetch_assoc()) {
        $occupied_units[] = $row['unit_number'];
    }
    
    // Define all possible units
    $all_units = [];
    
    // Ground Floor: 14 units (G1 to G14)
    for ($i = 1; $i <= 14; $i++) {
        $all_units[] = 'G' . $i;
    }
    
    // Floors 1-5: 13 units each (F1-1 to F5-13)
    for ($floor = 1; $floor <= 5; $floor++) {
        for ($unit = 1; $unit <= 13; $unit++) {
            $all_units[] = 'F' . $floor . '-' . $unit;
        }
    }
    
    // Penthouse Floor: 5 units (P1 to P5)
    for ($i = 1; $i <= 5; $i++) {
        $all_units[] = 'P' . $i;
    }
    
    // Return only units WITHOUT primary tenants (available for new primary tenants)
    foreach ($all_units as $unit) {
        if (!in_array($unit, $occupied_units)) {
            $units[] = $unit;
        }
    }
    
    // Sort units
    usort($units, function($a, $b) {
        $a_type = $a[0];
        $b_type = $b[0];
        $a_num = intval(preg_replace('/[^0-9]/', '', $a));
        $b_num = intval(preg_replace('/[^0-9]/', '', $b));
        
        $type_order = ['G' => 1, 'F' => 2, 'P' => 3];
        
        if ($type_order[$a_type] != $type_order[$b_type]) {
            return $type_order[$a_type] - $type_order[$b_type];
        }
        
        return $a_num - $b_num;
    });
    
    return $units;
}

// --- Get available units for SECONDARY tenants (units WITH primary tenant) ---
function getAvailableSecondaryUnits($conn) {
    $units = [];
    
    // Get all units that HAVE a primary tenant (available for secondary tenants)
    $query = "SELECT DISTINCT unit_number 
              FROM users 
              WHERE unit_number IS NOT NULL 
              AND unit_number != '' 
              AND is_primary_tenant = 1
              AND is_verified = 1";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $units[] = $row['unit_number'];
    }
    
    // Sort units
    usort($units, function($a, $b) {
        $a_type = $a[0];
        $b_type = $b[0];
        $a_num = intval(preg_replace('/[^0-9]/', '', $a));
        $b_num = intval(preg_replace('/[^0-9]/', '', $b));
        
        $type_order = ['G' => 1, 'F' => 2, 'P' => 3];
        
        if ($type_order[$a_type] != $type_order[$b_type]) {
            return $type_order[$a_type] - $type_order[$b_type];
        }
        
        return $a_num - $b_num;
    });
    
    return $units;
}

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Handle invitation registration
$invitation_data = null;
if (isset($_GET['invite'])) {
    $token = $_GET['invite'];
    $invite_stmt = $conn->prepare("SELECT ui.*, u.full_name as inviter_name, u.unit_number 
                                  FROM unit_invitations ui 
                                  JOIN users u ON ui.inviter_id = u.id 
                                  WHERE ui.token = ? AND ui.status = 'pending' AND ui.expires_at > NOW()");
    $invite_stmt->bind_param("s", $token);
    $invite_stmt->execute();
    $invitation_result = $invite_stmt->get_result();
    
    if ($invitation_result->num_rows > 0) {
        $invitation_data = $invitation_result->fetch_assoc();
        $_SESSION['invitation_token'] = $token;
    }
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = formatPhone($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $unit_number = trim($_POST['unit_number']);
    $occupant_type = $_POST['occupant_type'] ?? 'primary';
    
    // Additional fields for non-primary tenants
    $primary_tenant_name = trim($_POST['primary_tenant_name'] ?? '');
    $primary_tenant_email = trim($_POST['primary_tenant_email'] ?? '');

    if (!$name || !$email || !$phone) {
        die("Invalid input, please try again.");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Handle invitation registration
        if (isset($_SESSION['invitation_token'])) {
            $token = $_SESSION['invitation_token'];
            $invite_stmt = $conn->prepare("SELECT * FROM unit_invitations WHERE token = ? AND status = 'pending'");
            $invite_stmt->bind_param("s", $token);
            $invite_stmt->execute();
            $invite_result = $invite_stmt->get_result();
            
            if ($invite_result->num_rows > 0) {
                $invite = $invite_result->fetch_assoc();
                $unit_number = $invite['unit_number'];
                $occupant_type = $invite['role'];
                $invited_by = $invite['inviter_id'];
                
                // Mark invitation as accepted
                $update_invite = $conn->prepare("UPDATE unit_invitations SET status = 'accepted' WHERE token = ?");
                $update_invite->bind_param("s", $token);
                $update_invite->execute();
            }
        } else {
            // Regular registration
            if (!$unit_number) {
                throw new Exception("Unit number is required.");
            }

            if ($occupant_type === 'primary') {
                // Check if unit already has primary tenant
                $primary_check = $conn->prepare("SELECT id FROM users WHERE unit_number = ? AND is_primary_tenant = 1");
                $primary_check->bind_param("s", $unit_number);
                $primary_check->execute();
                $primary_result = $primary_check->get_result();
                
                if ($primary_result->num_rows > 0) {
                    throw new Exception("Unit $unit_number already has a primary tenant. Please register as an additional occupant.");
                }
            } else {
                // For non-primary tenants, verify primary tenant exists
                $primary_verify = $conn->prepare("SELECT id, full_name FROM users WHERE unit_number = ? AND is_primary_tenant = 1");
                $primary_verify->bind_param("s", $unit_number);
                $primary_verify->execute();
                $primary_verify_result = $primary_verify->get_result();
                
                if ($primary_verify_result->num_rows === 0) {
                    throw new Exception("No primary tenant found for unit $unit_number. Please have the primary tenant register first.");
                }
                
                $primary_tenant = $primary_verify_result->fetch_assoc();
                $invited_by = $primary_tenant['id'];
                
                // Verify primary tenant information if provided
                if ($primary_tenant_name && $primary_tenant_email) {
                    $verify_primary = $conn->prepare("SELECT id FROM users WHERE unit_number = ? AND full_name LIKE ? AND email = ? AND is_primary_tenant = 1");
                    $search_name = "%$primary_tenant_name%";
                    $verify_primary->bind_param("sss", $unit_number, $search_name, $primary_tenant_email);
                    $verify_primary->execute();
                    $verify_result = $verify_primary->get_result();
                    
                    if ($verify_result->num_rows === 0) {
                        throw new Exception("Primary tenant information doesn't match our records. Please check the name and email.");
                    }
                }
            }
        }

        // Check if email or phone already exists
        $check = $conn->prepare("SELECT id, is_verified FROM users WHERE email=? OR phone=?");
        $check->bind_param("ss", $email, $phone);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if ($user['is_verified'] == 0) {
                // Update existing unverified user
                $update_stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, password=?, unit_number=?, unit_role=?, is_primary_tenant=?, invited_by=? WHERE email=?");
                $is_primary = ($occupant_type === 'primary') ? 1 : 0;
                $invited_by = $invited_by ?? NULL;
                $update_stmt->bind_param("sssssiis", $name, $phone, $password, $unit_number, $occupant_type, $is_primary, $invited_by, $email);
                $update_stmt->execute();
            } else {
                throw new Exception("Email or phone already exists and is verified. <a href='login.php'>Login here</a>.");
            }
        } else {
            // Insert new user
            $is_primary = ($occupant_type === 'primary') ? 1 : 0;
            $invited_by = $invited_by ?? NULL;
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, unit_number, unit_role, is_primary_tenant, invited_by, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("ssssssii", $name, $email, $phone, $password, $unit_number, $occupant_type, $is_primary, $invited_by);
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            // Store user ID for session
            $user_id = $stmt->insert_id;
            $stmt->close();
            
            // Store unit number in session for verification process
            $_SESSION['pending_unit_number'] = $unit_number;
            $_SESSION['pending_user_id'] = $user_id;
        }

        // Generate verification code
        $code = rand(100000, 999999);
        $_SESSION['verification_code'] = $code;
        $_SESSION['pending_email'] = $email;

        // Commit transaction
        $conn->commit();

        // Redirect to verification
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Sending Verification...</title>
            <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
            <script src="emailConfig.js"></script>
            <script>
              document.addEventListener("DOMContentLoaded", () => {
                const email = "<?php echo addslashes($_SESSION['pending_email']); ?>";
                const code = "<?php echo $_SESSION['verification_code']; ?>";

                sendVerificationEmail(email, code)
                  .then(() => {
                    alert("Verification email sent successfully! Check your inbox.");
                    window.location.href = "verify.php";
                  })
                  .catch((err) => {
                    console.error("Email send failed:", err);
                    alert("Failed to send verification email. Please try again.");
                    window.location.href = "verify.php";
                  });
              });
            </script>
        </head>
        <body>
            <p style="font-family: Arial; text-align: center; margin-top: 40px;">
                Sending verification code to <strong><?php echo htmlspecialchars($_SESSION['pending_email']); ?></strong>...
            </p>
        </body>
        </html>
        <?php
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Get available units based on current selection
$primary_units = getAvailablePrimaryUnits($conn);
$secondary_units = getAvailableSecondaryUnits($conn);

// Group units by floor for display
function groupUnitsByFloor($units) {
    $grouped = [
        'Ground Floor' => [],
        'Floor 1' => [],
        'Floor 2' => [],
        'Floor 3' => [],
        'Floor 4' => [],
        'Floor 5' => [],
        'Penthouse' => []
    ];

    foreach ($units as $unit) {
        if (strpos($unit, 'G') === 0) {
            $grouped['Ground Floor'][] = $unit;
        } elseif (strpos($unit, 'F1-') === 0) {
            $grouped['Floor 1'][] = $unit;
        } elseif (strpos($unit, 'F2-') === 0) {
            $grouped['Floor 2'][] = $unit;
        } elseif (strpos($unit, 'F3-') === 0) {
            $grouped['Floor 3'][] = $unit;
        } elseif (strpos($unit, 'F4-') === 0) {
            $grouped['Floor 4'][] = $unit;
        } elseif (strpos($unit, 'F5-') === 0) {
            $grouped['Floor 5'][] = $unit;
        } elseif (strpos($unit, 'P') === 0) {
            $grouped['Penthouse'][] = $unit;
        }
    }
    
    return $grouped;
}

$grouped_primary_units = groupUnitsByFloor($primary_units);
$grouped_secondary_units = groupUnitsByFloor($secondary_units);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Mojo Tenant System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }

        .form-container {
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 14px;
            margin: 10px 0;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        a:hover {
            text-decoration: underline;
        }

        .unit-info {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 14px;
            border-left: 4px solid var(--primary);
        }

        .available-count {
            color: var(--success);
            font-weight: bold;
        }

        .warning-message {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--danger);
        }

        .secondary-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #6c757d;
            transition: all 0.3s ease;
        }

        .info-message {
            background: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
            border-left: 4px solid var(--primary);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .role-primary { background: #d1ecf1; color: #0c5460; }
        .role-spouse { background: #f8d7da; color: #721c24; }
        .role-family { background: #d4edda; color: #155724; }
        .role-roommate { background: #e2e3e5; color: #383d41; }

        .form-group {
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .form-group input, .form-group select {
            padding-left: 45px;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 30px 20px;
            }
            
            body {
                padding: 10px;
            }
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>
            <?php if ($invitation_data): ?>
                <i class="fas fa-user-plus me-2"></i>Join Household
            <?php else: ?>
                <i class="fas fa-user-plus me-2"></i>Create Account
            <?php endif; ?>
        </h2>
        
        <div class="subtitle">
            Join Mojo Tenant System - Your Smart Living Solution
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($invitation_data): ?>
            <div class="unit-info">
                <i class="fas fa-envelope me-2"></i><strong>Invitation Details:</strong><br>
                You're joining <strong><?php echo htmlspecialchars($invitation_data['inviter_name']); ?></strong><br>
                Unit: <strong><?php echo htmlspecialchars($invitation_data['unit_number']); ?></strong><br>
                Role: <span class="role-badge role-<?php echo $invitation_data['role']; ?>">
                    <?php echo ucfirst($invitation_data['role']); ?>
                </span>
            </div>
        <?php else: ?>
            <div class="warning-message">
                <i class="fas fa-info-circle me-2"></i>
                <strong>First time registering?</strong> Choose "Primary Tenant" if you're the first person from your unit to register.
            </div>
            
            <div class="unit-info">
                <i class="fas fa-building me-2"></i>
                <strong>Building Structure:</strong><br>
                • Ground Floor: 14 units (G1-G14)<br>
                • Floors 1-5: 13 units each (F1-1 to F5-13)<br>
                • Penthouse: 5 units (P1-P5)<br>
                <strong>Available for Primary Tenants: <span class="available-count"><?php echo count($primary_units); ?> units</span></strong><br>
                <strong>Available for Additional Occupants: <span class="available-count"><?php echo count($secondary_units); ?> units</span></strong>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registrationForm">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="name" placeholder="Full Name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <i class="fas fa-phone"></i>
                <input type="text" name="phone" placeholder="Phone (e.g., 0722123456)" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <?php if (!$invitation_data): ?>
                <div class="form-group">
                    <i class="fas fa-users"></i>
                    <select name="occupant_type" id="occupantType" required>
                        <option value="">Select Your Role</option>
                        <option value="primary" <?php echo (isset($_POST['occupant_type']) && $_POST['occupant_type'] == 'primary') ? 'selected' : ''; ?>>Primary Tenant (First person from unit)</option>
                        <option value="spouse" <?php echo (isset($_POST['occupant_type']) && $_POST['occupant_type'] == 'spouse') ? 'selected' : ''; ?>>Spouse/Partner</option>
                        <option value="family" <?php echo (isset($_POST['occupant_type']) && $_POST['occupant_type'] == 'family') ? 'selected' : ''; ?>>Family Member</option>
                        <option value="roommate" <?php echo (isset($_POST['occupant_type']) && $_POST['occupant_type'] == 'roommate') ? 'selected' : ''; ?>>Roommate</option>
                    </select>
                </div>

                <div id="unitSelection">
                    <div class="form-group">
                        <i class="fas fa-home"></i>
                        <select name="unit_number" id="unitSelect" required>
                            <option value="">Select Your Role First</option>
                        </select>
                    </div>
                </div>

                <div id="secondaryFields" class="secondary-fields" style="display: none;">
                    <h6><i class="fas fa-user-check me-2"></i>Primary Tenant Information (Optional but Recommended)</h6>
                    <div class="form-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="primary_tenant_name" placeholder="Primary Tenant's Full Name" value="<?php echo isset($_POST['primary_tenant_name']) ? htmlspecialchars($_POST['primary_tenant_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="primary_tenant_email" placeholder="Primary Tenant's Email" value="<?php echo isset($_POST['primary_tenant_email']) ? htmlspecialchars($_POST['primary_tenant_email']) : ''; ?>">
                    </div>
                    <small class="text-muted">Helps us verify you're part of the same household.</small>
                </div>
            <?php else: ?>
                <input type="hidden" name="unit_number" value="<?php echo htmlspecialchars($invitation_data['unit_number']); ?>">
                <input type="hidden" name="occupant_type" value="<?php echo htmlspecialchars($invitation_data['role']); ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" id="submitBtn">
                <i class="fas fa-user-plus me-2"></i>Create Account
            </button>
        </form>
        
        <div class="link">
            Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a><br>
            Forgot password? <a href="../passReset/reset.php"><i class="fas fa-key me-1"></i>Reset here</a> 
        </div>
    </div>

    <script>
        // PHP data for JavaScript
        const primaryUnits = <?php echo json_encode($grouped_primary_units); ?>;
        const secondaryUnits = <?php echo json_encode($grouped_secondary_units); ?>;

        function populateUnitDropdown(units, placeholder) {
            const unitSelect = document.getElementById('unitSelect');
            unitSelect.innerHTML = '<option value="">' + placeholder + '</option>';
            
            for (const [floorName, floorUnits] of Object.entries(units)) {
                if (floorUnits.length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = floorName;
                    
                    floorUnits.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit;
                        option.textContent = `Unit ${unit}`;
                        optgroup.appendChild(option);
                    });
                    
                    unitSelect.appendChild(optgroup);
                }
            }
        }

        // Show/hide fields based on occupant type
        document.getElementById('occupantType').addEventListener('change', function() {
            const occupantType = this.value;
            const secondaryFields = document.getElementById('secondaryFields');
            const unitSelection = document.getElementById('unitSelection');
            
            if (occupantType === 'primary') {
                secondaryFields.style.display = 'none';
                unitSelection.style.display = 'block';
                populateUnitDropdown(primaryUnits, 'Select Available Unit');
            } else if (occupantType) {
                secondaryFields.style.display = 'block';
                unitSelection.style.display = 'block';
                populateUnitDropdown(secondaryUnits, 'Select Your Unit');
            } else {
                secondaryFields.style.display = 'none';
                unitSelection.style.display = 'none';
                document.getElementById('unitSelect').innerHTML = '<option value="">Select Your Role First</option>';
            }
        });

        // Trigger change event on page load if there's a selected value
        const initialOccupantType = document.getElementById('occupantType').value;
        if (initialOccupantType) {
            document.getElementById('occupantType').dispatchEvent(new Event('change'));
        }

        // Disable form resubmission and show loading state
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            this.classList.add('loading');
        });

        // Add input animations
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            element.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>

<?php
$stmt = $conn->prepare("UPDATE apartments SET tenant_id = ?, status = 'occupied' WHERE id = ?");
$stmt->bind_param("ii", $user_id, $apartment_id);
$stmt->execute();
?>
