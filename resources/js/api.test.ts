import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, assignInspector, createInspection, listInspections } from './api';
import { inspection } from './test-fixtures';

const fetchMock = vi.fn<typeof fetch>();

function respond(status: number, body: unknown): Response {
    return new Response(typeof body === 'string' ? body : JSON.stringify(body), { status });
}

async function failureOf(call: Promise<unknown>): Promise<ApiError> {
    try {
        await call;
    } catch (error) {
        if (error instanceof ApiError) {
            return error;
        }

        throw error;
    }

    throw new Error('The call did not fail.');
}

beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

describe('api', () => {
    it('returns the list from the data key', async () => {
        fetchMock.mockResolvedValue(respond(200, { data: [inspection({ id: 7 })] }));

        const list = await listInspections();

        expect(list.map((item) => item.id)).toEqual([7]);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/inspections');
    });

    it('turns an error response into an ApiError with the status and the server message', async () => {
        fetchMock.mockResolvedValue(
            respond(409, { message: 'This inspection is assigned.', current_status: 'assigned' }),
        );

        const error = await failureOf(listInspections());

        expect(error.status).toBe(409);
        expect(error.message).toBe('This inspection is assigned.');
        expect(error.fieldErrors).toEqual({});
    });

    it('keeps the errors of a 422 per field, and uses the first as the message', async () => {
        fetchMock.mockResolvedValue(
            respond(422, {
                message: 'The customer name field is required. (and 1 more error)',
                errors: {
                    customer_name: ['The customer name field is required.'],
                    requested_date: ['The requested date field is required.'],
                },
            }),
        );

        const error = await failureOf(createInspection({} as never));

        expect(error.status).toBe(422);
        expect(error.message).toBe('The customer name field is required.');
        expect(error.fieldErrors).toEqual({
            customer_name: ['The customer name field is required.'],
            requested_date: ['The requested date field is required.'],
        });
    });

    it('reports a network failure with status 0', async () => {
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        const error = await failureOf(listInspections());

        expect(error.status).toBe(0);
        expect(error.message).toBe('Could not reach the server.');
    });

    it('reports a successful response that is not JSON as an ApiError', async () => {
        fetchMock.mockResolvedValue(respond(200, '<html>not json</html>'));

        const error = await failureOf(listInspections());

        expect(error.status).toBe(200);
        expect(error.message).toBe('The server sent a response that could not be read.');
    });

    it('falls back to the status when an error response has no message', async () => {
        fetchMock.mockResolvedValue(respond(500, '<html>Server Error</html>'));

        const error = await failureOf(listInspections());

        expect(error.message).toBe('Request failed (500).');
    });

    it('creates an inspection with a JSON POST and returns the stored inspection', async () => {
        fetchMock.mockResolvedValue(respond(201, { data: inspection({ id: 9 }) }));
        const input = {
            customer_name: 'Jane Smith',
            vehicle_registration: 'ab12 cde',
            requested_date: '2027-01-15',
            notes: '',
        };

        const created = await createInspection(input);

        const [path, init] = fetchMock.mock.calls[0];
        expect(created.id).toBe(9);
        expect(path).toBe('/api/inspections');
        expect(init?.method).toBe('POST');
        expect(JSON.parse(init?.body as string)).toEqual(input);
        expect(new Headers(init?.headers).get('Content-Type')).toBe('application/json');
        expect(new Headers(init?.headers).get('Accept')).toBe('application/json');
    });

    it('assigns an inspector by posting the name to the inspection it was given', async () => {
        fetchMock.mockResolvedValue(
            respond(200, { data: inspection({ id: 4, status: 'assigned' }) }),
        );

        await assignInspector(4, 'Sam Carter');

        const [path, init] = fetchMock.mock.calls[0];
        expect(path).toBe('/api/inspections/4/assign');
        expect(init?.method).toBe('POST');
        expect(JSON.parse(init?.body as string)).toEqual({ inspector_name: 'Sam Carter' });
    });
});
