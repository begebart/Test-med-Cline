<?php
require_once 'config.php';

// If user is already logged in, redirect to paper
if (isset($_SESSION['current_user'])) {
    header('Location: paper.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = 'Please enter your name';
    } elseif (!in_array($name, $allowed_users)) {
        $error = 'This name is not recognized. Please use one of the predefined names.';
    } else {
        // Set current user in session
        $_SESSION['current_user'] = $name;
        $_SESSION['users'][$name]['last_activity'] = time();
        
        logActivity($name, 'Logged in');
        
        header('Location: paper.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collaborative Paper - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 0.95em;
        }

        input[type="text"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 0.9em;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .allowed-users {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .allowed-users h3 {
            color: #555;
            margin-bottom: 10px;
            font-size: 0.95em;
        }

        .user-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .user-tag {
            background: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            color: #667eea;
            border: 1px solid #667eea;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 1.6em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Samarbejdspapir</h1>
        <p class="subtitle">Ét fælles papir</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Dine initialer</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    placeholder="Enter your name..." 
                    required 
                    autofocus
                    autocomplete="off"
                >
            </div>

            <button type="submit">Gå til papiret</button>
        </form>

        <div class="allowed-users">
            <h3>Godkendte brugere:</h3>
            <div class="user-list">
                <?php foreach ($allowed_users as $user): ?>
                    <span class="user-tag"><?php echo htmlspecialchars($user); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>