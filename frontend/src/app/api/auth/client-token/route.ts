import { NextRequest, NextResponse } from 'next/server';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

interface ClientCredentialsRequest {
    grant_type: 'client_credentials';
    client_id: string;
    client_secret: string;
    scope: string;
}

let tokenCache: {
    token: string;
    expiresAt: number;
} | null = null;

export async function POST(request: NextRequest) {
    try {
        const clientId = process.env.API_CLIENT_ID;
        const clientSecret = process.env.API_SECRET_KEY;
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!clientId || !clientSecret) {
            return NextResponse.json(
                {
                    error: 'Client credentials not configured',
                    code: 'MISSING_CREDENTIALS'
                },
                { status: 500 }
            );
        }

        if (tokenCache && Date.now() < (tokenCache.expiresAt - 300000)) {
            return NextResponse.json({
                access_token: tokenCache.token,
                expires_in: Math.floor((tokenCache.expiresAt - Date.now()) / 1000),
                token_type: 'Bearer',
                scope: '',
                issued_at: Math.floor(Date.now() / 1000),
                from_cache: true
            });
        }

        const credentials = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');

        const requestBody: ClientCredentialsRequest = {
            grant_type: 'client_credentials',
            client_id: clientId,
            client_secret: clientSecret,
            scope: '',
        };

        const response = await fetch(`${baseUrl}/oauth/token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Basic ${credentials}`,
                'User-Agent': 'CreativeBusiness-Frontend/1.0',
            },
            body: JSON.stringify(requestBody),
            signal: AbortSignal.timeout(10000),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));

            const errorMessage = errorData.error_description ||
                errorData.message ||
                errorData.error ||
                `HTTP ${response.status}: ${response.statusText}`;

            return NextResponse.json(
                {
                    error: errorMessage,
                    code: errorData.error || 'CLIENT_CREDENTIALS_FAILED',
                    status: response.status
                },
                { status: response.status }
            );
        }

        const tokenData: ClientCredentialsResponse = await response.json();

        if (!tokenData.access_token || !tokenData.expires_in) {
            return NextResponse.json(
                {
                    error: 'Invalid token response from OAuth server',
                    code: 'INVALID_TOKEN_RESPONSE'
                },
                { status: 502 }
            );
        }

        tokenCache = {
            token: tokenData.access_token,
            expiresAt: Date.now() + (tokenData.expires_in * 1000)
        };


        return NextResponse.json({
            access_token: tokenData.access_token,
            expires_in: tokenData.expires_in,
            token_type: tokenData.token_type,
            scope: tokenData.scope,
            issued_at: Math.floor(Date.now() / 1000),
        });

    } catch (error: any) {
        if (error.name === 'TimeoutError' || error.name === 'AbortError') {
            return NextResponse.json(
                {
                    error: 'OAuth server timeout',
                    code: 'OAUTH_TIMEOUT'
                },
                { status: 408 }
            );
        }

        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            return NextResponse.json(
                {
                    error: 'Unable to connect to OAuth server',
                    code: 'OAUTH_CONNECTION_FAILED'
                },
                { status: 503 }
            );
        }

        return NextResponse.json(
            {
                error: error.message || 'Authentication service unavailable',
                code: 'INTERNAL_ERROR'
            },
            { status: 500 }
        );
    }
}