require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const cookieParser = require('cookie-parser');
const crypto = require('crypto');

const nonceStore = new Map();
const cookieExpiryTime = 5 * 60 * 1000;

setInterval(() => {
    const now = Date.now();
    for (const [key, expiry] of nonceStore.entries()) {
        if (expiry < now) {
            nonceStore.delete(key);
        }
    }
}, 60 * 1000);


const {
    PORT = 5001,
    LARAVEL_API_URL,
    AUTH_SERVER_CLIENT_ID,
    AUTH_SERVER_CLIENT_SECRET,
    AUTH_SERVER_SECRET,
    FRONTEND_ORIGIN
} = process.env;

const app = express();
app.use(express.json());
app.use(cookieParser(AUTH_SERVER_SECRET));

app.use(rateLimit({
    windowMs: 60 * 1000,
    max: 30,
    standardHeaders: true
}));

app.use(cors({
    origin: FRONTEND_ORIGIN,
    credentials: true
}));

const verifyFrontend = (req, res, next) => {
    const nonce = req.signedCookies['auth_server_csrf'];

    if (!nonce || !nonceStore.has(nonce)) {
        return res.status(401).json({ message: 'Missing or invalid CSRF nonce' });
    }

    const expiry = nonceStore.get(nonce);

    if (Date.now() > expiry) {
        nonceStore.delete(nonce);
        return res.status(403).json({ message: 'Nonce expired' });
    }

    if (!nonce || nonce !== AUTH_SERVER_SECRET) {
        return res.status(401).json({message: 'Unauthorized frontend'});
    }

    if (req.get('Origin') !== FRONTEND_ORIGIN || !req.get('Origin')) {
        return res.status(403).json({message: 'Blocked: invalid origin or UA'});
    }

    if (!req.get('User-Agent') || req.get('User-Agent').includes('Postman') || req.get('User-Agent').includes('curl')) {
        return res.status(403).json({ message: 'Blocked: Invalid Client' });
    }

    next();
};

app.post('/api/server-token', async (req, res) => {
    const nonce = crypto.randomBytes(16).toString('hex');

    if (!req.get('Origin') || req.get('Origin') !== FRONTEND_ORIGIN) {
        return res.status(403).json({ message: 'Blocked: Invalid Origin' });
    }

    if (!req.get('User-Agent') || req.get('User-Agent').includes('Postman') || req.get('User-Agent').includes('curl')) {
        return res.status(403).json({ message: 'Blocked: Invalid Client' });
    }

    nonceStore.set(nonce, Date.now() + cookieExpiryTime);

    try {
        res
            .cookie('auth_server_csrf', nonce, {
                httpOnly: true,
                secure: true,
                sameSite: 'Strict',
                maxAge: cookieExpiryTime,
                signed: true
            })
            .json({
                message: 'Frontend verified'
            });
    } catch (err) {
        return res.status(500).json({message: 'Token setup failed'});
    }
});

app.listen(PORT, () => console.log(`🔐 Auth server running on http://localhost:${PORT}`));
