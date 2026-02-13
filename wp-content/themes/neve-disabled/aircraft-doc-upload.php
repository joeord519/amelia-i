<?php
/*
Template Name: Aircraft Doc Upload
*/

get_header();

$tailNumber = get_query_var('tail_number'); // Get Tail Number from URL
$docs_dir = wp_upload_dir()['basedir'] . "/aircraft_docs/$tailNumber/";
$docs_url = wp_upload_dir()['baseurl'] . "/aircraft_docs/$tailNumber/";

if (!file_exists($docs_dir)) {
    mkdir($docs_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['aircraft_doc']) && isset($_POST['tail_number'])) {
    $tailNumber = sanitize_text_field($_POST['tail_number']);
    $category = sanitize_text_field($_POST['category']);
    $fileName = basename($_FILES['aircraft_doc']['name']);
    $customName = (!empty($_POST['custom_name']) && $category === 'Manuals') ? sanitize_text_field($_POST['custom_name']) : $fileName;

    // ✅ Ensure each aircraft has its own directory
    $docs_dir = wp_upload_dir()['basedir'] . "/aircraft_docs/$tailNumber/";
    $docs_url = wp_upload_dir()['baseurl'] . "/aircraft_docs/$tailNumber/";
    if (!file_exists($docs_dir)) {
        mkdir($docs_dir, 0777, true);
    }

    $filePath = $docs_dir . "$category-$fileName";
    $fileUrl = $docs_url . "$category-$fileName";

    if (move_uploaded_file($_FILES['aircraft_doc']['tmp_name'], $filePath)) {
        $doc_entry = get_option("aircraft_docs_$tailNumber", []);
        $doc_entry[$category][] = ['name' => $customName, 'url' => $fileUrl];

        // ✅ Store document page URL in wp_aircraft table
        global $wpdb;
        $docPageUrl = "https://amelia-i.com/wp-content/plugins/fullcalendar_test/customer-calendar/doc-pages/$tailNumber-docs.html";
        $wpdb->update(
            'wp_aircraft',
            ['aircraft_docs_page' => $docPageUrl],
            ['tail_number' => $tailNumber],
            ['%s'],
            ['%s']
        );

        update_option("aircraft_docs_$tailNumber", $doc_entry);

        echo "<p style='color: green;'>Document uploaded successfully! <a href='$fileUrl' target='_blank'>$customName</a></p>";
    } else {
        echo "<p style='color: red;'>Error uploading file.</p>";
    }
}

$aircraft_docs = get_option("aircraft_docs_$tailNumber", []);
?>

<!-- ✅ Upload Form -->
<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="tail_number">Tail Number:</label>
        <input type="text" name="tail_number" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="category">Document Category:</label>
        <select name="category" class="form-control" id="doc-category">
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

    <div class="form-group" id="custom-name-field" style="display: none;">
        <label for="custom_name">Custom Document Name:</label>
        <input type="text" name="custom_name" class="form-control">
    </div>

    <div class="form-group">
        <label for="aircraft_doc">Choose File:</label>
        <input type="file" name="aircraft_doc" class="form-control-file" required>
    </div>

    <button type="submit" class="btn btn-primary">Upload Document</button>
</form>

<!-- ✅ Display Uploaded Documents -->
<h3 class="mt-4">Available Documents for <?php echo esc_html($tailNumber); ?></h3>
<?php if (!empty($aircraft_docs)): ?>
    <?php foreach ($aircraft_docs as $category => $docs): ?>
        <h4><?php echo esc_html($category); ?></h4>
        <ul>
            <?php foreach ($docs as $doc): ?>
                <li><a href="<?php echo esc_url($doc['url']); ?>" target="_blank"><?php echo esc_html($doc['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
<?php else: ?>
    <p>No documents available for this aircraft.</p>
<?php endif; ?>

<script>
    document.getElementById('doc-category').addEventListener('change', function() {
        let customField = document.getElementById('custom-name-field');
        if (this.value === 'Manuals') {
            customField.style.display = 'block';
        } else {
            customField.style.display = 'none';
        }
    });
</script>

<?php get_footer(); ?>
