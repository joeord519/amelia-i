<?php
/*
Template Name: Aircraft Docs Page
*/

get_header();

$tailNumber = get_query_var('tail_number'); // Get Tail Number from URL
$docs_dir = wp_upload_dir()['basedir'] . "/aircraft_docs/$tailNumber/";
$docs_url = wp_upload_dir()['baseurl'] . "/aircraft_docs/$tailNumber/";

if (!file_exists($docs_dir)) {
    mkdir($docs_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['aircraft_doc'])) {
    $category = sanitize_text_field($_POST['category']);
    $fileName = basename($_FILES['aircraft_doc']['name']);
    $filePath = $docs_dir . "$category-$fileName";
    $fileUrl = $docs_url . "$category-$fileName";

    if (move_uploaded_file($_FILES['aircraft_doc']['tmp_name'], $filePath)) {
        $doc_entry = get_option("aircraft_docs_$tailNumber", []);
        $doc_entry[$category][] = $fileUrl;
        update_option("aircraft_docs_$tailNumber", $doc_entry);

        echo "<p style='color: green;'>Document uploaded successfully! <a href='$fileUrl' target='_blank'>$fileName</a></p>";
    } else {
        echo "<p style='color: red;'>Error uploading file.</p>";
    }
}

$aircraft_docs = get_option("aircraft_docs_$tailNumber", []);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Aircraft Documents - <?php echo esc_html($tailNumber); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    <h2>Documents for Aircraft: <?php echo esc_html($tailNumber); ?></h2>

    <!-- Upload Form -->
    <form action="" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="category">Document Category:</label>
            <select name="category" class="form-control">
                <option value="POH">POH</option>
                <option value="Weight & Balance">Weight & Balance</option>
                <option value="Last Annual">Last Annual/100 Hr</option>
                <option value="Last Pitot Static">Last Pitot Static Check</option>
                <option value="Last Transponder">Last Transponder Check</option>
                <option value="Registration">Aircraft Registration</option>
                <option value="Airworthiness">Airworthiness Certificate</option>
                <option value="Checklist">Aircraft Checklist</option>
                <option value="Manuals">Additional Plane Docs & Manuals</option>
            </select>
        </div>
        <div class="form-group">
            <label for="aircraft_doc">Choose File:</label>
            <input type="file" name="aircraft_doc" class="form-control-file" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload Document</button>
    </form>

    <!-- Display Uploaded Documents -->
    <h3 class="mt-4">Available Documents</h3>
    <?php foreach ($aircraft_docs as $category => $docs): ?>
        <h4><?php echo esc_html($category); ?></h4>
        <ul>
            <?php foreach ($docs as $doc): ?>
                <li><a href="<?php echo esc_url($doc); ?>" target="_blank"><?php echo esc_html(basename($doc)); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

</body>
</html>

<?php get_footer(); ?>