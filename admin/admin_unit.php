<?php
session_start();
require_once "db_config.php";

// Check if user is admin (you should implement proper admin authentication)
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Handle unit assignment (for manual assignments by admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_unit'])) {
    $user_id = $_POST['user_id'];
    $unit_number = $_POST['unit_number'];
    
    $stmt = $conn->prepare("UPDATE users SET unit_number = ? WHERE id = ?");
    $stmt->bind_param("si", $unit_number, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle user deletion (vacating unit)
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    
    // Instead of actually deleting, you might want to mark as inactive
    // For now, we'll delete to demonstrate the concept
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_units.php");
    exit();
}

// Get all users with their unit information
$users_query = "
    SELECT id, full_name, email, phone, unit_number, is_verified, role 
    FROM users 
    ORDER BY unit_number IS NULL, unit_number, full_name
";
$users_result = $conn->query($users_query);

// Get all possible units
$all_units = [
    'A101', 'A102', 'A103', 'A104', 'A105',
    'B101', 'B102', 'B103', 'B104', 'B105',
    'C101', 'C102', 'C103', 'C104', 'C105',
    'D101', 'D102', 'D103', 'D104', 'D105'
];

// Get occupied units
$occupied_query = "SELECT unit_number FROM users WHERE unit_number IS NOT NULL AND unit_number != '' AND is_verified = 1";
$occupied_result = $conn->query($occupied_query);
$occupied_units = [];
while ($row = $occupied_result->fetch_assoc()) {
    $occupied_units[] = $row['unit_number'];
}

// Get available units
$available_units = array_diff($all_units, $occupied_units);

// Get unverified users (pending registrations)
$pending_query = "SELECT * FROM users WHERE is_verified = 0";
$pending_result = $conn->query($pending_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unit Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .occupied { background-color: #d4edda; }
        .available { background-color: #f8f9fa; }
        .pending { background-color: #fff3cd; }
        .status-badge { font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Unit Management - Admin Panel</h1>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Units</h5>
                                <h2><?php echo count($all_units); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Occupied</h5>
                                <h2><?php echo count($occupied_units); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Available</h5>
                                <h2><?php echo count($available_units); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo $pending_result->num_rows; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unit Grid View -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-building me-2"></i>Unit Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($all_units as $unit): 
                                $is_occupied = in_array($unit, $occupied_units);
                                $unit_user = null;
                                
                                if ($is_occupied) {
                                    foreach ($users_result as $user) {
                                        if ($user['unit_number'] === $unit && $user['is_verified'] == 1) {
                                            $unit_user = $user;
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <div class="col-md-3 mb-3">
                                <div class="card <?php echo $is_occupied ? 'occupied' : 'available'; ?>">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Unit <?php echo $unit; ?></h6>
                                        <?php if ($is_occupied && $unit_user): ?>
                                            <p class="card-text mb-1">
                                                <strong><?php echo htmlspecialchars($unit_user['full_name']); ?></strong>
                                            </p>
                                            <p class="card-text text-muted small mb-1">
                                                <?php echo htmlspecialchars($unit_user['email']); ?>
                                            </p>
                                            <span class="badge bg-success status-badge">Occupied</span>
                                            <div class="mt-2">
                                                <a href="?delete_user=<?php echo $unit_user['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to vacate this unit?')">
                                                    Vacate
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <p class="card-text text-muted">Available</p>
                                            <span class="badge bg-secondary status-badge">Vacant</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tenant List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>All Tenants
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Unit Number</th>
                                        <th>Status</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset result pointer
                                    $users_result->data_seek(0);
                                    while ($user = $users_result->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td>
                                            <?php if ($user['unit_number']): ?>
                                                <span class="badge bg-primary"><?php echo $user['unit_number']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_verified'] == 1): ?>
                                                <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['role'] ?? 'tenant'; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_verified'] == 1): ?>
                                                <a href="?delete_user=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                                    Remove
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pending Registrations -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Pending Registrations
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-warning">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Unit Number</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($pending = $pending_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pending['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                            <td><?php echo htmlspecialchars($pending['phone']); ?></td>
                                            <td>
                                                <?php if ($pending['unit_number']): ?>
                                                    <span class="badge bg-primary"><?php echo $pending['unit_number']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">Awaiting Verification</span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No pending registrations.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
