import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
    try {
        const authHeader = request.headers.get('authorization');
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!authHeader) {
            return NextResponse.json(
                { message: 'Authorization required' },
                { status: 401 }
            );
        }

        const response = await axios.post(
            `${baseUrl}/api/email/verification-notification`,
            {},
            {
                headers: {
                    'Authorization': authHeader,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: 15000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Resend verification error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to resend verification email';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { message: errorMessage },
            { status }
        );
    }
}