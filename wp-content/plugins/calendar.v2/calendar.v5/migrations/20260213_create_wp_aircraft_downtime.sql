CREATE TABLE IF NOT EXISTS wp_aircraft_downtime (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tail_number VARCHAR(32) NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  reason VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aircraft_downtime_tail (tail_number),
  KEY idx_aircraft_downtime_window (start_at, end_at),
  CONSTRAINT fk_aircraft_downtime_tail
    FOREIGN KEY (tail_number)
    REFERENCES wp_aircraft(tail_number)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
