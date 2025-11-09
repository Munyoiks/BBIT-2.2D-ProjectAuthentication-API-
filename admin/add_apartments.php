// add_apartment.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Apartment</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 20px 0;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h2 {
            font-weight: 600;
            margin: 0;
        }
        
        .content {
            padding: 30px 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 20px;
        }
        
        .logout {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .back {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .logout a, .back a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .logout a {
            background: #dc3545;
        }
        
        .logout a:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .back a {
            background: #6c757d;
        }
        
        .back a:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input[type="text"]:focus, input[type="number"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        .btn {
            padding: 12px 25px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 0;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .logout, .back {
                position: static;
                display: inline-block;
                margin: 5px;
            }
            
            .header h2 {
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="manage_apartments.php">← Back to Apartments</a></div>
        <h2>Add New Apartment</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Apartment Number:</label>
                    <input type="text" name="apartment_number" value="<?= htmlspecialchars($_POST['apartment_number'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Building Name:</label>
                    <input type="text" name="building_name" value="<?= htmlspecialchars($_POST['building_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Monthly Rent (KSh):</label>
                    <input type="number" name="rent_amount" value="<?= htmlspecialchars($_POST['rent_amount'] ?? '') ?>" step="0.01" min="0" required>
                </div>
                
                <button type="submit" class="btn">Add Apartment</button>
            </form>
        </div>
    </div>
</body>
</html>
