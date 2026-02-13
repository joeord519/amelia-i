<?php
// Location: /wp-content/plugins/amelia-chatbot/send-to-openai.php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/openai-config.php';

$conn = getDB();
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

function isSimilarToTrained($incoming, $pdo, $category = null, $threshold = 80) {
  $query = "SELECT question FROM wp_amelia_knowledge_base WHERE active = 1";
  $params = [];

  if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
  }

  $stmt = $pdo->prepare($query);
  $stmt->execute($params);
  $trained = $stmt->fetchAll(PDO::FETCH_COLUMN);

  foreach ($trained as $trained_q) {
    similar_text(strtolower($incoming), strtolower($trained_q), $percent);
    if ($percent >= $threshold) {
      return true;
    }
  }
  return false;
}

if (!$apiKey) {
  file_put_contents(__DIR__ . '/chat_error.log', "❌ API KEY is missing or not defined.\n", FILE_APPEND);
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$requestedCategory = $input['category'] ?? 'general';

file_put_contents(__DIR__ . '/chat_debug.log', "Incoming Message: $userMessage\nCategory: $requestedCategory\n", FILE_APPEND);

$trainingData = '';
$linkAdditions = '';

try {
  $stmt = $conn->prepare("SELECT * FROM wp_amelia_knowledge_base WHERE active = 1");
  $stmt->execute();
  $results = $stmt->fetchAll();

  $categoryMatches = [];
  $generalMatches = [];
  $matchedSomething = false;

  foreach ($results as $row) {
    $matched = stripos($userMessage, $row['question']) !== false;

    if ($matched) {
      $matchedSomething = true;
    }

    if ($matched || $row['always_include']) {
      $formatted = "Q: {$row['question']}\nA: {$row['answer']}";

      $resources = [];

      if (!empty($row['mandatory_link'])) {
        $resources[] = $row['mandatory_link']; // Already full HTML
      }
      if (!empty($row['source_url'])) {
        $resources[] = "<div>ℹ️ Reference: {$row['source_url']}</div>";
      }
      if (!empty($row['pdf_url'])) {
        $resources[] = "<div>📄 <a href='{$row['pdf_url']}' target='_blank'>PDF Resource</a></div>";
      }
      if (!empty($row['image_url'])) {
        $resources[] = '<a href="' . htmlspecialchars($row['image_url']) . '" target="_blank"><img src="' . htmlspecialchars($row['image_url']) . '" alt="Resource Image" class="chat-thumbnail"></a>';
      }

      if (!empty($resources)) {
        $formatted .= "\n" . implode("\n", $resources);
      }

      $formatted .= "\n";

      if ($row['category'] === $requestedCategory) {
        $categoryMatches[] = $formatted;
      } elseif ($row['category'] === 'general') {
        $generalMatches[] = $formatted;
      }
    }
  }

  $trainingData = implode('', array_merge($categoryMatches, $generalMatches));

  // ✅ Log unanswered if no actual question matched
  if (!empty($userMessage) && !isSimilarToTrained($userMessage, $conn, $requestedCategory)) {
    $logStmt = $conn->prepare("INSERT INTO wp_amelia_unanswered (question, category) VALUES (?, ?)");
    $logStmt->execute([$userMessage, $requestedCategory]);
    file_put_contents(__DIR__ . '/chat_debug.log', "🔍 No fuzzy match found. Logged to unanswered.\n", FILE_APPEND);
  }

} catch (PDOException $e) {
  file_put_contents(__DIR__ . '/chatbot_sql_error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
}

// 🧠 Base prompt
$systemMessage = "You are Amelia, a friendly assistant at Piston Aviation. Respond with love, clarity, and confidence. If there are helpful resources provided (like links or PDFs), include them naturally in your answer.";

if ($trainingData) {
  $systemMessage .= "\n\nHere is some relevant knowledge from your training data:\n" . $trainingData;
}

file_put_contents(__DIR__ . '/chat_debug.log', "🧠 Final Prompt:\n" . $systemMessage . "\n\n", FILE_APPEND);

$messages = [
  ["role" => "system", "content" => $systemMessage],
  ["role" => "user", "content" => $userMessage]
];

$payload = json_encode([
  'model' => 'gpt-4o',
  'messages' => $messages,
  'temperature' => 0.8
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_POST => true
]);

$response = curl_exec($ch);

if (!$response) {
  file_put_contents(__DIR__ . '/chat_error.log', "❌ CURL ERROR: " . curl_error($ch) . PHP_EOL, FILE_APPEND);
}

file_put_contents(__DIR__ . '/chat_response.log', "📥 Raw OpenAI Response:\n" . $response . "\n\n", FILE_APPEND);

$decoded = json_decode($response, true);
file_put_contents(__DIR__ . '/chat_debug.log', "✅ Decoded GPT Response:\n" . print_r($decoded, true) . "\n\n", FILE_APPEND);

$reply = $decoded['choices'][0]['message']['content'] ?? 'Sorry, something went wrong.';

// 🛠 Normalize and check for custom action
$normalizedReply = strtolower(trim(strip_tags($reply)));

file_put_contents(__DIR__ . '/chat_debug.log', "🧪 Normalized Reply: " . $normalizedReply . PHP_EOL, FILE_APPEND);

if (strpos($normalizedReply, '[custom_action]accelerated_signup_flow') !== false) {
  echo json_encode(['reply' => '[trigger_registration_flow]']);
  exit;
}

// 🧠 Default response
echo json_encode(['reply' => $reply]);
