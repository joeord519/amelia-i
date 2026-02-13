<?php
require_once(__DIR__ . '/../db_connect.php');

// Get all steps for a given flow
function getStepsByFlowId($flow_id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_communication_steps WHERE flow_id = :flow_id ORDER BY step_order ASC");
  $stmt->execute(['flow_id' => $flow_id]);
  return $stmt->fetchAll();
}

// Get a single step by ID
function getStepById($id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_communication_steps WHERE id = :id");
  $stmt->execute(['id' => $id]);
  return $stmt->fetch();
}

// Add new step
function addStep($data) {
  $db = getDB();
  $stmt = $db->prepare("
    INSERT INTO wp_communication_steps 
    (flow_id, step_order, method, subject, reply_to, message, delay_value, delay_type, delay_from_step_id, send_time_window, frequency, send_days, active) 
    VALUES 
    (:flow_id, :step_order, :method, :subject, :reply_to, :message, :delay_value, :delay_type, :delay_from_step_id, :send_time_window, :frequency, :send_days, :active)
  ");
  return $stmt->execute([
    'flow_id'             => $data['flow_id'],
    'step_order'          => $data['step_order'],
    'method'              => $data['method'],
    'subject'             => $data['subject'] ?? '',
    'reply_to'            => $data['reply_to'] ?? '',
    'message'             => $data['message'],
    'delay_value'         => $data['delay_value'],
    'delay_type'          => $data['delay_type'],
    'delay_from_step_id'  => $data['delay_from_step_id'] ?? null,
    'send_time_window'    => $data['send_time_window'] ?? '',
    'frequency'           => $data['frequency'],
    'send_days'           => $data['send_days'],
    'active'              => $data['active']
  ]);
}

// Update existing step
function updateStep($id, $data) {
  $db = getDB();
  $stmt = $db->prepare("
    UPDATE wp_communication_steps SET
      step_order = :step_order,
      method     = :method,
      subject    = :subject,
      reply_to   = :reply_to,
      message    = :message,
      delay_value = :delay_value,
      delay_type  = :delay_type,
      delay_from_step_id = :delay_from_step_id,
      send_time_window   = :send_time_window,
      frequency  = :frequency,
      send_days  = :send_days,
      active     = :active
    WHERE id = :id
  ");
  return $stmt->execute([
    'id'                  => $id,
    'step_order'          => $data['step_order'],
    'method'              => $data['method'],
    'subject'             => $data['subject'] ?? '',
    'reply_to'            => $data['reply_to'] ?? '',
    'message'             => $data['message'],
    'delay_value'         => $data['delay_value'],
    'delay_type'          => $data['delay_type'],
    'delay_from_step_id'  => $data['delay_from_step_id'] ?? null,
    'send_time_window'    => $data['send_time_window'] ?? '',
    'frequency'           => $data['frequency'],
    'send_days'           => $data['send_days'],
    'active'              => $data['active']
  ]);
}

// Delete step
function deleteStep($id) {
  $db = getDB();
  $stmt = $db->prepare("DELETE FROM wp_communication_steps WHERE id = :id");
  return $stmt->execute(['id' => $id]);
}

