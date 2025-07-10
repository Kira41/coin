CREATE TABLE admins_agents (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    is_admin TINYINT(1) NOT NULL,
    created_by INTEGER NULL,
    UNIQUE(email)
);

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
    btcAddress TEXT,
    ethAddress TEXT,
    usdtAddress TEXT,
    userBankName TEXT,
    userAccountName TEXT,
    userAccountNumber TEXT,
    userIban TEXT,
    userSwiftCode TEXT,
    note TEXT,
    linked_to_id INTEGER
);

CREATE TABLE wallets (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    currency TEXT,
    network TEXT,
    address TEXT,
    label TEXT
);

CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id INTEGER,
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
);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    type TEXT,
    title TEXT,
    message TEXT,
    time TEXT,
    alertClass TEXT
);

CREATE TABLE deposits (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id INTEGER,
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
);

CREATE TABLE retraits (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id INTEGER,
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
);

CREATE TABLE tradingHistory (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    admin_id INTEGER,
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
    UNIQUE(operationNumber),
    FOREIGN KEY (user_id) REFERENCES personal_data(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins_agents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE loginHistory (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    date TEXT,
    ip TEXT,
    device TEXT
);

CREATE TABLE bank_withdrawl_info (
    user_id BIGINT PRIMARY KEY,
    widhrawBankName TEXT,
    widhrawAccountName TEXT,
    widhrawAccountNumber TEXT,
    widhrawIban TEXT,
    widhrawSwiftCode TEXT
);
