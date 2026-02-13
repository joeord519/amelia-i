<?php
return [
  // LIVE endpoints
  'base_path'           => 'https://www.docusign.net/restapi',
  'oauth_host'          => 'https://account.docusign.com',

  // Your LIVE DocuSign app/account values
  'integration_key'     => '6c7aee8f-239e-4459-bf09-dc10b5e1648b',  // aka Client ID
  'user_id'             => '41b034a2-25a0-4a46-ba8e-c4e883f66c50',        // API User ID (GUID)
  'account_id'          => '8741e901-5213-4f85-a24c-96c1efe5ebf9',       // API Account ID

  // RSA key you downloaded for live (keep this file private)
  'private_key_path'    => __DIR__ . '/docusign_private.key',

  // ✅ Your live template
  'template_id'         => 'ae4fb95f-3846-4a1c-ba4b-fc46f6ef084e',

  'impersonation_scope' => 'signature impersonation',
];

