<?php
require_once 'config.php';

$error = null;
$success = null;
$user_id = null;
$alerts = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'subscribe':
                    // Validate input
                    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                    $name = sanitizeInput($_POST['name']);
                    $topic = sanitizeInput($_POST['topic']);
                    $frequency = in_array($_POST['frequency'], ['daily', 'weekly', 'monthly']) 
                        ? $_POST['frequency'] 
                        : 'weekly';

                    if (!$email || !$name || !$topic) {
                        throw new Exception('Please fill in all required fields');
                    }

                    // Check if user exists or create new user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $stmt = $pdo->prepare("INSERT INTO users (email, name) VALUES (?, ?)");
                        $stmt->execute([$email, $name]);
                        $user_id = $pdo->lastInsertId();
                    } else {
                        $user_id = $user['id'];
                    }

                    // Create alert subscription
                    $stmt = $pdo->prepare("
                        INSERT INTO alerts (user_id, topic, frequency) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $topic, $frequency]);

                    $success = "Successfully subscribed to alerts for: " . htmlspecialchars($topic);
                    break;

                case 'unsubscribe':
                    $alert_id = filter_var($_POST['alert_id'], FILTER_VALIDATE_INT);
                    if (!$alert_id) {
                        throw new Exception('Invalid alert ID');
                    }

                    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
                    $stmt->execute([$alert_id]);

                    $success = "Successfully unsubscribed from alert";
                    break;

                case 'update':
                    $alert_id = filter_var($_POST['alert_id'], FILTER_VALIDATE_INT);
                    $frequency = in_array($_POST['frequency'], ['daily', 'weekly', 'monthly']) 
                        ? $_POST['frequency'] 
                        : 'weekly';

                    if (!$alert_id) {
                        throw new Exception('Invalid alert ID');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE alerts 
                        SET frequency = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$frequency, $alert_id]);

                    $success = "Successfully updated alert frequency";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load existing alerts if email is provided
if (isset($_GET['email'])) {
    try {
        $email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
        if ($email) {
            $stmt = $pdo->prepare("
                SELECT u.email, u.name, a.id, a.topic, a.frequency, a.created_at
                FROM users u
                JOIN alerts a ON u.id = a.user_id
                WHERE u.email = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$email]);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get popular topics for suggestions
try {
    $stmt = $pdo->prepare("
        SELECT c.case_type, COUNT(*) as count
        FROM classifications c
        GROUP BY c.case_type
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $popular_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $popular_topics = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert Subscriptions - Legal AI Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="index.php" class="text-xl font-bold text-gray-900">Legal AI Assistant</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo $success; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Subscribe Form -->
            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Subscribe to Legal Alerts</h2>
                    
                    <form action="alerts.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="subscribe">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="topic" class="block text-sm font-medium text-gray-700">Legal Topic</label>
                            <input type="text" name="topic" id="topic" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., Medical Malpractice, Tax Law, etc.">
                        </div>

                        <div>
                            <label for="frequency" class="block text-sm font-medium text-gray-700">Alert Frequency</label>
                            <select name="frequency" id="frequency" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Subscribe
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Popular Topics -->
            <?php if (!empty($popular_topics)): ?>
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="p-6">
                        <h3 class="text-sm font-medium text-gray-900 mb-4">Popular Topics</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($popular_topics as $topic): ?>
                                <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($topic['case_type']); ?>
                                    <span class="ml-1 text-blue-600">(<?php echo $topic['count']; ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Manage Existing Alerts -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Manage Your Alerts</h2>
                    
                    <form action="alerts.php" method="GET" class="mb-6">
                        <div class="flex gap-4">
                            <input type="email" name="email" placeholder="Enter your email"
                                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Load Alerts
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($alerts)): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="py-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($alert['topic']); ?>
                                            </h4>
                                            <div class="mt-1 text-sm text-gray-500">
                                                Frequency: <?php echo ucfirst($alert['frequency']); ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-4">
                                            <form action="alerts.php" method="POST" class="flex items-center space-x-2">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                <select name="frequency"
                                                        class="text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <option value="daily" <?php echo $alert['frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                    <option value="weekly" <?php echo $alert['frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                    <option value="monthly" <?php echo $alert['frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                </select>
                                                <button type="submit"
                                                        class="text-sm text-blue-600 hover:text-blue-500">
                                                    Update
                                                </button>
                                            </form>
                                            <form action="alerts.php" method="POST" class="flex items-center">
                                                <input type="hidden" name="action" value="unsubscribe">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                <button type="submit"
                                                        class="text-sm text-red-600 hover:text-red-500">
                                                    Unsubscribe
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
