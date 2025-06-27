INSERT INTO personal_data (
    balance, totalDepots, totalRetraits, nbTransactions, fullName, compteverifie, compteverifie01, niveauavance, passwordHash, passwordStrength, passwordStrengthBar, emailNotifications, smsNotifications, loginAlerts, transactionAlerts, twoFactorAuth, emailaddress, address, phone, dob, nationality, btcAddress, ethAddress, usdtAddress, widhrawbankname, widhrawusername, widhrawacountnumber, widhrawiben, widhrawswift
) VALUES (
    '5000 $', '1200 $', '800 $', '10', 'Ahmed Kouraychi', 'Vérifié', '1', 'Niveau 2', '$2b$10$xOMK7an7pZTpM8GKYISa1On.tsvzPqt9OiE8vqgZh7FgsL9HGNKMG', 'Fort', '90%', '1', '1', '1', '1', '0', 'Mider22@gmail.com', 'Sousse, Tunisie', '+21690000000', '2025-06-11', 'ca', '', '0xABC123...', 'TRc123456...', 'Banque Nationale', 'Société de services financiers', '1234567890', 'SA1234567890123456789012', 'BNPARABIC'
);

INSERT INTO transactions (user_id, operationNumber, type, amount, date, status, statusClass) VALUES
(1,'#12345','Dépôt','$100','06/01/2025','complet','bg-success'),
(1,'#12344','Retrait','$200','05/28/2025','complet','bg-success'),
(1,'#12343','Dépôt','$300','05/25/2025','complet','bg-success'),
(1,'#12342','Retrait','$150','05/20/2025','En cours','bg-warning');

INSERT INTO notifications (user_id, type, title, message, time, alertClass) VALUES
(1,'info','Mise à jour du système','Le système sera mis à jour vendredi prochain.','Il y a 2 heures','alert-info'),
(1,'success','Dépôt réussi','Un montant de 500 $ a été déposé avec succès.','Il y a un jour','alert-success'),
(1,'warning','Vérification KYC','Merci de vérifier votre identité.','Il y a 3 jours','alert-warning');

INSERT INTO deposits (user_id, date, amount, method, status, statusClass) VALUES
(1,'2025/06/01','$500','Carte','En cours','bg-warning'),
(1,'2025/05/15','$300','Banque','complet','bg-success'),
(1,'2025/05/02','$400','Bitcoin','complet','bg-success');

INSERT INTO retraits (user_id, date, amount, method, status, statusClass) VALUES
(1,'2025/05/28','$200','Banque','complet','bg-success'),
(1,'2025/05/20','$150','Bitcoin','En cours','bg-warning'),
(1,'2025/05/10','$300','Paypal','complet','bg-success');

INSERT INTO trading_history (user_id, temps, paire_devises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass) VALUES
(1,'2025/06/09 14:30','BTC/USD','Acheter','bg-success','$1,000','$500','complet','bg-success','+$175.50','text-success'),
(1,'2025/06/09 13:15','ETH/USD','Vendre','bg-success','$500','$2,850','complet','bg-success','-$25.00','text-danger'),
(1,'2025/06/09 12:00','ADA/USD','Acheter','bg-danger','$300','$0.45','En cours','bg-warning','-','');

INSERT INTO login_history (user_id, date, ip, device) VALUES
(1,'2025/06/09 15:00','192.168.0.1','Chrome - Windows'),
(1,'2025/06/08 18:20','192.168.0.2','Firefox - Android'),
(1,'2025/06/07 09:10','192.168.0.3','Safari - iOS'),
(1,'2025/06/06 23:45','192.168.0.4','Edge - Windows'),
(1,'2025/06/05 08:30','192.168.0.5','Chrome - macOS');

INSERT INTO kyc_status (user_id, step_name, status, date) VALUES
(1,'enregistrementducomptestat','0',''),
(1,'confirmationdeladresseemailstat','0',''),
(1,'telechargerlesdocumentsdidentitestat','0',''),
(1,'verificationdeladressestat','0',''),
(1,'revisionfinalestat','0','');

INSERT INTO form_fields (user_id, form_name, field_name, field_value) VALUES
(1,'profileEditForm','fullNameInput','Ahmed Kouraychi'),
(1,'profileEditForm','email','Mider22@gmail.com'),
(1,'profileEditForm','phoneInput','+21690000000'),
(1,'profileEditForm','birthdate','2025-06-11'),
(1,'profileEditForm','nationalityInput','ca'),
(1,'profileEditForm','addressInput','Sousse, Tunisie'),
(1,'bankAccountForm','defaultBankName','tunisa BAN'),
(1,'bankAccountForm','defaultAccountName','Ahmed kouraychi'),
(1,'bankAccountForm','defaultAccountNumber','1234567890'),
(1,'bankAccountForm','defaultIban','SA1234567890123456789012'),
(1,'bankAccountForm','defaultSwiftCode','NCBKSAJE'),
(1,'bankWithdrawForm','withdrawAmount','1000'),
(1,'bankWithdrawForm','bankName','bank'),
(1,'bankWithdrawForm','accountHolder','ahmed'),
(1,'bankWithdrawForm','accountNumber','4546545'),
(1,'bankWithdrawForm','iban','15454'),
(1,'bankWithdrawForm','swiftCode','111111111'),
(1,'bankWithdrawForm','withdrawNotes','ssss'),
(1,'bankWithdrawForm','saveBankInfo','1');

INSERT INTO wallets (id, user_id, currency, network, address, label) VALUES
('1',1,'btc','Bitcoin','bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kygt080','Mon BTC'),
('2',1,'eth','ERC20','0xabc123def456abc123def456abc123def456abcd','Mon ETH');
