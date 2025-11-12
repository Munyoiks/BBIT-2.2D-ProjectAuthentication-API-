<?php
session_start();
require_once '../auth/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ==================== HELPERS ==================== */
function redirect($url, $msg = null) {
    if ($msg) $_SESSION['flash'] = $msg;
    header("Location: $url"); exit;
}
function flash() {
    if (isset($_SESSION['flash'])) {
        $m = $_SESSION['flash']; unset($_SESSION['flash']);
        return "<div class='flash-message'>$m</div>";
    }
    return '';
}

/* ==================== HTML RENDER ==================== */
function render_header() {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Members | Mojo Tenant</title>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script src="../auth/emailConfig.js"></script>
    <style>
        :root {
            --primary: #6D28D9;
            --primary-dark: #5B21B6;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --gray: #6B7280;
            --light: #F3F4F6;
            --white: #FFFFFF;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #1F2937;
            min-height: 100vh;
            padding: 1rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            margin-bottom: 1.5rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1E293B;
        }

        .btn {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(109,40,217,0.3);
        }

        .btn-secondary {
            background: var(--gray);
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.95rem;
        }

        th {
            background: #F8FAFC;
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #E2E8F0;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #E2E8F0;
        }

        tr:hover {
            background: #F8FAFC;
        }

        .status {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .status-pending { color: var(--warning); background: #FFFBEB; }
        .status-invited { color: var(--success); background: #ECFDF5; }
        .status-expired { color: var(--danger); background: #FEF2F2; }

        .flash-message {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            transition: border 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109,40,217,0.1);
        }

        .actions {
            white-space: nowrap;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }

            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                border: 1px solid #E2E8F0;
                border-radius: 12px;
                margin-bottom: 1rem;
                padding: 1rem;
                background: #FAFAFA;
            }

            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }

            td:before {
                content: attr(data-label);
                position: absolute;
                left: 1rem;
                width: 45%;
                font-weight: 600;
                color: #374151;
                text-align: left;
            }

            .actions {
                justify-content: flex-end;
                margin-top: 0.5rem;
            }

            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .card { padding: 1rem; }
            h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Back to Dashboard -->
    <div style="margin-bottom: 1rem;">
    <a href="dashboard.php" class="btn btn-secondary">
            Back to Dashboard
        </a>
    </div>';

    echo flash();
}

function render_footer() {
    echo '</div>
    <script>
        async function sendInvite(familyId, name, email, unit, role = "family") {
            const btn = event.target;
            if (btn.disabled) return;

            btn.disabled = true;
            btn.classList.add("loading");
            btn.textContent = "Sending...";

            const token = crypto.randomUUID().replace(/-/g, "").substr(0, 32);

            // Dynamic base path
            const path = window.location.pathname;
            const lastSlash = path.lastIndexOf("/");
            const base = lastSlash > 0 ? path.substring(0, lastSlash) : "";
            const link = ${window.location.protocol}//${window.location.host}${base}/setup_password.php?invite=${token}&fid=${familyId};

            const data = {
                full_name: name,
                invitation_link: link,
                primary_tenant: "Mojo Tenant Admin",
                unit_number: unit,
                role: window.formatRole(role),
                expires_at: window.getExpirationDate(7)
            };

            try {
                await window.sendInvitationEmail(email, data);
                const saveRes = await fetch(${base}/family.php?action=save_token&id=${familyId}&token=${token});
                if (!saveRes.ok) throw new Error("Failed to save invite");

                // Update UI instantly
                const row = btn.closest("tr");
                row.querySelector(".status").innerHTML = <span class="status status-invited">Invited</span>;
                btn.outerHTML = <span class="status-invited">Sent</span>;

            } catch (err) {
                alert("Failed to send invite: " + (err.message || "Try again"));
                console.error(err);
                btn.disabled = false;
                btn.classList.remove("loading");
                btn.textContent = "Invite";
            }
        }
    </script>
    </body></html>';
}

/* ==================== ROUTING ==================== */

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Routing logic BEFORE any output
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $unit_number = trim($_POST['unit_number']);
            $phone = trim($_POST['phone'] ?? '');

            $full_name = mysqli_real_escape_string($conn, $full_name);
            $email = mysqli_real_escape_string($conn, $email);
            $unit_number = mysqli_real_escape_string($conn, $unit_number);
            $phone = mysqli_real_escape_string($conn, $phone);

            $sql = "INSERT INTO families (full_name, email, unit_number, phone, invited_by) 
                    VALUES ('$full_name', '$email', '$unit_number', '$phone', '$user_id')";
            
            if (mysqli_query($conn, $sql)) {
                redirect('family.php', 'Family member added successfully.');
            } else {
                // Defer output until after header logic
                $add_error = '<div class="flash-message" style="background:#FECACA;color:#991B1B;border:1px solid #FECACA">Error: ' . mysqli_error($conn) . '</div>';
            }
        }
        break;
    case 'save_token':
        if (isset($_GET['id'], $_GET['token'])) {
            $id = (int)$_GET['id'];
            $token = mysqli_real_escape_string($conn, $_GET['token']);
            $sql = "UPDATE families 
                    SET invitation_token = ?, 
                        invitation_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                        invitation_sent_at = NOW() 
                    WHERE id = ? AND invited_by = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sii", $token, $id, $user_id);
            mysqli_stmt_execute($stmt);
        }
        http_response_code(200);
        exit;
    case 'delete':
        if ($id) {
            $id = (int)$id;
            $sql = "DELETE FROM families WHERE id = ? AND invited_by = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
            mysqli_stmt_execute($stmt);
            redirect('family.php', 'Member deleted.');
        }
        break;
}

// Now safe to output HTML
render_header();

switch ($action) {
    case 'list':
        $sql = "SELECT *, 
                CASE 
                    WHEN invitation_token IS NOT NULL AND invitation_expires_at < NOW() THEN 'expired'
                    WHEN invitation_token IS NOT NULL THEN 'invited'
                    ELSE 'pending'
                END AS status 
                FROM families WHERE invited_by = ? ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $families = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $families[] = $row;
        }
        ?>
        <div class="card">
            <div class="header">
                <h2>Family Members</h2>
                <a href="?action=add" class="btn">+ Add Member</a>
            </div>

            <?php if (empty($families)): ?>
                <p style="text-align:center;color:#64748B;padding:2rem;">No family members yet. <a href="?action=add">Add one</a></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Unit</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($families as $f): ?>
                        <tr>
                            <td data-label="Name"><?=htmlspecialchars($f['full_name'])?></td>
                            <td data-label="Email"><?=htmlspecialchars($f['email'])?></td>
                            <td data-label="Unit"><?=htmlspecialchars($f['unit_number'])?></td>
                            <td data-label="Phone"><?=htmlspecialchars($f['phone'] ?: '-')?></td>
                            <td data-label="Status" class="status">
                                <?php if ($f['status'] === 'pending'): ?>
                                    <span class="status status-pending">Not Invited</span>
                                <?php elseif ($f['status'] === 'invited'): ?>
                                    <span class="status status-invited">Invited</span>
                                <?php else: ?>
                                    <span class="status status-expired">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" class="actions">
                                <?php if ($f['status'] === 'pending'): ?>
                                    <button onclick="sendInvite(<?=$f['id']?>, '<?=addslashes($f['full_name'])?>', '<?=addslashes($f['email'])?>', '<?=addslashes($f['unit_number'])?>')" class="btn btn-sm">Invite</button>
                                <?php elseif ($f['status'] === 'invited'): ?>
                                    <span class="status-invited">Sent</span>
                                <?php elseif ($f['status'] === 'expired'): ?>
                                    <button onclick="sendInvite(<?=$f['id']?>, '<?=addslashes($f['full_name'])?>', '<?=addslashes($f['email'])?>', '<?=addslashes($f['unit_number'])?>')" class="btn btn-sm">Re-send</button>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?=$f['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this member?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        break;
    case 'add':
        // Show add form (output only)
        ?>
        <div class="card">
            <h2>Add Family Member</h2>
            <?php if (isset($add_error)) echo $add_error; ?>
            <form method="post" style="max-width:500px;">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Unit Number</label><input type="text" name="unit_number" required></div>
                <div class="form-group"><label>Phone (optional)</label><input type="tel" name="phone"></div>
                <button type="submit" class="btn">Add Member</button>
                <a href="family.php" class="btn btn-secondary" style="margin-left:0.5rem;">Cancel</a>
            </form>
        </div>
        <?php
        break;
    default:
        echo '<div class="flash-message" style="background:#FECACA;color:#991B1B;border:1px solid #FECACA">Invalid action.</div>';
}

render_footer();
?>
