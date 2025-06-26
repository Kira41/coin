INSERT INTO personal_data (
    balance, totalDepots, totalRetraits, nbTransactions, fullName, compteverifie, compteverifie01, niveauavance, passwordHash, passwordStrength, passwordStrengthBar, emailNotifications, smsNotifications, loginAlerts, transactionAlerts, twoFactorAuth, emailaddress, address, phone, dob, nationality, btcAddress, ethAddress, usdtAddress, widhrawbankname, widhrawusername, widhrawacountnumber, widhrawiben, widhrawswift
) VALUES (
    '5000 $', '1200 $', '800 $', '10', 'Ahmed Kouraychi', 'Vérifié', '1', 'Niveau 2', '6ce0330487c92a564b80836c30f81d5b33da46b4e0acaafa94c2211e38f1e01a', 'Fort', '90%', '1', '1', '1', '1', '0', 'Mider22@gmail.com', 'Sousse, Tunisie', '+21690000000', '2025-06-11', 'ca', '', '0xABC123...', 'TRc123456...', 'Banque Nationale', 'Société de services financiers', '1234567890', 'SA1234567890123456789012', 'BNPARABIC'
);

INSERT INTO transactions (operationNumber, type, amount, date, status, statusClass) VALUES
('#12345','Dépôt','$100','06/01/2025','complet','bg-success'),
('#12344','Retrait','$200','05/28/2025','complet','bg-success'),
('#12343','Dépôt','$300','05/25/2025','complet','bg-success'),
('#12342','Retrait','$150','05/20/2025','En cours','bg-warning');

INSERT INTO notifications (type, title, message, time, alertClass) VALUES
('info','Mise à jour du système','Le système sera mis à jour vendredi prochain.','Il y a 2 heures','alert-info'),
('success','Dépôt réussi','Un montant de 500 $ a été déposé avec succès.','Il y a un jour','alert-success'),
('warning','Vérification KYC','Merci de vérifier votre identité.','Il y a 3 jours','alert-warning');

INSERT INTO deposits (date, amount, method, status, statusClass) VALUES
('2025/06/01','$500','Carte','En cours','bg-warning'),
('2025/05/15','$300','Banque','complet','bg-success'),
('2025/05/02','$400','Bitcoin','complet','bg-success');

INSERT INTO retraits (date, amount, method, status, statusClass) VALUES
('2025/05/28','$200','Banque','complet','bg-success'),
('2025/05/20','$150','Bitcoin','En cours','bg-warning'),
('2025/05/10','$300','Paypal','complet','bg-success');

INSERT INTO trading_history (temps, paire_devises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass) VALUES
('2025/06/09 14:30','BTC/USD','Acheter','bg-success','$1,000','$500','complet','bg-success','+$175.50','text-success'),
('2025/06/09 13:15','ETH/USD','Vendre','bg-success','$500','$2,850','complet','bg-success','-$25.00','text-danger'),
('2025/06/09 12:00','ADA/USD','Acheter','bg-danger','$300','$0.45','En cours','bg-warning','-','');

INSERT INTO login_history (date, ip, device) VALUES
('2025/06/09 15:00','192.168.0.1','Chrome - Windows'),
('2025/06/08 18:20','192.168.0.2','Firefox - Android'),
('2025/06/07 09:10','192.168.0.3','Safari - iOS'),
('2025/06/06 23:45','192.168.0.4','Edge - Windows'),
('2025/06/05 08:30','192.168.0.5','Chrome - macOS');

INSERT INTO kyc_status (step_name, status, date) VALUES
('enregistrementducomptestat','0',''),
('confirmationdeladresseemailstat','0',''),
('telechargerlesdocumentsdidentitestat','0',''),
('verificationdeladressestat','0',''),
('revisionfinalestat','0','');

INSERT INTO form_fields (form_name, field_name, field_value) VALUES
('profileEditForm','fullNameInput','Ahmed Kouraychi'),
('profileEditForm','email','Mider22@gmail.com'),
('profileEditForm','phoneInput','+21690000000'),
('profileEditForm','birthdate','2025-06-11'),
('profileEditForm','nationalityInput','ca'),
('profileEditForm','addressInput','Sousse, Tunisie'),
('bankAccountForm','defaultBankName','tunisa BAN'),
('bankAccountForm','defaultAccountName','Ahmed kouraychi'),
('bankAccountForm','defaultAccountNumber','1234567890'),
('bankAccountForm','defaultIban','SA1234567890123456789012'),
('bankAccountForm','defaultSwiftCode','NCBKSAJE'),
('bankWithdrawForm','withdrawAmount','1000'),
('bankWithdrawForm','bankName','bank'),
('bankWithdrawForm','accountHolder','ahmed'),
('bankWithdrawForm','accountNumber','4546545'),
('bankWithdrawForm','iban','15454'),
('bankWithdrawForm','swiftCode','111111111'),
('bankWithdrawForm','withdrawNotes','ssss'),
('bankWithdrawForm','saveBankInfo','1');
