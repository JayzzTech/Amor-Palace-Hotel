async function request(path) {
    const response = await fetch(path, {
        headers: { Accept: "application/json" },
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || `Request failed (${response.status})`);
    return result;
}

function availabilityQuery(availability = {}) {
    const params = new URLSearchParams();
    for (const key of ["checkIn", "checkOut", "guests"]) {
        if (availability[key] !== undefined && availability[key] !== "") {
            params.set(key, String(availability[key]));
        }
    }
    const query = params.toString();
    return query ? `?${query}` : "";
}

export const getBranches = (availability) =>
    request(`/api/branches${availability ? availabilityQuery(availability) : ""}`);
export const getRooms = (branchId, availability) =>
    request(`/api/branches/${branchId}/rooms${availability ? availabilityQuery(availability) : ""}`);
export const getRoom = (roomId) => request(`/api/rooms/${roomId}`);
export const getReservation = (reservationId) => request(`/api/reservations/${reservationId}`);
export const getCsrfToken = (tokenId) => request(`/api/csrf/${tokenId}`);
export const getSession = () => request('/api/session');
export const registerAccount = (body) => postJson("/api/register", body);
export const loginAccount = (body) => postJson("/api/login", body);
export const getAccountDashboard = () => request("/api/account");
export const getStaffDashboard = (query = "") =>
    request(`/api/staff${query ? `?${query}` : ""}`);
export const getStaffOperations = () => request("/api/staff/operations");
export const getSuperDashboard = () => request("/api/super");
export const createBranch = (body) => postJson("/api/super/branches", body);
export const updateBranch = (id, body) =>
    patchJson(`/api/super/branches/${id}`, body);
export const createUser = (body) => postJson("/api/super/users", body);
export const updateUser = (id, body) =>
    patchJson(`/api/super/users/${id}`, body);
export async function postJson(path, body) {
    const response = await fetch(path, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
    });
    const result = await response.json();
    if (!response.ok)
        throw new Error(result.error || `Request failed (${response.status})`);
    return result;
}
export const createReservation = (roomId, body) =>
    postJson(`/api/rooms/${roomId}/reservations`, body);
export const createRoom = (body) => postJson("/api/staff/rooms", body);
export const patchJson = async (path, body) => {
    const response = await fetch(path, {
        method: "PATCH",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
    });
    const result = await response.json();
    if (!response.ok)
        throw new Error(result.error || `Request failed (${response.status})`);
    return result;
};
