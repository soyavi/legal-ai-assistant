<?php
require_once 'config.php';

// Get case IDs
$case1_id = isset($_GET['case1']) ? filter_var($_GET['case1'], FILTER_VALIDATE_INT) : 0;
$case2_id = isset($_GET['case2']) ? filter_var($_GET['case2'], FILTER_VALIDATE_INT) : 0;

$error = null;
$cases = [];
$comparison = null;

try {
    // If we have the first case ID but not the second, show case selection
    if ($case1_id && !$case2_id) {
        // Fetch first case details
        $stmt = $pdo->prepare("SELECT id, title, court, date_issued, case_number FROM sentences WHERE id = ?");
        $stmt->execute([$case1_id]);
        $cases['case1'] = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cases['case1']) {
            throw new Exception('First case not found');
        }

        // Fetch potential cases to compare with
        $sql = "SELECT s.id, s.title, s.court, s.date_issued, s.case_number, c.case_type 
                FROM sentences s
                LEFT JOIN classifications c ON s.id = c.sentence_id
                WHERE s.id != :case1_id
                ORDER BY s.date_issued DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['case1_id' => $case1_id]);
        $potential_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // If we have both case IDs, perform comparison
    elseif ($case1_id && $case2_id) {
        // Fetch both cases
        $stmt = $pdo->prepare("
            SELECT s.*, c.case_type, a.summary, a.proven_facts, a.applied_norms, 
                   a.court_criteria, a.final_resolution
            FROM sentences s
            LEFT JOIN classifications c ON s.id = c.sentence_id
            LEFT JOIN analysis a ON s.id = a.sentence_id
            WHERE s.id IN (?, ?)
        ");
        $stmt->execute([$case1_id, $case2_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $case) {
            if ($case['id'] == $case1_id) {
                $cases['case1'] = $case;
            } else {
                $cases['case2'] = $case;
            }
        }

        // Check if comparison already exists
        $stmt = $pdo->prepare("
            SELECT * FROM comparisons 
            WHERE (sentence_id_1 = ? AND sentence_id_2 = ?)
            OR (sentence_id_1 = ? AND sentence_id_2 = ?)
        ");
        $stmt->execute([$case1_id, $case2_id, $case2_id, $case1_id]);
        $comparison = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no comparison exists, generate one using OpenAI
        if (!$comparison) {
            $prompt = "Compare these two legal cases:\n\n" .
                     "Case 1:\n" . $cases['case1']['content'] . "\n\n" .
                     "Case 2:\n" . $cases['case2']['content'] . "\n\n" .
                     "Provide analysis in these sections:\n" .
                     "1. Similarities in legal reasoning and principles\n" .
                     "2. Key differences in approach or outcome\n" .
                     "3. Evolution of legal criteria (if cases are from different dates)";

            $analysis = callOpenAI($prompt);
            
            if (isset($analysis['choices'][0]['message']['content'])) {
                $content = $analysis['choices'][0]['message']['content'];
                
                // Parse sections (in production, use more robust parsing)
                $sections = [
                    'similarities' => '',
                    'differences' => '',
                    'evolution_notes' => ''
                ];
                
                foreach ($sections as $key => &$value) {
                    if (preg_match("/\b$key\b.*?:(.*?)(?=\b(?:" . implode('|', array_keys($sections)) . ")\b|$)/si", $content, $matches)) {
                        $value = trim($matches[1]);
                    }
                }

                // Store comparison
                $stmt = $pdo->prepare("
                    INSERT INTO comparisons 
                    (sentence_id_1, sentence_id_2, similarities, differences, evolution_notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $case1_id,
                    $case2_id,
                    $sections['similarities'],
                    $sections['differences'],
                    $sections['evolution_notes']
                ]);

                $comparison = [
                    'similarities' => $sections['similarities'],
                    'differences' => $sections['differences'],
                    'evolution_notes' => $sections['evolution_notes']
                ];
            }
        }
    } else {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Cases - Legal AI Assistant</title>
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
            <?php elseif (!$case2_id): ?>
                <!-- Case Selection -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            Select a Case to Compare With:
                            <span class="text-gray-600">
                                <?php echo htmlspecialchars($cases['case1']['title']); ?>
                            </span>
                        </h2>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($potential_cases as $case): ?>
                                <div class="py-4">
                                    <a href="?case1=<?php echo $case1_id; ?>&case2=<?php echo $case['id']; ?>" 
                                       class="block hover:bg-gray-50 rounded-lg p-4">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                        </h3>
                                        <div class="mt-1 flex space-x-4 text-sm text-gray-500">
                                            <div><?php echo htmlspecialchars($case['court']); ?></div>
                                            <div><?php echo htmlspecialchars($case['case_type']); ?></div>
                                            <div><?php echo date('M j, Y', strtotime($case['date_issued'])); ?></div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Comparison Results -->
                <div class="space-y-6">
                    <!-- Cases Overview -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-6">
                                <!-- Case 1 -->
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($cases['case1']['title']); ?>
                                    </h3>
                                    <div class="text-sm text-gray-500">
                                        <div>Court: <?php echo htmlspecialchars($cases['case1']['court']); ?></div>
                                        <div>Date: <?php echo date('M j, Y', strtotime($cases['case1']['date_issued'])); ?></div>
                                        <div>Type: <?php echo htmlspecialchars($cases['case1']['case_type']); ?></div>
                                    </div>
                                </div>

                                <!-- Case 2 -->
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($cases['case2']['title']); ?>
                                    </h3>
                                    <div class="text-sm text-gray-500">
                                        <div>Court: <?php echo htmlspecialchars($cases['case2']['court']); ?></div>
                                        <div>Date: <?php echo date('M j, Y', strtotime($cases['case2']['date_issued'])); ?></div>
                                        <div>Type: <?php echo htmlspecialchars($cases['case2']['case_type']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Analysis -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-6">AI Analysis</h2>

                            <!-- Similarities -->
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-900 mb-2">Similarities</h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo nl2br(htmlspecialchars($comparison['similarities'])); ?>
                                </p>
                            </div>

                            <!-- Differences -->
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-900 mb-2">Key Differences</h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo nl2br(htmlspecialchars($comparison['differences'])); ?>
                                </p>
                            </div>

                            <!-- Evolution -->
                            <?php if (!empty($comparison['evolution_notes'])): ?>
                                <div class="mb-6">
                                    <h3 class="text-sm font-medium text-gray-900 mb-2">Evolution of Legal Criteria</h3>
                                    <p class="text-sm text-gray-600">
                                        <?php echo nl2br(htmlspecialchars($comparison['evolution_notes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-4">
                        <a href="view.php?id=<?php echo $case1_id; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            View Case 1
                        </a>
                        <a href="view.php?id=<?php echo $case2_id; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            View Case 2
                        </a>
                        <a href="compare.php?case1=<?php echo $case1_id; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Compare with Different Case
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
