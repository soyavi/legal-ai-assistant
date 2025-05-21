<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal AI Assistant</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
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
                            <h1 class="text-xl font-bold text-gray-900">Legal AI Assistant</h1>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Search Section -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-medium mb-4">Search Judicial Sentences</h2>
                <form action="search.php" method="GET" class="space-y-4">
                    <div>
                        <label for="query" class="block text-sm font-medium text-gray-700">Search Query</label>
                        <input type="text" name="query" id="query" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter your search query...">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1: Automatic Summaries -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <h3 class="text-lg font-medium text-gray-900">Automatic Summaries</h3>
                        <p class="mt-2 text-sm text-gray-500">Get concise summaries of judicial sentences including proven facts, applied norms, and final resolutions.</p>
                    </div>
                </div>

                <!-- Feature 2: Case Classification -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <h3 class="text-lg font-medium text-gray-900">Case Classification</h3>
                        <p class="mt-2 text-sm text-gray-500">Automatic classification of cases by type, court, and relevant legal norms.</p>
                    </div>
                </div>

                <!-- Feature 3: Case Comparison -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <h3 class="text-lg font-medium text-gray-900">Case Comparison</h3>
                        <p class="mt-2 text-sm text-gray-500">Compare different sentences to identify similarities, differences, and evolving legal criteria.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
