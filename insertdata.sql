INSERT INTO admins_agents (email, password, is_admin, created_by)
VALUES ('admin@scampia.io', 'c1de8b176818ec85532879c60030aedd', 1, NULL);

INSERT INTO personal_data VALUES (
1, 3500, 1200, 800, '10',
'Ahmed Kouraychi', 'Vérifié', '1', 'Niveau 2',
'c1de8b176818ec85532879c60030aedd', 'Fort', '90%',
'0', '0', '0', '1', '0',
'41kira41@gmail.com', 'Sousse, Tunisie', '+21690000000',
'2025-06-11', 'ca', '2025-01-01',
'Bank of Earth', 'Ahmed Kouraychi',
'ACC123456', 'IBAN123456', 'SWIFT123', '', 1
);

INSERT INTO deposit_crypto_address (user_id, crypto_name, wallet_info) VALUES
    (1, 'Bitcoin', '0xABC123...'),
    (1, 'Tron', 'TRc123456...'),
    (1, 'USDT', 'USDT123...');


INSERT INTO wallets (id, user_id, currency, amount, purchase_price, usd_value, network, address, label) VALUES (
    1751038645430, 1, 'btc', 0, 0, 0,
    'Bitcoin',
    'BTC12345678', ''
);

INSERT INTO transactions (user_id, operationNumber, type, amount, date, status, statusClass) VALUES (1, '#12345', 'Dépôt', 100, '06/01/2025', 'complet', 'bg-success');
INSERT INTO transactions (user_id, operationNumber, type, amount, date, status, statusClass) VALUES (1, '#12344', 'Retrait', 200, '05/28/2025', 'complet', 'bg-success');
INSERT INTO transactions (user_id, operationNumber, type, amount, date, status, statusClass) VALUES (1, '#12343', 'Dépôt', 300, '05/25/2025', 'complet', 'bg-success');
INSERT INTO transactions (user_id, operationNumber, type, amount, date, status, statusClass) VALUES (1, '#12342', 'Retrait', 150, '05/20/2025', 'En cours', 'bg-warning');
INSERT INTO notifications (user_id, type, title, message, time, alertClass) VALUES (1, 'info', 'Mise à jour du système', 'Le système sera mis à jour vendredi prochain.', 'Il y a 2 heures', 'alert-info');
INSERT INTO notifications (user_id, type, title, message, time, alertClass) VALUES (1, 'success', 'Dépôt réussi', 'Un montant de 500 $ a été déposé avec succès.', 'Il y a un jour', 'alert-success');
INSERT INTO notifications (user_id, type, title, message, time, alertClass) VALUES (1, 'warning', 'Vérification KYC', 'Merci de vérifier votre identité.', 'Il y a 3 jours', 'alert-warning');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D1', '2025/06/27', 150, 'Bitcoin', 'En cours', 'bg-warning');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D2', '2025/06/27', 150, 'Carte', 'En cours', 'bg-warning');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D3', '2025/06/27', 100, 'Banque', 'En cours', 'bg-warning');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D4', '2025/06/01', 500, 'Carte', 'En cours', 'bg-warning');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D5', '2025/05/15', 300, 'Banque', 'complet', 'bg-success');
INSERT INTO deposits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'D6', '2025/05/02', 400, 'Bitcoin', 'complet', 'bg-success');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R1', '2025/06/27', 700, 'Paypal', 'En cours', 'bg-warning');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R2', '2025/06/27', 200, 'Ethereum', 'En cours', 'bg-warning');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R3', '2025/06/27', 1000, 'Banque', 'En cours', 'bg-warning');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R4', '2025/05/28', 200, 'Banque', 'complet', 'bg-success');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R5', '2025/05/20', 150, 'Bitcoin', 'En cours', 'bg-warning');
INSERT INTO retraits (user_id, operationNumber, date, amount, method, status, statusClass) VALUES (1, 'R6', '2025/05/10', 300, 'Paypal', 'complet', 'bg-success');
INSERT INTO tradingHistory (user_id, operationNumber, temps, paireDevises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass, details) VALUES (1, 'T1', '2025/06/09 14:30', 'BTC/USD', 'Acheter', 'bg-success', 1000, 500, 'complet', 'bg-success', 175.50, 'text-success', '{}');
INSERT INTO tradingHistory (user_id, operationNumber, temps, paireDevises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass, details) VALUES (1, 'T2', '2025/06/09 13:15', 'ETH/USD', 'Vendre', 'bg-success', 500, 2850, 'complet', 'bg-success', -25.00, 'text-danger', '{}');
INSERT INTO tradingHistory (user_id, operationNumber, temps, paireDevises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass, details) VALUES (1, 'T3', '2025/06/09 12:00', 'ADA/USD', 'Acheter', 'bg-danger', 300, 0.45, 'En cours', 'bg-warning', NULL, '', '{}');
INSERT INTO loginHistory (user_id, date, ip, device) VALUES (1, '2025/06/09 15:00', '192.168.0.1', 'Chrome - Windows');
INSERT INTO loginHistory (user_id, date, ip, device) VALUES (1, '2025/06/08 18:20', '192.168.0.2', 'Firefox - Android');
INSERT INTO loginHistory (user_id, date, ip, device) VALUES (1, '2025/06/07 09:10', '192.168.0.3', 'Safari - iOS');
INSERT INTO loginHistory (user_id, date, ip, device) VALUES (1, '2025/06/06 23:45', '192.168.0.4', 'Edge - Windows');
INSERT INTO loginHistory (user_id, date, ip, device) VALUES (1, '2025/06/05 08:30', '192.168.0.5', 'Chrome - macOS');
INSERT INTO bank_withdrawl_info (user_id, widhrawBankName, widhrawAccountName, widhrawAccountNumber, widhrawIban, widhrawSwiftCode)
VALUES (1, 'My Bank', 'Company Ltd', '987654321', 'IBAN987654', 'SWIFT987');

-- example pending order
INSERT INTO orders (user_id, pair, type, side, quantity, target_price, stop_price)
VALUES (1, 'BTC/USDT', 'limit', 'buy', 0.1, 30000, NULL);

-- example executed trade for that order
INSERT INTO trades (user_id, order_id, pair, side, quantity, price, total_value, fee, profit_loss)
VALUES (1, 1, 'BTC/USDT', 'buy', 0.1, 30000, 3000, 0, 0);

INSERT INTO verification_status (user_id, enregistrementducompte, confirmationdeladresseemail, telechargerlesdocumentsdidentite, verificationdeladresse, revisionfinale)
VALUES (1, 1, 1, 0, 0, 2);
