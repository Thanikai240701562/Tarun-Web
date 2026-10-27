/**
 * TeamForge Ephemeral WebSocket Relay Server (Node.js)
 * Section 15: Zero persistent message storage in database.
 * Run locally with: node chat/server.js
 */

const http = require('http');
const PORT = process.env.WS_PORT || 8088;

// Create lightweight server with zero external dependencies
const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
        status: 'online',
        service: 'TeamForge Ephemeral WebSocket Relay',
        uptime: process.uptime()
    }));
});

// In-Memory active rooms: { [teamId]: Set<client_sockets> }
const rooms = new Map();

server.on('upgrade', (req, socket, head) => {
    // Basic WebSocket handshake
    const key = req.headers['sec-websocket-key'];
    if (!key) {
        socket.destroy();
        return;
    }

    const crypto = require('crypto');
    const digest = crypto
        .createHash('sha1')
        .update(key + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
        .digest('base64');

    const headers = [
        'HTTP/1.1 101 Switching Protocols',
        'Upgrade: websocket',
        'Connection: Upgrade',
        `Sec-WebSocket-Accept: ${digest}`
    ];

    socket.write(headers.join('\r\n') + '\r\n\r\n');

    let currentRoom = null;

    socket.on('data', (buffer) => {
        // Read unmasked or masked payload
        try {
            const secondByte = buffer[1];
            const isMasked = (secondByte & 0x80) === 0x80;
            let length = secondByte & 0x7f;
            let offset = 2;

            if (length === 126) {
                length = buffer.readUInt16BE(2);
                offset = 4;
            } else if (length === 127) {
                offset = 10;
            }

            let mask = null;
            if (isMasked) {
                mask = buffer.slice(offset, offset + 4);
                offset += 4;
            }

            const payload = buffer.slice(offset, offset + length);
            if (isMasked && mask) {
                for (let i = 0; i < payload.length; i++) {
                    payload[i] ^= mask[i % 4];
                }
            }

            const messageStr = payload.toString('utf8');
            const data = JSON.parse(messageStr);

            if (data.action === 'join') {
                currentRoom = String(data.teamId);
                if (!rooms.has(currentRoom)) {
                    rooms.set(currentRoom, new Set());
                }
                rooms.get(currentRoom).add(socket);
            } else if (data.action === 'message' && currentRoom) {
                // Broadcast to all sockets in same room
                const peers = rooms.get(currentRoom);
                if (peers) {
                    const outgoing = JSON.stringify({
                        type: 'chat',
                        message: data.message
                    });
                    const outBuf = Buffer.from(outgoing, 'utf8');
                    const frame = Buffer.alloc(2 + outBuf.length);
                    frame[0] = 0x81; // text frame FIN
                    frame[1] = outBuf.length;
                    outBuf.copy(frame, 2);

                    peers.forEach((peer) => {
                        if (peer.writable) peer.write(frame);
                    });
                }
            }
        } catch (e) {
            // Ignore malformed frames
        }
    });

    socket.on('close', () => {
        if (currentRoom && rooms.has(currentRoom)) {
            rooms.get(currentRoom).delete(socket);
        }
    });
});

server.listen(PORT, () => {
    console.log('===============================================================');
    console.log('   🚀 TeamForge Development Environment is Active!');
    console.log('---------------------------------------------------------------');
    console.log('   🌐 Web Application URL : http://localhost/teamforge/');
    console.log(`   ⚡ Ephemeral Chat Relay : ws://localhost:${PORT}`);
    console.log('   🔒 Privacy Mode        : Zero MySQL Message Persistence');
    console.log('===============================================================');
});
