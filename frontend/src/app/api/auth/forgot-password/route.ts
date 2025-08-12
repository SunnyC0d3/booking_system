import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
    try {
        const body = await request.json();
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const response = await axios.post(
            `${baseUrl}/api/forgot-password`,
            body,
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: 15000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Forgot password error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to send reset email';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { message: errorMessage },
            { status }
        );
    }
}