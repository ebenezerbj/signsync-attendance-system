CREATE TABLE IF NOT EXISTS clockinout (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID VARCHAR(15) NOT NULL,
    ClockIn DATETIME NOT NULL,
    ClockOut DATETIME NULL,
    ClockInSource VARCHAR(50) DEFAULT 'Manual',
    ClockOutSource VARCHAR(50) DEFAULT 'Manual',
    ClockInLocation VARCHAR(255) NULL,
    ClockOutLocation VARCHAR(255) NULL,
    ClockInDevice VARCHAR(100) NULL,
    ClockOutDevice VARCHAR(100) NULL,
    WorkDuration DECIMAL(5,2) NULL,
    Notes TEXT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (EmployeeID),
    INDEX idx_date (ClockIn)
);
