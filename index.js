const express = require('express');
const app = express();

app.use(express.json());

app.get('/api.php', (req, res) => {
    const action = req.query.action;
    if (action === 'test') {
        return res.json({ success: true, message: 'API LIVE on Render!' });
    }
    res.json({ success: false, error: 'Unknown action' });
});

app.get('/webhook', (req, res) => {
    const text = req.query.text || '';
    if (text === '/start') {
        res.send('🔥 SENSI BOT ACTIVE! Send /gen 5 7days');
    } else if (text.startsWith('/gen')) {
        res.send('✅ Generated keys!');
    } else {
        res.send('❌ Send /start');
    }
});

app.get('/', (req, res) => {
    res.send('<h1>🔥 SENSI MODS SERVER LIVE</h1><p>API: <a href="/api.php?action=test">/api.php?action=test</a></p>');
});

app.listen(3000, () => console.log('🔥 SENSI SERVER RUNNING'));
