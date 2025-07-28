let socket;
function connectSocket(userId) {
  socket = io('http://localhost:3001');
  socket.on('connect', () => {
    socket.emit('join', userId);
  });
  socket.on('balance_updated', data => {
    if (window.updateBalance) {
      window.updateBalance(data.newBalance);
    } else if (window.dashboardData && window.dashboardData.personalData) {
      window.dashboardData.personalData.balance = parseFloat(data.newBalance);
      if (typeof window.refreshUI === 'function') window.refreshUI();
    }
  });
  socket.on('wallet_updated', () => {
    if (typeof fetchDashboardData === 'function') {
      fetchDashboardData();
    }
  });
  socket.on('new_trade', trade => {
    if (window.addTrade) window.addTrade(trade);
  });
  socket.on('order_filled', order => {
    if (window.handleOrderFilled) window.handleOrderFilled(order);
  });
  socket.on('new_order', order => {
    if (window.handleNewOrder) window.handleNewOrder(order);
  });
}
