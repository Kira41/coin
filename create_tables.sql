CREATE TABLE IF NOT EXISTS personal_data (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    operationNumber TEXT,
    type TEXT,
    amount TEXT,
    date TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT,
    title TEXT,
    message TEXT,
    time TEXT,
    alertClass TEXT
);

CREATE TABLE IF NOT EXISTS deposits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT,
    amount TEXT,
    method TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS retraits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT,
    amount TEXT,
    method TEXT,
    status TEXT,
    statusClass TEXT
);

CREATE TABLE IF NOT EXISTS trading_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT,
    ip TEXT,
    device TEXT
);

CREATE TABLE IF NOT EXISTS kyc_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    step_name TEXT,
    status TEXT,
    date TEXT
);

CREATE TABLE IF NOT EXISTS form_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    form_name TEXT,
    field_name TEXT,
    field_value TEXT
);

CREATE TABLE IF NOT EXISTS wallets (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    currency TEXT,
    network TEXT,
    address TEXT,
    label TEXT
);
