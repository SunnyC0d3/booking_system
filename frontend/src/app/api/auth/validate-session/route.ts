import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function GET(request: NextRequest) {
    try {
        const authHeader = request.headers.get('authorization');

        if (!authHeader) {
            return NextResponse.json({ valid: false });
        }

        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const response = await axios.get(
            `${baseUrl}/api/user`,
            {
                headers: {
                    'Authorization': authHeader,
                    'Accept': 'application/json',
                },
                timeout: 5000,
            }
        );

        return NextResponse.json({ valid: true, user: response.data });

    } catch (error: any) {
        return NextResponse.json({ valid: false });
    }
}