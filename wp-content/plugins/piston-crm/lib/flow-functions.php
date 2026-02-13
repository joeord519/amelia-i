<?php
require_once(__DIR__ . '/../db_connect.php');

function getAllFlows($program = null) {
  $db = getDB();
  $sql = "SELECT * FROM wp_communication_flows";
  if ($program) {
    $sql .= " WHERE program_name = :program";
  }
  $sql .= " ORDER BY delay_value ASC";

  $stmt = $db->prepare($sql);
  if ($program) {
    $stmt->execute(['program' => $program]);
  } else {
    $stmt->execute();
  }
  return $stmt->fetchAll();
}

function getFlowById($id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_communication_flows WHERE id = :id");
  $stmt->execute(['id' => $id]);
  return $stmt->fetch();
}

function addFlow($data) {
  $db = getDB();
  $stmt = $db->prepare("
    INSERT INTO wp_communication_flows 
    (program_name, title, method, subject, reply_to, message, frequency, delay_value, delay_type, delay_from_step_id, send_days, send_time_window, active) 
    VALUES 
    (:program_name, :title, :method, :subject, :reply_to, :message, :frequency, :delay_value, :delay_type, :delay_from_step_id, :send_days, :send_time_window, :active)
  ");
  $stmt->execute([
    'program_name'        => $data['program_name'],
    'title'               => $data['title'],
    'method'              => $data['method'],
    'subject'             => $data['subject'],
    'reply_to'            => $data['reply_to'],
    'message'             => $data['message'],
    'frequency'           => $data['frequency'],
    'delay_value'         => $data['delay_value'],
    'delay_type'          => $data['delay_type'],
    'delay_from_step_id'  => $data['delay_from_step_id'] ?? null,
    'send_days'           => $data['send_days'],
    'send_time_window'    => $data['send_time_window'],
    'active'              => $data['active']
  ]);
  return $db->lastInsertId();
}

function updateFlow($id, $data) {
  $db = getDB();
  $stmt = $db->prepare("
    UPDATE wp_communication_flows SET
      program_name = :program_name,
      title        = :title,
      method       = :method,
      subject      = :subject,
      reply_to     = :reply_to,
      message      = :message,
      frequency    = :frequency,
      delay_value  = :delay_value,
      delay_type   = :delay_type,
      delay_from_step_id = :delay_from_step_id,
      send_days    = :send_days,
      send_time_window = :send_time_window,
      active       = :active
    WHERE id = :id
  ");
  return $stmt->execute([
    'id'                  => $id,
    'program_name'        => $data['program_name'],
    'title'               => $data['title'],
    'method'              => $data['method'],
    'subject'             => $data['subject'],
    'reply_to'            => $data['reply_to'],
    'message'             => $data['message'],
    'frequency'           => $data['frequency'],
    'delay_value'         => $data['delay_value'],
    'delay_type'          => $data['delay_type'],
    'delay_from_step_id'  => $data['delay_from_step_id'] ?? null,
    'send_days'           => $data['send_days'],
    'send_time_window'    => $data['send_time_window'],
    'active'              => $data['active']
  ]);
}

function deleteFlow($id) {
  $db = getDB();
  $stmt = $db->prepare("DELETE FROM wp_communication_flows WHERE id = :id");
  return $stmt->execute(['id' => $id]);
}
