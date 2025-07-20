CREATE TABLE admins_agents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    is_admin TINYINT(1) NOT NULL,
    created_by INTEGER NULL,
    UNIQUE(email)
) ENGINE=InnoDB;

CREATE TABLE personal_data (
    user_id BIGINT PRIMARY KEY,
    balance DECIMAL(18,2),
    totalDepots DECIMAL(18,2),
    totalRetraits DECIMAL(18,2),
    nbTransactions TEXT,
    fullName TEXT,
    compteverifie TEXT,
    compteverifie01 TEXT,
    niveauavance TEXT,
    passwordHash TEXT,
    passwordStrength TEXT,
    passwordStrengthBar TEXT,
    emailNotifications TEXT,
    smsNotifications TEXT,
    loginAlerts TEXT,
    transactionAlerts TEXT,
    twoFactorAuth TEXT,
    emailaddress TEXT,
    address TEXT,
    phone TEXT,
    dob TEXT,
    nationality TEXT,
    created_at TEXT,
    userBankName TEXT,
    userAccountName TEXT,
    userAccountNumber TEXT,
    userIban TEXT,
    userSwiftCode TEXT,
    note TEXT,
    linked_to_id INTEGER
) ENGINE=InnoDB;

CREATE TABLE wallets (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    currency TEXT,
    network TEXT,
    address TEXT,
    label TEXT
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id BIGINT,
    operationNumber TEXT,
    type TEXT,
    amount DECIMAL(18,2),
    date TEXT,
    status TEXT,
    statusClass TEXT,
    UNIQUE(operationNumber),
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins_agents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    type TEXT,
    title TEXT,
    message TEXT,
    time TEXT,
    alertClass TEXT
) ENGINE=InnoDB;

CREATE TABLE deposits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id BIGINT, -- <== تم التعديل هنا
    operationNumber TEXT,
    date TEXT,
    amount DECIMAL(18,2),
    method TEXT,
    status TEXT,
    statusClass TEXT,
    UNIQUE(operationNumber),
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins_agents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE retraits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id BIGINT,
    operationNumber TEXT,
    date TEXT,
    amount DECIMAL(18,2),
    method TEXT,
    status TEXT,
    statusClass TEXT,
    UNIQUE(operationNumber),
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins_agents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tradingHistory (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id BIGINT,
    operationNumber TEXT,
    temps TEXT,
    paireDevises TEXT,
    type TEXT,
    statutTypeClass TEXT,
    montant DECIMAL(18,2),
    prix DECIMAL(18,2),
    statut TEXT,
    statutClass TEXT,
    profitPerte DECIMAL(18,2),
    profitClass TEXT,
    details TEXT,
    UNIQUE(operationNumber),
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins_agents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE loginHistory (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    date TEXT,
    ip TEXT,
    device TEXT
) ENGINE=InnoDB;

CREATE TABLE bank_withdrawl_info (
    user_id BIGINT PRIMARY KEY,
    widhrawBankName TEXT,
    widhrawAccountName TEXT,
    widhrawAccountNumber TEXT,
    widhrawIban TEXT,
    widhrawSwiftCode TEXT
) ENGINE=InnoDB;

CREATE TABLE deposit_crypto_address (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    crypto_name TEXT,
    wallet_info TEXT,
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE TABLE kyc (
    file_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    file_name TEXT,
    file_data MEDIUMTEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE verification_status (
    user_id BIGINT PRIMARY KEY,
    enregistrementducompte TINYINT(1) DEFAULT 0,
    confirmationdeladresseemail TINYINT(1) DEFAULT 0,
    telechargerlesdocumentsdidentite TINYINT(1) DEFAULT 0,
    verificationdeladresse TINYINT(1) DEFAULT 0,
    revisionfinale TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
