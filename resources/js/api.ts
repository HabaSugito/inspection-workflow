import type { Inspection } from './types';

export class ApiError extends Error {
    readonly status: number;

    constructor(status: number, message: string) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
    let response: Response;

    try {
        response = await fetch(path, {
            ...init,
            headers: { Accept: 'application/json', ...init?.headers },
        });
    } catch {
        throw new ApiError(0, 'Could not reach the server.');
    }

    const body: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            response.status,
            messageFrom(body) ?? `Request failed (${response.status}).`,
        );
    }

    if (body === null) {
        throw new ApiError(response.status, 'The server sent a response that could not be read.');
    }

    return body as T;
}

function messageFrom(body: unknown): string | null {
    if (typeof body === 'object' && body !== null && 'message' in body) {
        const { message } = body;

        return typeof message === 'string' && message !== '' ? message : null;
    }

    return null;
}

export async function listInspections(): Promise<Inspection[]> {
    const body = await request<{ data: Inspection[] }>('/api/inspections');

    return body.data;
}
