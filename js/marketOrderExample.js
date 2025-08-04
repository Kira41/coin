import { marketOrder, closePosition } from './pnl.js';

// Example usage
const position = marketOrder('buy', 0.5, 500); // buy $500 of XRP at $0.50
const profit = closePosition(position, 0.55);   // sell when price hits $0.55
console.log(`Profit: $${profit.toFixed(2)}`);
