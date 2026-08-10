// ============================================================
// 🔥 SENSI BOT - CYCLIC DEPLOYMENT (NO CREDIT CARD NEEDED)
// ============================================================

const express = require('express');
const app = express();

// ── ALLOW ALL REQUESTS ──
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    next();
});

// ── BOT LOGIC ──
app.get('/webhook', (req, res) => {
    const text = req.query.text || '';
    const chatId = req.query.chatId || '';

    if (text === '/start') {
        res.send(`🔥 SENSI BOT ACTIVE!\nSend /gen 5 7days`);
    } else if (text.startsWith('/gen')) {
        const parts = text.split(' ');
        const amount = parts[1] || 5;
        const time = parts[2] || '7days';
        res.send(`✅ Generated ${amount} keys for ${time}!`);
    } else {
        res.send('❌ Unknown command. Send /start');
    }
});

// ── KEEP ALIVE ──
app.listen(3000, () => console.log('✅ SENSI BOT RUNNING'));
