-- Task Management Module - Database Setup
-- Run this SQL in phpMyAdmin or MySQL CLI

-- Task Categories Table
CREATE TABLE IF NOT EXISTS task_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_code VARCHAR(20) NOT NULL UNIQUE,
    color_code VARCHAR(7) DEFAULT '#3498db',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories
INSERT INTO task_categories (category_name, category_code, color_code, description) VALUES
('General', 'GEN', '#95a5a6', 'General tasks not specific to any department'),
('Sales', 'SALES', '#e74c3c', 'Sales and CRM related tasks'),
('Marketing', 'MKT', '#f39c12', 'Marketing campaigns and activities'),
('HR', 'HR', '#9b59b6', 'Human resources related tasks'),
('Operations', 'OPS', '#1abc9c', 'Operations and manufacturing tasks'),
('Purchase', 'PUR', '#3498db', 'Purchase and procurement tasks'),
('Inventory', 'INV', '#2ecc71', 'Inventory management tasks'),
('Service', 'SVC', '#e67e22', 'Customer service and support tasks'),
('Finance', 'FIN', '#34495e', 'Finance and accounting tasks'),
('IT', 'IT', '#8e44ad', 'IT and technical tasks'),
('Admin', 'ADMIN', '#7f8c8d', 'Administrative tasks')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- Main Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_no VARCHAR(20) NOT NULL UNIQUE,
    task_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    category_id INT,
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    status ENUM('Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Not Started',

    -- Assignment
    assigned_to INT COMMENT 'Employee ID',
    assigned_by INT COMMENT 'Employee ID who created/assigned',

    -- Dates
    start_date DATE,
    due_date DATE,
    completed_date DATE,

    -- Progress
    progress_percent INT DEFAULT 0,

    -- Related entities (optional links)
    related_module VARCHAR(50) COMMENT 'sales, marketing, hr, inventory, etc.',
    related_id INT COMMENT 'ID of related record',
    related_reference VARCHAR(100) COMMENT 'Reference number like PROJ-0001, INV-0001',

    -- Customer/Project link
    customer_id INT,
    project_id INT,

    -- Additional info
    estimated_hours DECIMAL(8,2),
    actual_hours DECIMAL(8,2),
    remarks TEXT,

    -- Audit
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (category_id) REFERENCES task_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_task_status (status),
    INDEX idx_task_priority (priority),
    INDEX idx_task_assigned (assigned_to),
    INDEX idx_task_due_date (due_date),
    INDEX idx_task_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Comments/Activity Log Table
CREATE TABLE IF NOT EXISTS task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    comment TEXT NOT NULL,
    commented_by INT COMMENT 'Employee ID',
    comment_type ENUM('comment', 'status_change', 'progress_update', 'assignment') DEFAULT 'comment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (commented_by) REFERENCES employees(id) ON DELETE SET NULL,
    INDEX idx_task_comments (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Attachments Table (optional)
CREATE TABLE IF NOT EXISTS task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task_attachments (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Checklist Items (subtasks)
CREATE TABLE IF NOT EXISTS task_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    item_text VARCHAR(255) NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME,
    completed_by INT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_task_checklist (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
