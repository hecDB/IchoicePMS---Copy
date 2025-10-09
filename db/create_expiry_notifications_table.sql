-- Create table for tracking expiry notifications
CREATE TABLE IF NOT EXISTS expiry_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_date DATE NOT NULL,
    acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, notification_date),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Index for better performance
CREATE INDEX idx_user_notification_date ON expiry_notifications (user_id, notification_date);