import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function GET(request: NextRequest) {
    try {
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const response = await axios.get(
            `${baseUrl}/api/health`,
            {
                timeout: 5000,
            }
        );

        return NextResponse.json({
            status: 'ok',
            timestamp: new Date().toISOString(),
            backend: response.data,
        });

    } catch (error: any) {
        return NextResponse.json(
            {
                status: 'error',
                timestamp: new Date().toISOString(),
                error: error.message,
            },
            { status: 503 }
        );
    }
}