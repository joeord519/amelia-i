<?php
require_once __DIR__ . '/db_connect.php';
session_start();

if (empty($_SESSION['admin_logged_in'])) {
  header('Location: admin-login.php');
  exit;
}

$conn = getDB();

// ✅ Handle New Wing Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_wing'])) {
  $wingName = trim($_POST['wing_name']);
  $maxCfis = intval($_POST['max_cfis']);

  if ($wingName && $maxCfis > 0) {
    $stmt = $conn->prepare("INSERT INTO wp_cfi_wings (wing_name, max_cfis, status) VALUES (?, ?, 'Inactive')");
    $stmt->execute([$wingName, $maxCfis]);
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
  }
  exit;
}

// ✅ Handle AJAX status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
  $stmt = $conn->prepare("UPDATE wp_cfi_wings SET status = ? WHERE id = ?");
  $stmt->execute([$_POST['status'], $_POST['id']]);
  echo json_encode(['success' => true]);
  exit;
}

// ✅ Handle Logo Upload with Resize and Sanitized Filename
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logo_wing_id']) && isset($_FILES['logo_file'])) {
  $wingId = intval($_POST['logo_wing_id']);

  $rawName = pathinfo($_FILES['logo_file']['name'], PATHINFO_FILENAME);
  $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
  $mime = mime_content_type($_FILES['logo_file']['tmp_name']);

  $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!in_array($mime, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are supported.']);
    exit;
  }

  $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $rawName));
  $fileName = 'wing_' . $wingId . '_' . $slug . '.png';

  $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/cfi_wings/';
  $webPath = '/wp-content/uploads/cfi_wings/';
  $targetPath = $uploadDir . $fileName;
  $logoUrl = $webPath . $fileName;

  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  list($w, $h) = getimagesize($_FILES['logo_file']['tmp_name']);
  switch ($mime) {
    case 'image/jpeg':
      $src = imagecreatefromjpeg($_FILES['logo_file']['tmp_name']); break;
    case 'image/png':
      $src = imagecreatefrompng($_FILES['logo_file']['tmp_name']); break;
    case 'image/webp':
      $src = imagecreatefromwebp($_FILES['logo_file']['tmp_name']); break;
    default:
      $src = null;
  }

  if (!$src) {
    echo json_encode(['success' => false, 'message' => 'Unsupported image type or GD not enabled.']);
    exit;
  }

  $size = min($w, $h);
$cropped = imagecrop($src, ['x' => ($w - $size) / 2, 'y' => ($h - $size) / 2, 'width' => $size, 'height' => $size]);

if (!$cropped) {
  echo json_encode(['success' => false, 'message' => 'Failed to crop image.']);
  exit;
}

$resized = imagecreatetruecolor(300, 300);

// ✅ Preserve transparency
imagesavealpha($resized, true);
$transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
imagefill($resized, 0, 0, $transparent);

// ✅ Copy cropped into resized
imagecopyresampled($resized, $cropped, 0, 0, 0, 0, 300, 300, $size, $size);


  ob_start();
  $writeResult = imagepng($resized, $targetPath);
  $gdErrors = ob_get_clean();

  if (!$writeResult || !file_exists($targetPath)) {
    error_log("⚠️ PNG save failed. GD error: $gdErrors");
    echo json_encode(['success' => false, 'message' => 'Could not save resized logo image.']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE wp_cfi_wings SET logo_url = ? WHERE id = ?");
  $stmt->execute([$logoUrl, $wingId]);

  echo json_encode(['success' => true, 'logo' => $logoUrl]);
  exit;
}

// ✅ Handle Logo Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logo_wing_id'])) {
  $wingId = intval($_POST['delete_logo_wing_id']);
  $stmt = $conn->prepare("SELECT logo_url FROM wp_cfi_wings WHERE id = ?");
  $stmt->execute([$wingId]);
  $logo = $stmt->fetchColumn();

  if ($logo) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . parse_url($logo, PHP_URL_PATH);
    if (file_exists($filePath)) unlink($filePath);
    $stmt = $conn->prepare("UPDATE wp_cfi_wings SET logo_url = NULL WHERE id = ?");
    $stmt->execute([$wingId]);
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'No logo found.']);
  }
  exit;
}

// ✅ Fetch wings
$stmt = $conn->query("
  SELECT w.id AS wing_id, w.wing_name, w.status, w.max_cfis, w.logo_url,
         COUNT(c.cfi_id) AS current_count
  FROM wp_cfi_wings w
  LEFT JOIN wp_cfis c ON w.id = c.wing_id
  GROUP BY w.id, w.wing_name, w.status, w.max_cfis, w.logo_url
  ORDER BY w.id ASC
");
$wings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage Wings</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
  <div class="container mt-4">
    <div class="row">
      <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>
      <div class="col-md-9">
        <h2 class="mb-4">🩶 Manage Wings</h2>
    <div class="mb-4 p-3 border rounded bg-white shadow-sm">
  <h5 class="mb-3">➕ Add a New Wing</h5>
  <form id="addWingForm" class="form-inline d-flex flex-wrap gap-2">
    <input type="text" name="wing_name" class="form-control me-2" placeholder="Wing Name" required style="min-width: 200px;">
    <input type="number" name="max_cfis" class="form-control me-2" placeholder="Max CFIs" required min="1" style="max-width: 120px;">
    <button type="submit" class="btn btn-success">Add Wing</button>
  </form>
</div>
    <div class="table-responsive shadow-sm bg-white rounded p-4">
      <table class="table table-bordered align-middle table-striped">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Wing Name</th>
            <th>Logo</th>
            <th>Upload New Logo</th>
            <th>CFIs Assigned</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($wings as $wing): ?>
          <tr>
            <td><?= $wing['wing_id']; ?></td>
            <td><?= htmlspecialchars($wing['wing_name']); ?></td>
            <td>
              <?php if ($wing['logo_url']): ?>
                <img 
                  src="<?= $wing['logo_url']; ?>" 
                  alt="Logo" 
                  width="50" 
                  height="50" 
                  title="Click to view full logo" 
                  style="object-fit: contain; cursor: zoom-in;" 
                  class="zoomable-logo" 
                  data-full="<?= $wing['logo_url']; ?>">
                <form class="delete-logo-form mt-1" method="post">
                  <input type="hidden" name="delete_logo_wing_id" value="<?= $wing['wing_id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              <?php else: ?>
                <em>No logo</em>
              <?php endif; ?>
            </td>
            <td>
              <form class="logo-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="logo_wing_id" value="<?= $wing['wing_id']; ?>">
                <input type="file" name="logo_file" accept="image/*" required class="form-control form-control-sm mb-2 previewable">
                <img src="#" class="img-preview d-none mb-2" style="max-height: 50px;">
                <button type="submit" class="btn btn-primary btn-sm">Upload</button>
              </form>
            </td>
            <td><?= $wing['current_count'] . ' / ' . $wing['max_cfis']; ?></td>
            <td>
              <span class="badge bg-<?= $wing['status'] === 'Active' ? 'success' : 'secondary'; ?>" id="status-<?= $wing['wing_id']; ?>">
                <?= $wing['status']; ?>
              </span>
            </td>
            <td>
              <button class="btn btn-outline-<?= $wing['status'] === 'Active' ? 'danger' : 'success'; ?> btn-sm toggle-status"
                      data-id="<?= $wing['wing_id']; ?>"
                      data-status="<?= $wing['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                <?= $wing['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<script>
$(document).ready(function () {
  $('.toggle-status').click(function () {
    const btn = $(this);
    const id = btn.data('id');
    const newStatus = btn.data('status');

    $.post('manage-wings.php', { id, status: newStatus }, function (res) {
      if (res.success) {
        Swal.fire({ icon: 'success', title: '✅ Wing Updated', text: `Wing status set to ${newStatus}`, timer: 1500, showConfirmButton: false })
          .then(() => location.reload());
      }
    }, 'json').fail(() => {
      Swal.fire('Error', 'Could not update wing status.', 'error');
    });
  });

  $('.logo-upload-form').submit(function (e) {
    e.preventDefault();
    const form = $(this)[0];
    const formData = new FormData(form);

    $.ajax({
      url: 'manage-wings.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function (res) {
        if (res.success) {
          Swal.fire({ icon: 'success', title: '🎉 Logo Updated', text: 'The logo was uploaded successfully!', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
          Swal.fire('Upload Failed', res.message || 'Something went wrong.', 'error');
        }
      },
      error: function () {
        Swal.fire('Upload Failed', 'An error occurred during upload.', 'error');
      }
    });
  });

  $('.previewable').on('change', function () {
    const reader = new FileReader();
    const img = $(this).siblings('.img-preview')[0];
    reader.onload = e => {
      img.src = e.target.result;
      img.classList.remove('d-none');
    };
    reader.readAsDataURL(this.files[0]);
  });

  $('.delete-logo-form').submit(function (e) {
    e.preventDefault();
    const form = $(this);
    const formData = form.serialize();

    Swal.fire({
      icon: 'warning',
      title: 'Delete Logo?',
      text: 'This cannot be undone.',
      showCancelButton: true,
      confirmButtonText: 'Delete'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('manage-wings.php', formData, function (res) {
          if (res.success) {
            Swal.fire('Deleted', 'Logo removed.', 'success').then(() => location.reload());
          } else {
            Swal.fire('Error', res.message || 'Could not delete logo.', 'error');
          }
        }, 'json');
      }
    });
  });

  $('.zoomable-logo').on('click', function () {
    const url = $(this).data('full');
    Swal.fire({
      title: 'Wing Logo',
      html: `
        <div style="background: white; padding: 10px; border-radius: 8px;">
  <img src="${url}" style="max-width: 100%; max-height: 400px; background: white;">
</div>
        <br>
        <a href="${url}" download class="btn btn-primary mt-2">⬇️ Download Logo</a>
      `,
      showConfirmButton: false,
      showCloseButton: true,
      width: 500
    });
  });
});

$('#addWingForm').on('submit', function (e) {
  e.preventDefault();
  const formData = $(this).serialize() + '&create_wing=1';

  $.post('manage-wings.php', formData, function (res) {
    if (res.success) {
      Swal.fire({ icon: 'success', title: '🎉 Wing Added!', text: 'Your new wing is ready.', timer: 1500, showConfirmButton: false })
        .then(() => location.reload());
    } else {
      Swal.fire('Error', res.message || 'Could not add wing.', 'error');
    }
  }, 'json');
});

</script>
      </div> <!-- end col-md-9 -->
    </div> <!-- end row -->
  </div> <!-- end container -->

</body>
</html>




