<!-- Load TinyMCE -->
<script src="https://cdn.tiny.cloud/1/dcn2x687vile0857j1azvvn32ctmju42bg6mxodv8lj8ixio/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
  tinymce.init({
    selector: '#email_body',
    height: 300,
    menubar: false,
    plugins: [
      'advlist autolink lists link image charmap anchor',
      'searchreplace visualblocks code fullscreen',
      'insertdatetime media table paste help wordcount'
    ],
    toolbar:
      'undo redo | formatselect | bold italic underline | fontsizeselect forecolor backcolor | ' +
      'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
      'link image table | removeformat | help',
    branding: false
  });
</script>

<textarea id="email_body" name="email_body" class="form-control"><?= htmlspecialchars($emailBody ?? '') ?></textarea>
