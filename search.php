<?php
require_once 'config.php';

// Get and sanitize search query
$query = isset($_GET['query']) ? sanitizeInput($_GET['query']) : '';

// Initialize results array
$results = [];
$error = null;

if (!empty($query)) {
    try {
        // Prepare the search query
        $sql = "SELECT s.*, c.case_type, a.summary 
                FROM sentences s 
                LEFT JOIN classifications c ON s.id = c.sentence_id 
                LEFT JOIN analysis a ON s.id = a.sentence_id 
                WHERE s.content LIKE :query 
                OR s.title LIKE :query 
                OR c.case_type LIKE :query 
                ORDER BY s.date_issued DESC 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['query' => "%$query%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If we have results, enhance them with OpenAI analysis
        if (!empty($results) && empty($results[0]['summary'])) {
            foreach ($results as &$result) {
                // Prepare prompt for OpenAI
                $prompt = "Analyze this legal sentence and provide a brief summary:\n\n" . 
                         $result['content'] . "\n\nProvide a concise summary including:\n" .
                         "1. Key facts\n2. Legal principles applied\n3. Final decision";

                // Call OpenAI API
                $analysis = callOpenAI($prompt);
                
                if (isset($analysis['choices'][0]['message']['content'])) {
                    // Store the analysis in the database
                    $stmt = $pdo->prepare("INSERT INTO analysis (sentence_id, summary) VALUES (:id, :summary)");
                    $stmt->execute([
                        'id' => $result['id'],
                        'summary' => $analysis['choices'][0]['message']['content']
                    ]);
                    
                    $result['summary'] = $analysis['choices'][0]['message']['content'];
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Legal AI Assistant</title>
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
            <!-- Search Form -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <form action="search.php" method="GET" class="flex gap-4">
                    <input type="text" name="query" value="<?php echo $query; ?>" 
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           placeholder="Enter your search query...">
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Search
                    </button>
                </form>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Results -->
            <?php if (!empty($query)): ?>
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            Search Results for "<?php echo $query; ?>"
                        </h2>
                    </div>
                    
                    <?php if (empty($results)): ?>
                        <div class="p-6 text-center text-gray-500">
                            No results found for your search query.
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($results as $result): ?>
                                <div class="p-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                                        <?php echo $result['title']; ?>
                                    </h3>
                                    <div class="flex space-x-4 text-sm text-gray-500 mb-4">
                                        <div>Court: <?php echo $result['court']; ?></div>
                                        <div>Case Type: <?php echo $result['case_type']; ?></div>
                                        <div>Date: <?php echo date('M j, Y', strtotime($result['date_issued'])); ?></div>
                                    </div>
                                    <?php if (!empty($result['summary'])): ?>
                                        <div class="prose max-w-none">
                                            <h4 class="text-sm font-medium text-gray-900 mb-2">AI-Generated Summary:</h4>
                                            <p class="text-sm text-gray-600">
                                                <?php echo nl2br($result['summary']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-4">
                                        <a href="view.php?id=<?php echo $result['id']; ?>" 
                                           class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                            View Full Case →
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
