<?php
/*
Template Name: Aircraft Document Upload
*/

get_header();

// ✅ Restrict to Admins Only
if (!current_user_can('manage_options')) {
    wp_die('<h2>Access Denied</h2><p>You do not have permission to upload aircraft documents.</p>');
}

// ✅ Define Allowed File Types
$allowed_file_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

// ✅ Fetch Aircraft List from Database
global $wpdb;
$aircraft_tail_numbers = $wpdb->get_col("SELECT tail_number FROM wp_aircraft ORDER BY tail_number ASC");

// ✅ Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['aircraft_doc'])) {
    $tailNumber = sanitize_text_field($_POST['tail_number']);
    $category = sanitize_text_field($_POST['category']);
    $entry_date = !empty($_POST['entry_date']) ? sanitize_text_field($_POST['entry_date']) : null;
    $custom_name = !empty($_POST['custom_name']) ? sanitize_text_field($_POST['custom_name']) : null;

    if (!in_array($tailNumber, $aircraft_tail_numbers)) {
        wp_die('<h2>Error</h2><p>Invalid aircraft selected.</p>');
    }

    // ✅ Get Upload Directory
    $upload_dir = wp_upload_dir();
    $docs_dir = $upload_dir['basedir'] . "/aircraft_docs/$tailNumber/";
    $docs_url = $upload_dir['baseurl'] . "/aircraft_docs/$tailNumber/";

    // ✅ Ensure Directory Exists
    if (!file_exists($docs_dir) && !mkdir($docs_dir, 0777, true) && !is_dir($docs_dir)) {
        wp_die('<h2>Error</h2><p>Failed to create directory.</p>');
    }

    // ✅ Validate File Type
    $file_ext = strtolower(pathinfo($_FILES['aircraft_doc']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_file_types)) {
        wp_die('<h2>Error</h2><p>Invalid file type. Allowed types: PDF, JPG, PNG, DOC, DOCX.</p>');
    }

    // ✅ Generate File Name with Date If Needed
    if ($category === 'Miscellaneous' && $custom_name) {
        $file_name = "$custom_name.$file_ext";
    } elseif (in_array($category, ["Weight & Balance", "Last Annual Airframe", "Last Annual Engine", "Last Annual Propeller", "AD Compliance"]) && $entry_date) {
        $file_name = "$category-$entry_date.$file_ext";
    } else {
        $file_name = "$category.$file_ext";
    }

    $file_path = $docs_dir . $file_name;
    $file_url = $docs_url . $file_name;

    // ✅ Move File to Server
    if (move_uploaded_file($_FILES['aircraft_doc']['tmp_name'], $file_path)) {
        // ✅ Store File in WordPress Options for Retrieval
        $doc_entry = get_option("aircraft_docs_$tailNumber", []);
        $doc_entry[$category][] = $file_url;
        update_option("aircraft_docs_$tailNumber", $doc_entry);

        // ✅ Success Message with Navigation
        echo "<div class='container text-center mt-5'>";
        echo "<h2>Document Uploaded Successfully!</h2>";
        echo "<p><strong>File:</strong> <a href='$file_url' target='_blank'>$file_name</a></p>";
        echo "<a href='" . site_url('/aircraft-doc-upload/') . "' class='btn btn-primary'>Upload Another Document</a> ";
        echo "<a href='" . site_url("/$tailNumber-aircraft-documents/") . "' class='btn btn-success'>View Aircraft Documents</a>";
        echo "</div>";
        exit;
    } else {
        wp_die('<h2>Error</h2><p>Failed to upload document.</p>');
    }
}

// ✅ Define Document Categories
$doc_categories = [
    "Pre-Flight Checklist" => "Pre-Flight & Post-Flight Checklist",
    "Airworthiness" => "Airworthiness Certificate",
    "Registration" => "Registration Document",
    "POH" => "Pilot’s Operational Handbook",
    "Weight & Balance" => "Weight & Balance Sheet",
    "Last Annual Airframe" => "Last 100 Hr / Annual Entry - Airframe",
    "Last Annual Engine" => "Last 100 Hr / Annual Entry - Engine",
    "Last Annual Propeller" => "Last 100 Hr / Annual Entry - Propeller",
    "AD Compliance" => "Applicable AD List & Compliance Records",
    "Miscellaneous" => "Miscellaneous Document"
];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Aircraft Document Upload</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
        body {
            text-align: center;
            padding: 20px;
        }
        .upload-container {
            max-width: 500px;
            margin: 0 auto;
            text-align: left;
        }
    </style>
</head>
<body>

    <h2>Upload Aircraft Document</h2>
    
    <div class="upload-container">
        <form action="" method="post" enctype="multipart/form-data">
            <!-- ✅ Select Aircraft -->
            <div class="form-group">
                <label for="tail_number">Aircraft Tail Number:</label>
                <select name="tail_number" class="form-control" required>
                    <option value="">-- Select Aircraft --</option>
                    <?php foreach ($aircraft_tail_numbers as $tail): ?>
                        <option value="<?php echo esc_attr($tail); ?>"><?php echo esc_html($tail); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ✅ Select Document Category -->
            <div class="form-group">
                <label for="category">Document Category:</label>
                <select name="category" id="doc-category" class="form-control" required>
                    <?php foreach ($doc_categories as $key => $category): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ✅ Custom Name for Miscellaneous Docs -->
            <div class="form-group" id="custom-name-group" style="display: none;">
                <label for="custom_name">Enter Document Name:</label>
                <input type="text" name="custom_name" class="form-control">
            </div>

            <!-- ✅ Date Entry for Weight & Balance & 100 Hr Entries -->
            <div class="form-group" id="date-entry-group" style="display: none;">
                <label for="entry_date">Date of Logbook Entry (YYYY-MM-DD):</label>
                <input type="date" name="entry_date" class="form-control">
            </div>

            <!-- ✅ File Upload -->
            <div class="form-group">
                <label for="aircraft_doc">Choose File:</label>
                <input type="file" name="aircraft_doc" class="form-control-file" required>
            </div>

            <button type="submit" class="btn btn-primary">Upload Document</button>
        </form>
    </div>

    <script>
        document.getElementById('doc-category').addEventListener('change', function() {
            let dateEntryGroup = document.getElementById('date-entry-group');
            let customNameGroup = document.getElementById('custom-name-group');
            let selectedValue = this.value;
            
            let requiresDate = ["Weight & Balance", "Last Annual Airframe", "Last Annual Engine", "Last Annual Propeller", "AD Compliance"];
            dateEntryGroup.style.display = requiresDate.includes(selectedValue) ? "block" : "none";
            customNameGroup.style.display = selectedValue === "Miscellaneous" ? "block" : "none";
        });
    </script>

</body>
</html>

<?php get_footer(); ?>

