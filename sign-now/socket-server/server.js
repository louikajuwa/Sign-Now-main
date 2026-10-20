const WebSocket = require('ws');
const Redis = require('ioredis');

const WS_PORT = process.env.WS_PORT || 3027;
const REDIS_HOST = process.env.REDIS_HOST || 'redis';
const REDIS_PORT = process.env.REDIS_PORT || 6379;

const wss = new WebSocket.Server({ port: WS_PORT });
const redis = new Redis({ host: REDIS_HOST, port: REDIS_PORT });

redis.subscribe('docsign_events', (err) => {
    if (err) {
        console.error('Failed to subscribe to docsign_events:', err);
        process.exit(1);
    }
    console.log(`Subscribed to docsign_events channel`);
});

redis.on('message', (channel, message) => {
    if (channel !== 'docsign_events') return;

    let clientCount = 0;
    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
            clientCount++;
        }
    });

    const parsed = JSON.parse(message);
    console.log(`[${parsed.event}] Broadcast to ${clientCount} client(s)`);
});

wss.on('connection', (ws) => {
    console.log(`Client connected (total: ${wss.clients.size})`);

    ws.on('close', () => {
        console.log(`Client disconnected (total: ${wss.clients.size})`);
    });

    ws.on('error', (err) => {
        console.error('WebSocket error:', err.message);
    });
});

console.log(`Sign-Now WebSocket server running on port ${WS_PORT}`);
