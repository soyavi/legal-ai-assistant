<?php
require_once 'config.php';

// Get and validate case ID
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    // Fetch case details with analysis
    $sql = "SELECT s.*, c.case_type, c.legal_norms, c.is_precedent,
            a.summary, a.proven_facts, a.applied_norms, a.court_criteria, 
            a.final_resolution, a.dissenting_opinion
            FROM sentences s
            LEFT JOIN classifications c ON s.id = c.sentence_id
            LEFT JOIN analysis a ON s.id = a.sentence_id
            WHERE s.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$case) {
        throw new Exception('Case not found');
    }

    // If no analysis exists, generate it using OpenAI
    if (empty($case['summary'])) {
        $prompt = "Analyze this legal case in detail:\n\n" . 
                 $case['content'] . "\n\nProvide:\n" .
                 "1. Summary\n2. Proven Facts\n3. Applied Legal Norms\n" .
                 "4. Court's Criteria\n5. Final Resolution\n" .
                 "6. Dissenting Opinion (if any)";

        $analysis = callOpenAI($prompt);
        
        if (isset($analysis['choices'][0]['message']['content'])) {
            // Parse the response into sections
            $content = $analysis['choices'][0]['message']['content'];
            $sections = [
                'summary' => '',
                'proven_facts' => '',
                'applied_norms' => '',
                'court_criteria' => '',
                'final_resolution' => '',
                'dissenting_opinion' => ''
            ];
            
            // Simple parsing (in production, you'd want more robust parsing)
            foreach ($sections as $key => &$value) {
                if (preg_match("/\b$key\b.*?:(.*?)(?=\b(?:" . implode('|', array_keys($sections)) . ")\b|$)/si", $content, $matches)) {
                    $value = trim($matches[1]);
                }
            }

            // Store the analysis
            $stmt = $pdo->prepare("INSERT INTO analysis 
                    (sentence_id, summary, proven_facts, applied_norms, 
                     court_criteria, final_resolution, dissenting_opinion)
                    VALUES 
                    (:id, :summary, :proven_facts, :applied_norms,
                     :court_criteria, :final_resolution, :dissenting_opinion)");
            
            $stmt->execute([
                'id' => $id,
                'summary' => $sections['summary'],
                'proven_facts' => $sections['proven_facts'],
                'applied_norms' => $sections['applied_norms'],
                'court_criteria' => $sections['court_criteria'],
                'final_resolution' => $sections['final_resolution'],
                'dissenting_opinion' => $sections['dissenting_opinion']
            ]);

            // Update case array with new analysis
            $case = array_merge($case, $sections);
        }
    }

    // Fetch related cases
    $sql = "SELECT s.id, s.title, s.court, s.date_issued, c.case_type
            FROM sentences s
            LEFT JOIN classifications c ON s.id = c.sentence_id
            WHERE c.case_type = :case_type 
            AND s.id != :id
            ORDER BY s.date_issued DESC
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'case_type' => $case['case_type'],
        'id' => $id
    ]);
    $related_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($case['title']); ?> - Legal AI Assistant</title>
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
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Case Header -->
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="p-6">
                        <h1 class="text-2xl font-bold text-gray-900 mb-4">
                            <?php echo htmlspecialchars($case['title']); ?>
                        </h1>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                            <div>Court: <?php echo htmlspecialchars($case['court']); ?></div>
                            <div>Case Number: <?php echo htmlspecialchars($case['case_number']); ?></div>
                            <div>Date: <?php echo date('F j, Y', strtotime($case['date_issued'])); ?></div>
                            <div>Type: <?php echo htmlspecialchars($case['case_type']); ?></div>
                            <?php if ($case['is_precedent']): ?>
                                <div class="text-blue-600 font-medium">Precedent Case</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- AI Analysis -->
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">AI Analysis</h2>
                        
                        <!-- Summary -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Summary</h3>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['summary'])); ?></p>
                        </div>

                        <!-- Proven Facts -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Proven Facts</h3>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['proven_facts'])); ?></p>
                        </div>

                        <!-- Applied Legal Norms -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Applied Legal Norms</h3>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['applied_norms'])); ?></p>
                        </div>

                        <!-- Court's Criteria -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Court's Criteria</h3>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['court_criteria'])); ?></p>
                        </div>

                        <!-- Final Resolution -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Final Resolution</h3>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['final_resolution'])); ?></p>
                        </div>

                        <?php if (!empty($case['dissenting_opinion'])): ?>
                            <!-- Dissenting Opinion -->
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-900 mb-2">Dissenting Opinion</h3>
                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($case['dissenting_opinion'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Related Cases -->
                <?php if (!empty($related_cases)): ?>
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Related Cases</h2>
                            <div class="divide-y divide-gray-200">
                                <?php foreach ($related_cases as $related): ?>
                                    <div class="py-4">
                                        <a href="view.php?id=<?php echo $related['id']; ?>" 
                                           class="block hover:bg-gray-50">
                                            <h3 class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($related['title']); ?>
                                            </h3>
                                            <div class="mt-1 flex space-x-4 text-sm text-gray-500">
                                                <div><?php echo htmlspecialchars($related['court']); ?></div>
                                                <div><?php echo date('M j, Y', strtotime($related['date_issued'])); ?></div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Compare Cases Button -->
                <div class="mt-6 flex justify-end">
                    <a href="compare.php?case1=<?php echo $id; ?>" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Compare with Another Case
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
