import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
    try {
        const authHeader = request.headers.get('authorization');
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (authHeader) {
            await axios.post(
                `${baseUrl}/api/logout`,
                {},
                {
                    headers: {
                        'Authorization': authHeader,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    timeout: 10000,
                }
            );
        }

        return NextResponse.json({ message: 'Logged out successfully' });

    } catch (error: any) {
        console.error('Logout error:', error);
        // Always return success for logout to prevent client-side issues
        return NextResponse.json({ message: 'Logged out successfully' });
    }
}