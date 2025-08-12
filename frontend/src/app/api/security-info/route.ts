import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function GET(request: NextRequest) {
    try {
        const authHeader = request.headers.get('authorization');
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!authHeader) {
            return NextResponse.json(
                { message: 'Authorization required' },
                { status: 401 }
            );
        }

        const response = await axios.get(
            `${baseUrl}/api/security-info`,
            {
                headers: {
                    'Authorization': authHeader,
                    'Accept': 'application/json',
                },
                timeout: 10000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Get security info error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to fetch security information';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { message: errorMessage },
            { status }
        );
    }
}