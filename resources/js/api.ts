import type { Inspection, NewInspection } from './types';

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
    readonly status: number;
    readonly fieldErrors: FieldErrors;

    constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.fieldErrors = fieldErrors;
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
            fieldErrorsFrom(body),
        );
    }

    if (body === null) {
        throw new ApiError(response.status, 'The server sent a response that could not be read.');
    }

    return body as T;
}

function messageFrom(body: unknown): string | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    if ('errors' in body && typeof body.errors === 'object' && body.errors !== null) {
        const first: unknown = Object.values(body.errors).flat()[0];

        if (typeof first === 'string') {
            return first;
        }
    }

    if ('message' in body && typeof body.message === 'string' && body.message !== '') {
        return body.message;
    }

    return null;
}

function fieldErrorsFrom(body: unknown): FieldErrors {
    if (
        typeof body !== 'object' ||
        body === null ||
        !('errors' in body) ||
        typeof body.errors !== 'object' ||
        body.errors === null
    ) {
        return {};
    }

    const fieldErrors: FieldErrors = {};

    for (const [field, messages] of Object.entries(body.errors)) {
        if (Array.isArray(messages)) {
            fieldErrors[field] = messages.filter((message) => typeof message === 'string');
        }
    }

    return fieldErrors;
}

async function post(path: string, payload?: object): Promise<Inspection> {
    const body = await request<{ data: Inspection }>(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload ?? {}),
    });

    return body.data;
}

export async function listInspections(): Promise<Inspection[]> {
    const body = await request<{ data: Inspection[] }>('/api/inspections');

    return body.data;
}

export function createInspection(input: NewInspection): Promise<Inspection> {
    return post('/api/inspections', input);
}

export function assignInspector(id: number, inspectorName: string): Promise<Inspection> {
    return post(`/api/inspections/${id}/assign`, { inspector_name: inspectorName });
}

export function startInspection(id: number): Promise<Inspection> {
    return post(`/api/inspections/${id}/start`);
}

export function completeInspection(id: number): Promise<Inspection> {
    return post(`/api/inspections/${id}/complete`);
}
