const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.status = status;
    }
}

export function toQuery(filters) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    }
    return params.toString();
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers ?? {}),
        },
    });

    if (!response.ok) {
        let message = 'The server could not return the figures.';
        if (response.status === 401 || response.status === 419) {
            message = 'Your session has ended. Please sign in again.';
        } else {
            try {
                const body = await response.json();
                message = body.message || message;
            } catch {
                // keep the generic message
            }
        }
        throw new ApiError(response.status, message);
    }

    return response.json();
}

export function fetchDashboard(filters, signal) {
    return request(`/api/dashboard?${toQuery(filters)}`, { signal });
}

export function acknowledgeAlert(id) {
    return request(`/api/alerts/${id}/acknowledge`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
}

export const exportUrl = (filters) => `/api/export.csv?${toQuery(filters)}`;

export const fhirUrl = (filters) => `/api/fhir/MeasureReport?${toQuery(filters)}`;
