<?php
/*
Template Name: Aircraft Document Page
*/

get_header();

// ✅ Get Tail Number from URL (with fallback extraction)
$tailNumber = get_query_var('tail_number') ? sanitize_text_field(get_query_var('tail_number')) : null;

if (!$tailNumber && isset($_SERVER['REQUEST_URI'])) {
    preg_match('/\/([A-Za-z0-9]+)-aircraft-documents\//', $_SERVER['REQUEST_URI'], $matches);
    if (!empty($matches[1])) {
        $tailNumber = strtoupper(sanitize_text_field($matches[1]));
    }
}

// ✅ If still no tail number, show message
if (!$tailNumber) {
    echo "<h2 class='text-center mt-5'>No Aircraft Selected.</h2>";
    exit;
}

// ✅ Retrieve stored documents
$aircraft_docs = get_option("aircraft_docs_$tailNumber", []);

// ✅ Define Document Categories & Limits
$doc_categories = [
    "Pre-Flight Checklist" => ["name" => "📋 Pre-Flight & Post-Flight Checklist", "max" => 1],
    "Airworthiness" => ["name" => "🛫 Airworthiness Certificate", "max" => 1],
    "Registration" => ["name" => "📜 Registration Document", "max" => 1],
    "POH" => ["name" => "📖 Pilot’s Operational Handbook", "max" => 1],
    "Weight & Balance" => ["name" => "⚖ Weight & Balance Sheet", "max" => 5],
    "Last Annual Airframe" => ["name" => "🛠 Last 100 Hr / Annual Entry - Airframe", "max" => 5],
    "Last Annual Engine" => ["name" => "⚙ Last 100 Hr / Annual Entry - Engine", "max" => 5],
    "Last Annual Propeller" => ["name" => "🔩 Last 100 Hr / Annual Entry - Propeller", "max" => 5],
    "AD Compliance" => ["name" => "✅ Applicable AD List & Compliance Records", "max" => 5],
    "Miscellaneous" => ["name" => "📂 Miscellaneous Documents", "max" => 10]
];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Aircraft Documents - <?php echo esc_html($tailNumber); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
      body {
    text-align: center;
    padding: 20px;
}
.document-container {
    max-width: 600px;
    margin: 0 auto; /* ✅ Centers the entire document section */
    display: flex;
    flex-direction: column;
    align-items: center; /* ✅ Ensures everything stays centered */
}
h2 {
    text-align: center; /* ✅ Centers the title */
}
.doc-wrapper {
    width: 100%;
    display: flex;
    justify-content: center; /* ✅ Centers the entire document list */
}
.doc-content {
    width: 100%;
    max-width: 600px;
    text-align: left; /* ✅ Ensures text remains left-aligned */
}
.doc-category {
    font-weight: bold;
    margin-top: 30px;
    font-size: 18px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px; /* ✅ Adds spacing between the icon and text */
    width: 100%;
}
.doc-list {
    text-align: left;
    padding-left: 20px;
}
.doc-list a {
    display: block;
    padding: 8px 0;
    font-size: 16px;
    text-decoration: none;
    color: #007bff;
}
.doc-list a:hover {
    text-decoration: underline;
}
.no-doc {
    font-style: italic;
    color: #888;
    font-size: 14px;
}

    </style>
</head>
<body>

   <h2>Aircraft Documents - <?php echo esc_html($tailNumber); ?>
    <?php
    global $wpdb;
    $slack_url = $wpdb->get_var($wpdb->prepare("SELECT slack_channel_url FROM wp_aircraft WHERE tail_number = %s", $tailNumber));
    if ($slack_url):
    ?>
        <a href="<?php echo esc_url($slack_url); ?>" target="_blank" title="Open Slack Squawk Channel">
            💬 Join Squawk Channel
        </a>
    <?php endif; ?>
</h2>
    
    <div class="document-container">
        <?php foreach ($doc_categories as $key => $category): ?>
            <div class="doc-category"><?php echo esc_html($category['name']); ?></div>
            <div class="doc-list">
                <?php 
                if (!empty($aircraft_docs[$key])): 
                    $docs_to_display = array_slice($aircraft_docs[$key], -$category['max']); // ✅ Limit history
                    foreach ($docs_to_display as $doc): 
                ?>
                        <a href="<?php echo esc_url($doc); ?>" target="_blank">📂 <?php echo esc_html(basename($doc)); ?></a>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <p class="no-doc">Document Coming Soon</p>
                <?php 
                endif; 
                ?>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>

<?php get_footer(); ?>
