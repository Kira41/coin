const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: '*' }
});

io.on('connection', (socket) => {
  console.log('client connected', socket.id);
  socket.on('join', (userId) => {
    socket.join(String(userId));
  });
});

app.use(express.json());

// Endpoint for PHP to emit events
app.post('/emit', (req, res) => {
  const { userId, event, data } = req.body;
  if (!event) return res.status(400).json({ error: 'event required' });
  if (userId) {
    io.to(String(userId)).emit(event, data);
  } else {
    io.emit(event, data);
  }
  res.json({ status: 'ok' });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
  console.log(`WebSocket server running on port ${PORT}`);
});
