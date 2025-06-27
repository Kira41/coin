CREATE TABLE IF NOT EXISTS personal_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    balance TEXT,
    totalDepots TEXT,
    totalRetraits TEXT,
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
    btcAddress TEXT,
    ethAddress TEXT,
    usdtAddress TEXT,
    widhrawbankname TEXT,
    widhrawusername TEXT,
    widhrawacountnumber TEXT,
    widhrawiben TEXT,
    widhrawswift TEXT
);

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    operationNumber TEXT,
    type TEXT,
    amount TEXT,
    date TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type TEXT,
    title TEXT,
    message TEXT,
    time TEXT,
    alertClass TEXT
);

CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date TEXT,
    amount TEXT,
    method TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS retraits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date TEXT,
    amount TEXT,
    method TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS trading_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    temps TEXT,
    paire_devises TEXT,
    type TEXT,
    statutTypeClass TEXT,
    montant TEXT,
    prix TEXT,
    statut TEXT,
    statutClass TEXT,
    profitPerte TEXT,
    profitClass TEXT
);

CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date TEXT,
    ip TEXT,
    device TEXT
);

CREATE TABLE IF NOT EXISTS kyc_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    step_name TEXT,
    status TEXT,
    date TEXT
);

CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    form_name TEXT,
    field_name TEXT,
    field_value TEXT
);

CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    currency TEXT,
    network TEXT,
    address TEXT,
    label TEXT,
    FOREIGN KEY (user_id) REFERENCES personal_data(id)
);

CREATE INDEX idx_wallets_user_id ON wallets(user_id);
