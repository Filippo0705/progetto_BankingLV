
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_name VARCHAR(255) NOT NULL,
    currency BIGINT NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount BIGINT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO 
  accounts (owner_name, currency)
VALUES 
  ('Mario Rossi', '300000'),
  ('Luigi Bianchi', '120000');

INSERT INTO 
  transactions (account_id, type, amount, description)
VALUES 
  ( 1, 'deposit', 100000 'stipendio'),
  ( 2, 'deposit', 200000, 'stipendio'),
  ( 1, 'deposit', 120000, 'scommesse');

