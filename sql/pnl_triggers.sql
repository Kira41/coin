DELIMITER //
-- Automatically compute profit/loss whenever a trade is recorded
CREATE TRIGGER trg_trades_before_insert
BEFORE INSERT ON trades
FOR EACH ROW
BEGIN
  DECLARE avg_price DECIMAL(20,10);
  DECLARE base_currency VARCHAR(10);
  SET base_currency = SUBSTRING_INDEX(NEW.pair,'/',1);
  IF NEW.side = 'sell' THEN
    SELECT purchase_price INTO avg_price
    FROM wallets
    WHERE user_id = NEW.user_id AND currency = LOWER(base_currency)
    LIMIT 1;
    IF avg_price IS NULL THEN
      SET avg_price = 0;
    END IF;
    SET NEW.profit_loss = (NEW.price - avg_price) * NEW.quantity;
  ELSE
    -- buy trade (long position open), no immediate profit
    SET NEW.profit_loss = 0;
  END IF;
END//
DELIMITER ;

