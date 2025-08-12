import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
    try {
        const { refresh_token } = await request.json();

        if (!refresh_token) {
            return NextResponse.json(
                { error: 'Refresh token is required' },
                { status: 400 }
            );
        }

        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const response = await axios.post(
            `${baseUrl}/api/refresh`,
            { refresh_token },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: 10000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Token refresh error:', error);

        const errorMessage = error.response?.data?.message || 'Token refresh failed';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { error: errorMessage },
            { status }
        );
    }
}