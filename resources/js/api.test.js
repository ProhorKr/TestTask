import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from './api';

afterEach(() => vi.unstubAllGlobals());
describe('API client', () => {
    it('sends JSON headers and returns the resource', async () => {
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { id: 1 } })));
        vi.stubGlobal('fetch', fetch);
        expect(await api('/notes')).toEqual({ data: { id: 1 } });
        expect(fetch).toHaveBeenCalledWith('/api/notes', expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }));
    });
    it('handles empty deletion response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 204 })));
        expect(await api('/notes/1', { method: 'DELETE' })).toBeNull();
    });
    it('preserves field validation errors', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ errors: { title: ['Required'] } }), { status: 422 })));
        await expect(api('/notes')).rejects.toMatchObject({ status: 422, errors: { title: ['Required'] } });
    });
    it('handles network failures with readable error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));
        await expect(api('/notes')).rejects.toBeInstanceOf(ApiError);
    });
    it('handles non-JSON server errors', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<h1>Bad gateway</h1>', { status: 502 })));
        await expect(api('/notes')).rejects.toMatchObject({ status: 502 });
    });
});
