
CREATE TABLE personal_data (
    user_id INTEGER PRIMARY KEY,
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
    userBankName TEXT,
    userAccountName TEXT,
    userAccountNumber TEXT,
    userIban TEXT,
    userSwiftCode TEXT
);


CREATE TABLE wallets (
    id INTEGER,
    user_id INTEGER,
    currency TEXT,
    network TEXT,
    address TEXT,
    label TEXT
);

CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, operationNumber TEXT, type TEXT, amount TEXT, date TEXT, status TEXT, statusClass TEXT);
CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, type TEXT, title TEXT, message TEXT, time TEXT, alertClass TEXT);
CREATE TABLE deposits (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, date TEXT, amount TEXT, method TEXT, status TEXT, statusClass TEXT);
CREATE TABLE retraits (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, date TEXT, amount TEXT, method TEXT, status TEXT, statusClass TEXT);
CREATE TABLE tradingHistory (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, temps TEXT, paireDevises TEXT, type TEXT, statutTypeClass TEXT, montant TEXT, prix TEXT, statut TEXT, statutClass TEXT, profitPerte TEXT, profitClass TEXT);
CREATE TABLE loginHistory (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, date TEXT, ip TEXT, device TEXT);
CREATE TABLE bank_withdrawl_info (
    widhrawBankName TEXT,
    widhrawAccountName TEXT,
    widhrawAccountNumber TEXT,
    widhrawIban TEXT,
    widhrawSwiftCode TEXT
);
