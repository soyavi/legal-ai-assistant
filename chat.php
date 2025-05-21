<?php
require_once 'config.php';

session_start();

// Initialize chat history if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

$error = null;
$response = null;

// Handle chat submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    try {
        $message = sanitizeInput($_POST['message']);
        
        // Store user message in history
        $_SESSION['chat_history'][] = [
            'role' => 'user',
            'content' => $message
        ];

        // Search for relevant cases
        $stmt = $pdo->prepare("
            SELECT s.*, c.case_type, a.summary 
            FROM sentences s
            LEFT JOIN classifications c ON s.id = c.sentence_id
            LEFT JOIN analysis a ON s.id = a.sentence_id
            WHERE MATCH(s.content) AGAINST(:query IN NATURAL LANGUAGE MODE)
            LIMIT 3
        ");
        $stmt->execute(['query' => $message]);
        $relevant_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare context for OpenAI
        $context = "Based on our legal database, here are relevant cases:\n\n";
        foreach ($relevant_cases as $case) {
            $context .= "Case: " . $case['title'] . "\n";
            $context .= "Summary: " . ($case['summary'] ?? 'No summary available') . "\n\n";
        }

        // Prepare the prompt for OpenAI
        $prompt = $context . "\n\nUser Question: " . $message . "\n\n" .
                 "Please provide a detailed response that:\n" .
                 "1. Directly answers the user's question\n" .
                 "2. Cites relevant legal principles from the cases\n" .
                 "3. Provides specific case references\n" .
                 "4. Explains any important considerations";

        // Get AI response
        $ai_response = callOpenAI($prompt);
        
        if (isset($ai_response['choices'][0]['message']['content'])) {
            $response = $ai_response['choices'][0]['message']['content'];
            
            // Store AI response in history
            $_SESSION['chat_history'][] = [
                'role' => 'assistant',
                'content' => $response
            ];
        } else {
            throw new Exception('Failed to get response from AI');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Clear chat if requested
if (isset($_POST['clear_chat'])) {
    $_SESSION['chat_history'] = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal AI Chat Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .chat-container {
            height: calc(100vh - 200px);
        }
        .messages-container {
            height: calc(100% - 80px);
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
            <div class="bg-white shadow rounded-lg overflow-hidden chat-container">
                <!-- Chat Header -->
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">Legal AI Chat Assistant</h2>
                    <form method="POST" class="flex items-center">
                        <button type="submit" name="clear_chat" 
                                class="text-sm text-gray-600 hover:text-gray-900">
                            Clear Chat
                        </button>
                    </form>
                </div>

                <!-- Chat Messages -->
                <div class="messages-container overflow-y-auto p-4 space-y-4">
                    <?php if (empty($_SESSION['chat_history'])): ?>
                        <!-- Welcome Message -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-700">
                                Welcome! I'm your Legal AI Assistant. You can ask me questions about:
                            </p>
                            <ul class="mt-2 text-sm text-gray-600 space-y-1">
                                <li>• Specific legal concepts or principles</li>
                                <li>• Case analysis and interpretation</li>
                                <li>• Legal precedents and their applications</li>
                                <li>• Comparing different legal decisions</li>
                                <li>• Understanding court rulings</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['chat_history'] as $message): ?>
                            <div class="flex <?php echo $message['role'] === 'user' ? 'justify-end' : 'justify-start'; ?>">
                                <div class="<?php echo $message['role'] === 'user' 
                                    ? 'bg-blue-600 text-white' 
                                    : 'bg-gray-100 text-gray-900'; ?> 
                                    rounded-lg px-4 py-2 max-w-3xl">
                                    <div class="text-sm">
                                        <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="ml-3">
                                    <p class="text-sm text-red-700"><?php echo $error; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Input -->
                <div class="border-t border-gray-200 p-4">
                    <form method="POST" class="flex space-x-4">
                        <input type="text" name="message" 
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="Type your legal question here..."
                               required>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Send
                        </button>
                    </form>
                </div>
            </div>

            <!-- Example Questions -->
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Example Questions You Can Ask:</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <button onclick="document.querySelector('input[name=message]').value = this.textContent"
                                class="text-left p-3 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            What are the key elements required to prove medical malpractice?
                        </button>
                        <button onclick="document.querySelector('input[name=message]').value = this.textContent"
                                class="text-left p-3 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            How do courts determine damages in personal injury cases?
                        </button>
                        <button onclick="document.querySelector('input[name=message]').value = this.textContent"
                                class="text-left p-3 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            What constitutes reasonable doubt in criminal cases?
                        </button>
                        <button onclick="document.querySelector('input[name=message]').value = this.textContent"
                                class="text-left p-3 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            How has the interpretation of self-defense changed in recent years?
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        const messagesContainer = document.querySelector('.messages-container');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Focus input field on load
        document.querySelector('input[name=message]').focus();
    </script>
</body>
</html>
