CREATE DATABASE IF NOT EXISTS campusfind;
USE campusfind;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  student_id VARCHAR(100) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_email (email)
);

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_category_name (name)
);

CREATE TABLE IF NOT EXISTS locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_location_name (name)
);

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id VARCHAR(50) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  type ENUM('lost', 'found') NOT NULL,
  name VARCHAR(255) NOT NULL,
  category_id INT NOT NULL,
  description TEXT NOT NULL,
  brand VARCHAR(120) DEFAULT '',
  color VARCHAR(80) DEFAULT '',
  location_id INT NOT NULL,
  item_date DATE NOT NULL,
  item_time TIME DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Reported',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  INDEX idx_items_type (type),
  INDEX idx_items_status (status),
  INDEX idx_items_date (item_date)
);

CREATE TABLE IF NOT EXISTS claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  user_id INT NOT NULL,
  verification_details TEXT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',
  admin_note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_claims_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_claims_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_claims_status (status),
  UNIQUE KEY unique_claim_per_user_item (item_id, user_id)
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id)
);

CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  reported_by INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  description TEXT,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_user FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reports_status (status)
);

INSERT IGNORE INTO users (name, email, student_id, phone, password, role, status) VALUES
('CampusFind Admin', 'admin@campusfind.edu', 'ADM-1001', '9999999999', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'admin', 'active'),
('Aarav Sharma', 'aarav@campusfind.edu', 'CS-101', '9876543210', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Nisha Verma', 'nisha@campusfind.edu', 'EC-205', '9876543211', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Rohan Mehta', 'rohan@campusfind.edu', 'ME-112', '9876543212', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Priya Nair', 'priya@campusfind.edu', 'EE-201', '9876543213', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Vikram Singh', 'vikram@campusfind.edu', 'BT-110', '9876543214', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Ananya Iyer', 'ananya@campusfind.edu', 'CH-118', '9876543215', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Karan Patel', 'karan@campusfind.edu', 'IT-230', '9876543216', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Megha Joshi', 'megha@campusfind.edu', 'MBA-120', '9876543217', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active'),
('Harsh Gupta', 'harsh@campusfind.edu', 'PH-402', '9876543218', '$2y$12$ptOg4ahilUUDKnAAu1KpAO.tlyG.axRUFo/.SwEWHp1k/Bbg1xrTa', 'user', 'active');

INSERT IGNORE INTO categories (name, description) VALUES
('Electronics', 'Phones, chargers, laptops, and accessories'),
('ID Cards', 'College IDs and identity cards'),
('Wallets', 'Wallets and cash cases'),
('Keys', 'Keys and key chains'),
('Books', 'Textbooks and notebooks'),
('Bags', 'Backpacks and shoulder bags'),
('Accessories', 'Jewellery, watches, hats, and more'),
('Other', 'Other unclassified lost or found items');

INSERT IGNORE INTO locations (name, description) VALUES
('Central Library', 'Main library building'),
('Main Academic Block', 'Academic classroom block'),
('Hostel Block A', 'Student hostel building'),
('Engineering Lab', 'Engineering department block'),
('Cafeteria', 'Campus food court'),
('Auditorium', 'Main auditorium'),
('Sports Complex', 'Sports and recreation area'),
('Admin Block', 'Administrative office area');

INSERT IGNORE INTO items (report_id, user_id, type, name, category_id, description, brand, color, location_id, item_date, item_time, image, status) VALUES
('LF-20261001-001', 2, 'lost', 'Black Wallet', 3, 'Black leather wallet with college ID.', 'Hidesign', 'Black', 1, '2026-10-01', '09:15:00', 'assets/images/default-item.svg', 'Reported'),
('FD-20261001-001', 3, 'found', 'Silver Keychain', 4, 'Silver keychain with a blue tag.', 'N/A', 'Silver', 5, '2026-10-02', '11:40:00', 'assets/images/default-item.svg', 'Under Review'),
('LF-20261002-002', 4, 'lost', 'Red Backpack', 6, 'Red backpack with college sticker and water bottle.', 'Skybags', 'Red', 2, '2026-10-02', '08:05:00', 'assets/images/default-item.svg', 'Matched'),
('FD-20261002-002', 5, 'found', 'Student ID Card', 2, 'Found in cafeteria near the study table.', 'College', 'Blue', 5, '2026-10-02', '13:30:00', 'assets/images/default-item.svg', 'Verified'),
('LF-20261003-003', 6, 'lost', 'Blue Notebook', 5, 'Blue hardbound notebook with engineering notes.', 'Classmate', 'Blue', 3, '2026-10-03', '16:45:00', 'assets/images/default-item.svg', 'Reported'),
('FD-20261003-003', 7, 'found', 'Wireless Earbuds', 1, 'White earbuds in charging case.', 'Boat', 'White', 6, '2026-10-03', '18:20:00', 'assets/images/default-item.svg', 'Reported'),
('LF-20261004-004', 8, 'lost', 'Black Phone Case', 1, 'Transparent black phone case with a cracked corner.', 'Spigen', 'Black', 4, '2026-10-04', '12:00:00', 'assets/images/default-item.svg', 'Claim Pending'),
('FD-20261004-004', 9, 'found', 'Laptop Charger', 1, 'Laptop charger with Lenovo label.', 'Lenovo', 'Black', 4, '2026-10-04', '15:10:00', 'assets/images/default-item.svg', 'Reported'),
('LF-20261005-005', 10, 'lost', 'College Badge', 2, 'Name engraved on backside.', 'Campus', 'Gold', 8, '2026-10-05', '10:45:00', 'assets/images/default-item.svg', 'Reported'),
('FD-20261005-005', 2, 'found', 'Black Water Bottle', 7, 'Reusable water bottle with sticker.', 'Milton', 'Black', 7, '2026-10-05', '17:20:00', 'assets/images/default-item.svg', 'Returned'),
('LF-20261006-006', 3, 'lost', 'USB Drive', 1, '8GB USB with engineering project files.', 'SanDisk', 'Black', 4, '2026-10-06', '14:15:00', 'assets/images/default-item.svg', 'Reported'),
('FD-20261006-006', 4, 'found', 'Gray Notebook', 5, 'Notebook with mathematics formulas.', 'N/A', 'Gray', 2, '2026-10-06', '10:00:00', 'assets/images/default-item.svg', 'Reported'),
('LF-20261007-007', 5, 'lost', 'Brown Leather Bag', 6, 'Small leather bag with zipper.', 'Wildcraft', 'Brown', 1, '2026-10-07', '09:30:00', 'assets/images/default-item.svg', 'Matched'),
('FD-20261007-007', 6, 'found', 'Gold Watch', 7, 'Classic gold coloured wristwatch.', 'Titan', 'Gold', 8, '2026-10-07', '20:00:00', 'assets/images/default-item.svg', 'Reported'),
('LF-20261008-008', 7, 'lost', 'Math Textbook', 5, 'Calculus book with handwritten notes.', 'Pearson', 'Blue', 2, '2026-10-08', '08:15:00', 'assets/images/default-item.svg', 'Reported');

INSERT IGNORE INTO claims (item_id, user_id, verification_details, status) VALUES
(1, 3, 'Black wallet with a small silver clasp and engraved initials.', 'Pending'),
(2, 4, 'Keychain has a blue tag with student hostel number.', 'Under Review'),
(3, 7, 'Red backpack had a unique patch of campus logo.', 'Approved'),
(4, 8, 'Student ID had the name and a gold seal.', 'Rejected'),
(5, 9, 'Notebook had my timetable and detailed handwriting.', 'Pending'),
(6, 10, 'Earbuds case had a custom sticker on the lid.', 'Under Review'),
(7, 2, 'Phone case had a tiny scratch pattern unique to me.', 'Approved'),
(8, 5, 'Charger fit my specific laptop model and cable style.', 'Completed'),
(9, 6, 'Badge had my graduation year engraved on the back.', 'Pending'),
(10, 8, 'Bottle had a black strap and my name sticker.', 'Completed');

INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(2, 'Lost item reported', 'Your report has been received and is being reviewed.', 'success', 0),
(3, 'Possible match found', 'A possible match was detected for your found item.', 'info', 0),
(4, 'Claim approved', 'Your claim for the red backpack was approved.', 'success', 0),
(5, 'Claim rejected', 'The admin rejected your claim after verification.', 'danger', 0),
(6, 'Item returned', 'Your item has been marked returned to you.', 'success', 0),
(7, 'Admin update', 'Your report is under review by the admin team.', 'info', 1),
(8, 'Claim pending', 'Your claim is pending review by the admin.', 'warning', 0),
(9, 'Possible match found', 'We found a likely match for your lost item.', 'info', 1),
(10, 'Item reported', 'Your report has been submitted to campus security.', 'success', 0),
(2, 'Notification', 'Your item status was updated to Reported.', 'info', 0);

INSERT INTO reports (item_id, reported_by, reason, description, status) VALUES
(1, 2, 'Lost item report', 'Black wallet reported by the student in the library.', 'Pending'),
(2, 3, 'Found item report', 'Silver keychain found by the cafeteria attendant.', 'Pending'),
(3, 4, 'Lost item report', 'Red backpack reported from the academic block.', 'Verified'),
(4, 5, 'Found item report', 'ID card found near the cafetaria entrance.', 'Approved'),
(5, 6, 'Lost item report', 'Blue notebook from hostel block A.', 'Pending'),
(6, 7, 'Found item report', 'Wireless earbuds found in the auditorium.', 'Pending'),
(7, 8, 'Lost item report', 'Phone case lost near engineering lab.', 'Verified'),
(8, 9, 'Found item report', 'Laptop charger found near the sports complex.', 'Pending'),
(9, 10, 'Lost item report', 'Badge lost while walking through the administrative block.', 'Pending'),
(10, 2, 'Found item report', 'Water bottle found in the sports complex.', 'Closed');
