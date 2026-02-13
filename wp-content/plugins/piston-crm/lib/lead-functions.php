<?php
require_once(__DIR__ . '/../db_connect.php');

// Fetch all leads, optional filter by program
function getAllLeads($program = null) {
  $db = getDB();
  $sql = "SELECT * FROM wp_leads";
  if ($program) {
    $sql .= " WHERE training_program = :program";
  }
  $sql .= " ORDER BY training_program, last_contacted_at ASC";

  $stmt = $db->prepare($sql);
  if ($program) {
    $stmt->execute(['program' => $program]);
  } else {
    $stmt->execute();
  }
  return $stmt->fetchAll();
}

function getLeadById($id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_leads WHERE id = :id");
  $stmt->execute(['id' => $id]);
  return $stmt->fetch();
}

function updateLeadLastContacted($id) {
  $db = getDB();
  $stmt = $db->prepare("UPDATE wp_leads SET last_contacted_at = NOW() WHERE id = :id");
  return $stmt->execute(['id' => $id]);
}

function addLeadNote($id, $note) {
  $db = getDB();
  $stmt = $db->prepare("UPDATE wp_leads SET notes = CONCAT(IFNULL(notes, ''), :note) WHERE id = :id");
  return $stmt->execute([
    'note' => "\n[" . date('Y-m-d H:i') . "] " . $note,
    'id' => $id
  ]);
}

function getAllProgramTypes() {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_program_types ORDER BY name ASC");
  $stmt->execute();
  return $stmt->fetchAll();
}

