CREATE TABLE roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE clinics (
    clinic_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_name VARCHAR(150) NOT NULL,
    address VARCHAR(255),
    region VARCHAR(100),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    clinic_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id)
        REFERENCES roles(role_id),

    CONSTRAINT fk_users_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics(clinic_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE TABLE items (
    item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    criticality_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    cold_chain_required TINYINT(1) NOT NULL DEFAULT 0,
    minimum_temperature DECIMAL(5,2) NULL,
    maximum_temperature DECIMAL(5,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE inventory (
    inventory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity_available DECIMAL(12,2) NOT NULL DEFAULT 0,
    average_daily_usage DECIMAL(12,2) NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inventory_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics(clinic_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inventory_item
        FOREIGN KEY (item_id)
        REFERENCES items(item_id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_clinic_item (clinic_id, item_id)
) ENGINE=InnoDB;


CREATE TABLE supply_requests (
    request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    urgency_score DECIMAL(5,2) NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    required_by DATETIME NULL,
    notes TEXT NULL,

    CONSTRAINT fk_requests_clinic
        FOREIGN KEY (clinic_id)
        REFERENCES clinics(clinic_id),

    CONSTRAINT fk_requests_user
        FOREIGN KEY (requested_by)
        REFERENCES users(user_id),

    INDEX idx_requests_status (status),
    INDEX idx_requests_urgency (urgency_score)
) ENGINE=InnoDB;


CREATE TABLE request_items (
    request_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity_requested DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_request_items_request
        FOREIGN KEY (request_id)
        REFERENCES supply_requests(request_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_request_items_item
        FOREIGN KEY (item_id)
        REFERENCES items(item_id),

    UNIQUE KEY uq_request_item (request_id, item_id)
) ENGINE=InnoDB;


CREATE TABLE vehicles (
    vehicle_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(50) NOT NULL UNIQUE,
    vehicle_type VARCHAR(100),
    capacity DECIMAL(12,2),
    refrigerated TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE drivers (
    driver_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    license_number VARCHAR(100),
    status VARCHAR(30) NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_drivers_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE shipments (
    shipment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    dispatched_at DATETIME NULL,
    estimated_delivery DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_shipments_request
        FOREIGN KEY (request_id)
        REFERENCES supply_requests(request_id),

    UNIQUE KEY uq_shipments_request (request_id),

    CONSTRAINT fk_shipments_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(vehicle_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_shipments_driver
        FOREIGN KEY (driver_id)
        REFERENCES drivers(driver_id)
        ON DELETE SET NULL,

    INDEX idx_shipments_status (status)
) ENGINE=InnoDB;


CREATE TABLE triage_scores (
    triage_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    criticality_score DECIMAL(5,2) NOT NULL,
    stockout_score DECIMAL(5,2) NOT NULL,
    cold_chain_score DECIMAL(5,2) NOT NULL,
    transit_risk_score DECIMAL(5,2) NOT NULL,
    urgency_score DECIMAL(5,2) NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_triage_request
        FOREIGN KEY (request_id)
        REFERENCES supply_requests(request_id)
        ON DELETE CASCADE,

    INDEX idx_triage_urgency (urgency_score)
) ENGINE=InnoDB;


CREATE TABLE telemetry_events (
    event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL DEFAULT 'Telemetry',
    temperature DECIMAL(5,2) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    recorded_at DATETIME NOT NULL,

    CONSTRAINT fk_telemetry_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(vehicle_id),

    CONSTRAINT fk_telemetry_shipment
        FOREIGN KEY (shipment_id)
        REFERENCES shipments(shipment_id)
        ON DELETE SET NULL,

    INDEX idx_telemetry_vehicle (vehicle_id),
    INDEX idx_telemetry_recorded (recorded_at)
) ENGINE=InnoDB;


CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read)
) ENGINE=InnoDB;


CREATE TABLE audit_logs (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE telemetry_alerts (
    alert_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NULL,
    alert_type VARCHAR(50) NOT NULL,
    alert_message TEXT NOT NULL,
    alert_value DECIMAL(10,2) NULL,
    threshold_value DECIMAL(10,2) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,

    CONSTRAINT fk_alert_event
        FOREIGN KEY (event_id)
        REFERENCES telemetry_events(event_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_alert_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(vehicle_id),

    CONSTRAINT fk_alert_shipment
        FOREIGN KEY (shipment_id)
        REFERENCES shipments(shipment_id)
        ON DELETE SET NULL,

    INDEX idx_alert_event (event_id),
    INDEX idx_alert_vehicle (vehicle_id),
    INDEX idx_alert_shipment (shipment_id),
    INDEX idx_alert_status (status),
    INDEX idx_alert_created (created_at)
) ENGINE=InnoDB;