require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const cookieParser = require('cookie-parser');
const crypto = require('crypto');

const nonceStore = new Map();
const cookieExpiryTime = 5 * 60 * 1000;

const API_CLIENT = 'client';
const API_AUTH = 'auth';

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
    API_URL,
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

app.all('/proxy', verifyFrontend, async (req, res) => {
    const { apiEndpoint, authType = 'client' } = req.body;

    if (!apiEndpoint) {
        return res.status(400).json({ message: 'Missing Endpoint URL' });
    }

    try {
        let token;

        if (authType === API_CLIENT) {
            const response = await axios.post(`${API_URL}/oauth/token`, {
                grant_type: 'client_credentials',
                client_id: AUTH_SERVER_CLIENT_ID,
                client_secret: AUTH_SERVER_CLIENT_SECRET,
            });
            token = response.data.access_token;
        } else if (authType === API_AUTH) {
            const authHeader = req.headers['authorization'];
            if (!authHeader || !authHeader.startsWith('Bearer ')) {
                return res.status(401).json({ message: 'Missing user token' });
            }
            token = authHeader.replace('Bearer ', '');
        } else {
            return res.status(400).json({ message: 'Invalid auth type' });
        }

        const response = await axios({
            method: req.method,
            url: `${API_URL}${apiEndpoint}`,
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
            data: req.body.data || {}
        });

        return res.status(response.status).json(response.data);
    } catch (err) {
        console.error('Endpoint proxy error:', err.response?.data || err.message);
        return res.status(err.response?.status || 500).json({
            message: err.response?.data?.message || 'Endpoint request failed',
        });
    }
});

app.listen(PORT, () => console.log(`🔐 Auth server running on http://localhost:${PORT}`));
