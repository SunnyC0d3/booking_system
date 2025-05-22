require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const cookieParser = require('cookie-parser');

const app = express();
app.use(express.json());
app.use(cookieParser());

const {
    PORT = 5001,
    LARAVEL_API_URL,
    AUTH_SERVER_CLIENT_ID,
    AUTH_SERVER_CLIENT_SECRET,
    AUTH_SERVER_PUBLIC_KEY,
    FRONTEND_ORIGIN
} = process.env;

// Rate limit
app.use(rateLimit({
    windowMs: 60 * 1000,
    max: 30,
    standardHeaders: true
}));

// CORS
app.use(cors({
    origin: FRONTEND_ORIGIN,
    credentials: true
}));

// Middleware to verify auth server cookie (simulate session)
const verifyFrontend = (req, res, next) => {
    const token = req.cookies['auth_server_csrf'];
    if (!token || token !== AUTH_SERVER_PUBLIC_KEY) {
        return res.status(401).json({ message: 'Unauthorized frontend' });
    }
    next();
};

// Step 1: Auth server fetches access token from Laravel
async function getClientAccessToken() {
    const response = await axios.post(`${LARAVEL_API_URL}/oauth/token`, {
        grant_type: 'client_credentials',
        client_id: AUTH_SERVER_CLIENT_ID,
        client_secret: AUTH_SERVER_CLIENT_SECRET,
        scope: ''
    });

    return response.data.access_token;
}

// Endpoint React uses on load to set a secure cookie
app.post('/api/public-token', async (req, res) => {
    try {
        res
            .cookie('auth_server_csrf', AUTH_SERVER_PUBLIC_KEY, {
                httpOnly: true,
                secure: true,
                sameSite: 'Strict',
                maxAge: 5 * 60 * 1000
            })
            .json({ message: 'Frontend verified' });
    } catch (err) {
        return res.status(500).json({ message: 'Token setup failed' });
    }
});

// Secure proxy to Laravel's protected public API
app.get('/api/products', verifyFrontend, async (req, res) => {
    try {
        const token = await getClientAccessToken();

        const response = await axios.get(`${LARAVEL_API_URL}/api/products`, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        });

        res.json(response.data);
    } catch (err) {
        res.status(err.response?.status || 500).json({
            message: err.response?.data?.message || 'Failed to fetch products'
        });
    }
});

app.listen(PORT, () => console.log(`🔐 Auth server running on http://localhost:${PORT}`));
