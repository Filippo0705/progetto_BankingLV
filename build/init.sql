
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_name VARCHAR(255) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO 
  accounts (owner_name, currency)
VALUES 
  ('Mario Rossi', 'EUR'),
  ('Luigi Bianchi', 'EUR');

INSERT INTO 
  transactions (account_id, type, amount, description)
VALUES 
  ( 1, 'deposit', 1000.00, 'stipendio'),
  ( 2, 'deposit', 2000.00, 'stipendio'),
  ( 1, 'deposit', 1200.00, 'scommesse');

