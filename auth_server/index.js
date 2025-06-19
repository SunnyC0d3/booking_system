require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const cookieParser = require('cookie-parser');
const crypto = require('crypto');
const Redis = require('ioredis');

const redis = new Redis({
    host: process.env.REDIS_HOST || 'localhost',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || null,
    retryDelayOnFailover: 100,
    maxRetriesPerRequest: 3,
});

redis.on('error', (err) => {
    console.error('Redis connection error:', err);
});

redis.on('connect', () => {
    console.log('✅ Redis connected successfully');
});

const nonceExpiryTime = 2 * 60;

const API_CLIENT = 'client';
const API_AUTH = 'auth';
const IP_WHITELIST = (process.env.IP_WHITELIST || '').split(',').map(ip => ip.trim());

const {
    PORT = 5001,
    API_URL,
    API_CLIENT_ID,
    API_CLIENT_SECRET,
    AUTH_SERVER_SECRET,
    FRONTEND_ORIGIN
} = process.env;

const storeNonce = async (nonce, data) => {
    try {
        await redis.setex(`nonce:${nonce}`, nonceExpiryTime, JSON.stringify(data));
        return true;
    } catch (error) {
        console.error('Failed to store nonce:', error);
        return false;
    }
};

const getNonce = async (nonce) => {
    try {
        const data = await redis.get(`nonce:${nonce}`);
        return data ? JSON.parse(data) : null;
    } catch (error) {
        console.error('Failed to get nonce:', error);
        return null;
    }
};

const deleteNonce = async (nonce) => {
    try {
        await redis.del(`nonce:${nonce}`);
        return true;
    } catch (error) {
        console.error('Failed to delete nonce:', error);
        return false;
    }
};

const app = express();
app.use(express.json());
app.use(cookieParser(AUTH_SERVER_SECRET))

app.use((req, res, next) => {
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'DENY');
    res.setHeader('X-XSS-Protection', '1; mode=block');
    if (process.env.NODE_ENV === 'production') {
        res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }
    res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'");
    res.removeHeader('X-Powered-By');
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

    next();
});

app.use(cors({
    origin: FRONTEND_ORIGIN,
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
    exposedHeaders: ['Set-Cookie']
}));

app.options('/api/proxy', cors({
    origin: FRONTEND_ORIGIN,
    credentials: true
}));

const authLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 5,
    standardHeaders: true,
    keyGenerator: (req) => `auth:${req.ip}:${req.get('User-Agent')}`,
    handler: (req, res) => {
        res.status(429).json({
            message: 'Too many authentication attempts, please try again later.',
            retryAfter: 60
        });
    }
});

const proxyLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 30,
    standardHeaders: true,
    keyGenerator: (req) => `proxy:${req.ip}`,
    handler: (req, res) => {
        res.status(429).json({
            message: 'Too many requests, please try again later.',
            retryAfter: 60
        });
    }
});

const verifyFrontend = async (req, res, next) => {
    const nonce = req.signedCookies['auth_server_csrf'];

    if (!nonce) {
        return res.status(401).json({message: 'Not authorised - missing nonce.'});
    }

    const stored = await getNonce(nonce);

    if (!stored) {
        return res.status(401).json({message: 'Not authorised - invalid or expired nonce.'});
    }

    if (stored.ip !== req.ip) {
        await deleteNonce(nonce);
        return res.status(403).json({message: 'Not authorised - IP mismatch.'});
    }

    if (stored.userAgent !== req.get('User-Agent')) {
        await deleteNonce(nonce);
        return res.status(403).json({message: 'Not authorised - user agent mismatch.'});
    }

    if (req.get('Origin') !== FRONTEND_ORIGIN || !req.get('Origin')) {
        await deleteNonce(nonce);
        return res.status(403).json({message: 'Not authorised - origin mismatch.'});
    }

    next();
};

app.post('/api/server-token', authLimiter, async (req, res) => {
    const nonce = crypto.randomBytes(16).toString('hex');

    if (!IP_WHITELIST.includes(req.ip)) {
        return res.status(403).json({message: 'IP not whitelisted.'});
    }

    if (!req.get('Origin') || req.get('Origin') !== FRONTEND_ORIGIN) {
        return res.status(403).json({message: 'Not authorised.'});
    }

    const nonceData = {
        ip: req.ip,
        userAgent: req.get('User-Agent'),
        createdAt: Date.now()
    };

    const stored = await storeNonce(nonce, nonceData);

    if (!stored) {
        return res.status(500).json({message: 'Failed to create session.'});
    }

    try {
        res
            .cookie('auth_server_csrf', nonce, {
                httpOnly: true,
                secure: process.env.NODE_ENV === 'production',
                sameSite: 'Strict',
                maxAge: nonceExpiryTime * 1000,
                signed: true
            })
            .json({
                message: 'Verified'
            });
    } catch (err) {
        await deleteNonce(nonce);
        return res.status(500).json({message: 'Token setup failed'});
    }
});

app.post('/api/proxy', proxyLimiter, verifyFrontend, async (req, res) => {
    const {path, authType, method, data} = req.body;

    if (!path) {
        return res.status(400).json({message: 'Missing Endpoint URL'});
    }

    try {
        let token;

        if (authType === API_CLIENT) {
            token = await redis.get('client_access_token');

            if (!token) {
                const response = await axios.post(`${API_URL}/oauth/token`, {
                    grant_type: 'client_credentials',
                    client_id: API_CLIENT_ID,
                    client_secret: API_CLIENT_SECRET,
                });

                token = response.data.access_token;

                await redis.set('client_access_token', token, 'EX', response.data.expires_in);
            }
        } else if (authType === API_AUTH) {
            const authHeader = req.headers['authorization'];
            if (!authHeader || !authHeader.startsWith('Bearer ')) {
                return res.status(401).json({message: 'Missing user token'});
            }
            token = authHeader.replace('Bearer ', '');
        } else {
            return res.status(400).json({message: 'Invalid auth type'});
        }

        const response = await axios({
            method: method,
            url: `${API_URL}${path}`,
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
            data: data || {}
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