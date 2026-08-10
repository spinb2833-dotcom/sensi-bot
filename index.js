const express = require('express');
const app = express();

// ── ALLOW ALL REQUESTS ──
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ── TELEGRAM WEBHOOK (FIXED) ──
app.post('/webhook', async (req, res) => {
    try {
        const update = req.body;
        if (!update || !update.message) {
            return res.sendStatus(200);
        }

        const chatId = update.message.chat.id;
        const text = update.message.text || '';

        // ── COMMANDS ──
        let reply = '';

        if (text === '/start') {
            reply = `🔥 SENSI MODS ADMIN BOT 🔥\n\n✅ Bot is ACTIVE!\n\n📋 Commands:\n/gen [amount] [time] - Generate keys\n/list - Show all keys\n/check [key] - Check key\n/stats - Show statistics`;
        } 
        else if (text.startsWith('/gen')) {
            const parts = text.split(' ');
            const amount = parts[1] || 5;
            const time = parts[2] || '7days';
            reply = `✅ Generated ${amount} keys for ${time}!\n\n🔑 SENSI-XXXX\n⏰ Expires: (Add DB later)`;
        } 
        else if (text === '/list') {
            reply = `📋 KEY LIST:\n\nSENSI-TEST123 | Active | 2026-12-31\nSENSI-DEMO456 | Used | 2026-10-01`;
        } 
        else if (text === '/stats') {
            reply = `📊 STATISTICS:\n\n🔑 Total Keys: 2\n🟢 Active: 1\n🟡 Used: 1`;
        } 
        else {
            reply = `❌ Unknown command. Send /start for help.`;
        }

        // ── SEND RESPONSE ──
        await sendTelegramMessage(chatId, reply);
        res.sendStatus(200);

    } catch (error) {
        console.error('Webhook error:', error);
        res.sendStatus(500);
    }
});

// ── FUNCTION TO SEND MESSAGE ──
async function sendTelegramMessage(chatId, text) {
    const token = "8848564612:AAFj3ueTQ4AvMaXu-KYqeLj_sjUAxU40dUI";
    const url = `https://api.telegram.org/bot${token}/sendMessage`;
    
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            chat_id: chatId,
            text: text,
            parse_mode: 'Markdown'
        })
    });
    
    return response.json();
}

// ── TEST API ──
app.get('/api.php', (req, res) => {
    const action = req.query.action;
    if (action === 'test') {
        return res.json({ success: true, message: 'API LIVE on Render!' });
    }
    res.json({ success: false, error: 'Unknown action' });
});

// ── ROOT ──
app.get('/', (req, res) => {
    res.send('🔥 SENSI MODS SERVER LIVE!');
});

// ── START ──
app.listen(3000, () => console.log('🔥 SENSI SERVER RUNNING'));
