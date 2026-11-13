import { useEffect, useState } from "react";
import {
    createRoom,
    getCsrfToken,
    getStaffDashboard,
    getStaffOperations,
    patchJson,
    postJson,
} from "../../api.js";
import { RoomStatusPanel } from "./RoomStatusPanel.jsx";

export function StaffDashboardPage() {
    const [data, setData] = useState(null);
    const [token, setToken] = useState("");
    const [roomForm, setRoomForm] = useState({
        number: "",
        type: "Standard Room",
        capacity: 2,
        pricePerNight: "",
        amenities: "",
        description: "",
    });
    const [roomMessage, setRoomMessage] = useState("");
    const [operations, setOperations] = useState(null);
    const [operationsError, setOperationsError] = useState("");
    const [status, setStatus] = useState("");
    const [query, setQuery] = useState("");
    const loadReservations = (nextStatus = status, nextQuery = query) => {
        const params = new URLSearchParams();
        if (nextStatus) params.set("status", nextStatus);
        if (nextQuery) params.set("q", nextQuery);
        return getStaffDashboard(params.toString()).then((result) => setData(result.data));
    };
    const loadOperations = () => getStaffOperations().then((result) => {
        setOperations(result.data);
        setOperationsError("");
        return result.data;
    }).catch((error) => {
        setOperationsError(error.message);
        throw error;
    });
    useEffect(() => {
        loadReservations()
            .catch(() => setData(false));
        loadOperations().catch(() => {});
    }, []);
    useEffect(() => {
        getCsrfToken("app").then((result) => setToken(result.token));
    }, []);
    const updateStatus = async (id, status) => {
        let guestIdType;
        let guestIdNumber;
        if (status === "checked_in") {
            guestIdType = window.prompt("Guest ID type (e.g. National ID)");
            guestIdNumber = window.prompt("Guest ID number");
            if (!guestIdType || !guestIdNumber) return;
        }
        await postJson(`/api/staff/reservations/${id}/status`, {
            _token: token,
            status,
            guestIdType,
            guestIdNumber,
        });
        await loadReservations();
    };
    const updateRoom = async (room) => {
        const price = window.prompt(
            `Nightly price for room ${room.number}`,
            room.pricePerNight,
        );
        if (!price) return;
        await patchJson(`/api/staff/rooms/${room.id}`, {
            _token: token,
            pricePerNight: Number(price),
            active: room.active,
        });
        await loadReservations();
    };
    const addRoom = async (event) => {
        event.preventDefault();
        setRoomMessage("");
        try {
            await createRoom({
                ...roomForm,
                branchId: data.branch.id,
                _token: token,
            });
            setRoomMessage("Room added successfully.");
            setRoomForm({
                number: "",
                type: "Standard Room",
                capacity: 2,
                pricePerNight: "",
                amenities: "",
                description: "",
            });
            await loadReservations();
        } catch (error) {
            setRoomMessage(error.message);
        }
    };
    if (data === false)
        return (
            <section className="page"><div className="wrap dashboard-page front-desk-dashboard">
                <p className="error">
                    You need staff access to view this dashboard.
                </p>
            </div></section>
        );
    if (!data)
        return (
            <section className="page">
                <p>Loading front desk…</p>
            </section>
        );
    const nextStatus = { pending: "confirmed", confirmed: "checked_in", checked_in: "checked_out" };
    const actionLabel = { confirmed: "Confirm", checked_in: "Check in", checked_out: "Check out" };
    const statLabels = { arrivals: "Today's arrivals", inhouse: "Current guests", pending: "Pending reservations", revenue: "Recorded revenue" };
    const formatDate = (value, withTime = false) => new Intl.DateTimeFormat("en-GB", {
        day: "2-digit", month: "short", year: "numeric", ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
    }).format(new Date(value));
    return (
        <section className="page"><div className="wrap dashboard-page front-desk-dashboard">
            <div className="dash-head dashboard-heading row-between" id="staff-overview"><div><p className="eyebrow dark">{data.role === "ROLE_ADMIN" ? "Branch management" : "Hotel operations"}</p>
            <h1>{data.role === "ROLE_ADMIN" ? "Admin dashboard" : "Front desk"}, {data.branch.name}</h1><p className="muted">{data.role === "ROLE_ADMIN" ? "Manage reservations, room inventory and daily operations for your assigned branch." : "Handle arrivals, stays, housekeeping and room operations for your assigned branch."}</p></div>
            </div>
            <div className="dashboard-workspace">
                <nav className="dashboard-section-nav" aria-label="Branch dashboard sections">
                    <span>Branch operations</span>
                    <a href="#staff-overview">Overview</a>
                    <a href="#staff-reservations">Reservations</a>
                    <a href="#staff-rooms">Rooms</a>
                    <a href="#staff-housekeeping">Room status</a>
                    <a href="#staff-maintenance">Maintenance</a>
                    <a href="#staff-inventory">Inventory</a>
                </nav>
                <div className="dashboard-content">
            <div className="stats">
                {Object.entries(data.stats).map(([key, value]) => (
                    <div className="stat" key={key}>
                        <b>
                            {key === "revenue"
                                ? `₱${value.toLocaleString()}`
                                : value}
                        </b>
                        <span>{statLabels[key] || key.replace("_", " ")}</span>
                    </div>
                ))}
            </div>
            <section className="panel dashboard-panel" id="staff-reservations"><div className="panel-heading"><div><span className="panel-kicker">Bookings</span><h3>Reservations</h3></div><span className="live-count">{data.reservations.length} shown</span></div>
                <div className="filters"><button className={status ? "" : "on"} onClick={() => { setStatus(""); loadReservations("", query); }}>All</button>{["pending", "confirmed", "checked_in", "checked_out", "cancelled"].map((item) => <button key={item} className={status === item ? "on" : ""} onClick={() => { setStatus(item); loadReservations(item, query); }}>{item.replace("_", " ")}</button>)}</div>
                <form className="dashboard-search" onSubmit={(event) => { event.preventDefault(); loadReservations(status, query); }}><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search reference, guest, email or room" /><button className="btn btn-ghost btn-sm" type="submit">Search</button>{query && <button className="btn btn-ghost btn-sm" type="button" onClick={() => { setQuery(""); loadReservations(status, ""); }}>Clear</button>}</form>
                <div className="table-scroll-hint">Scroll horizontally to view all reservation details <span aria-hidden="true">→</span></div>
                <div className="table-wrap reservation-table-wrap"><table><thead><tr><th>Reference</th><th>Guest</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                {data.reservations.length ? data.reservations.map((reservation) => <tr key={reservation.id}>
                    <td><b>{reservation.reference}</b><span className="muted small reservation-guest-label">Booking</span></td>
                    <td>{reservation.guest}<br /><span className="muted small">{reservation.email}</span></td>
                    <td>{reservation.room}, {reservation.roomType}</td>
                    <td><strong>{formatDate(reservation.checkIn)}</strong><span className="stay-arrow">→</span><strong>{formatDate(reservation.checkOut)}</strong><br /><span className="muted small">{reservation.nights} {reservation.nights === 1 ? "night" : "nights"} · {reservation.guests} {reservation.guests === 1 ? "guest" : "guests"}</span></td>
                    <td>₱{reservation.total.toLocaleString()}</td><td><span className={`badge badge-${reservation.status}`}>{reservation.status.replaceAll("_", " ")}</span></td>
                    <td className="reservation-actions">{nextStatus[reservation.status] ? <button className={`btn btn-sm ${nextStatus[reservation.status] === "cancelled" ? "btn-danger" : ""}`} onClick={() => updateStatus(reservation.id, nextStatus[reservation.status])}>{actionLabel[nextStatus[reservation.status]]}</button> : <span className="muted small">—</span>}</td>
                </tr>) : <tr><td colSpan="7">No reservations match this filter.</td></tr>}
                </tbody></table></div>
            </section>
            <section className="panel dashboard-panel" id="staff-rooms"><div className="panel-heading"><div><span className="panel-kicker">Inventory</span><h3>Rooms</h3></div></div>
                <div className="table-wrap inventory-table-wrap"><table><thead><tr><th>Room</th><th>Type</th><th>Sleeps</th><th>Nightly rate</th><th>Open for booking</th><th></th></tr></thead><tbody>
                {data.rooms.map((room) => <tr key={room.id}><td><b>{room.number}</b></td><td>{room.type}</td><td>{room.capacity}</td><td>₱{room.pricePerNight.toLocaleString()}</td><td>{room.active ? "Open" : "Closed"}</td><td>{data.permissions?.manageRooms && <button className="btn btn-ghost btn-sm" onClick={() => updateRoom(room)}>Edit price</button>}</td></tr>)}
                </tbody></table></div>
                {data.permissions?.manageRooms && <><h3>Add a room</h3>
                {roomMessage && <p className="error">{roomMessage}</p>}
                <form className="form-grid" onSubmit={addRoom}>
                    <input
                        placeholder="Room number"
                        value={roomForm.number}
                        onChange={(e) =>
                            setRoomForm({ ...roomForm, number: e.target.value })
                        }
                        required
                    />
                    <select
                        value={roomForm.type}
                        onChange={(e) =>
                            setRoomForm({ ...roomForm, type: e.target.value })
                        }
                    >
                        <option>Standard Room</option>
                        <option>Tryke Room</option>
                    </select>
                    <input
                        type="number"
                        min="1"
                        placeholder="Capacity"
                        value={roomForm.capacity}
                        onChange={(e) =>
                            setRoomForm({
                                ...roomForm,
                                capacity: Number(e.target.value),
                            })
                        }
                        required
                    />
                    <input
                        type="number"
                        min="1"
                        placeholder="Nightly price"
                        value={roomForm.pricePerNight}
                        onChange={(e) =>
                            setRoomForm({
                                ...roomForm,
                                pricePerNight: Number(e.target.value),
                            })
                        }
                        required
                    />
                    <input
                        placeholder="Amenities"
                        value={roomForm.amenities}
                        onChange={(e) =>
                            setRoomForm({
                                ...roomForm,
                                amenities: e.target.value,
                            })
                        }
                    />
                    <input
                        placeholder="Description"
                        value={roomForm.description}
                        onChange={(e) =>
                            setRoomForm({
                                ...roomForm,
                                description: e.target.value,
                            })
                        }
                    />
                    <button className="btn" type="submit" disabled={!token}>
                        Add room
                    </button>
                </form></>}
            </section>
            {operationsError && <p className="error operations-load-error" role="alert">Room operations could not be loaded: {operationsError}</p>}
            <RoomStatusPanel rooms={operations?.rooms || []} token={token} onSaved={async () => { await Promise.all([loadOperations(), loadReservations()]); }} />
            <section className="panel dashboard-panel" id="staff-maintenance"><div className="panel-heading"><div><span className="panel-kicker">Maintenance</span><h3>Work orders</h3></div></div>
                <button className="btn" type="button" onClick={async () => { const roomId = window.prompt("Room ID"); const title = window.prompt("Maintenance issue"); if (!roomId || !title) return; await postJson("/api/staff/maintenance", { roomId: Number(roomId), title, details: window.prompt("Details") || "", priority: "normal", _token: token }); const result = await getStaffOperations(); setOperations(result.data); }}>Create work order</button>
                <div className="table-wrap"><table><thead><tr><th>Room</th><th>Issue</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead><tbody>
                {(operations?.maintenance || []).map((item) => <tr key={item.id}><td>{item.room}</td><td><b>{item.title}</b></td><td>{item.priority}</td><td>{item.status}</td><td>{item.status !== "completed" && <button className="btn btn-ghost btn-sm" type="button" onClick={async () => { await patchJson(`/api/staff/maintenance/${item.id}`, { status: "completed", _token: token }); const result = await getStaffOperations(); setOperations(result.data); }}>Complete</button>}</td></tr>)}
                </tbody></table></div>
            </section>
            <section className="panel dashboard-panel" id="staff-inventory"><div className="panel-heading"><div><span className="panel-kicker">Stock</span><h3>Inventory</h3></div></div>
                <button className="btn" type="button" onClick={async () => { const name = window.prompt("Inventory item name"); if (!name) return; const quantity = Number(window.prompt("Current quantity", "0") || 0); const minimum = Number(window.prompt("Minimum quantity", "5") || 5); await postJson("/api/staff/inventory", { name, quantity, minimum, unit: "pcs", category: "supplies", _token: token }); const result = await getStaffOperations(); setOperations(result.data); }}>Add inventory item</button>
                <div className="table-wrap"><table><thead><tr><th>Item</th><th>Quantity</th><th>Minimum</th><th>Status</th></tr></thead><tbody>
                {(operations?.inventory || []).map((item) => <tr key={item.id}><td>{item.name}</td><td>{item.quantity} {item.unit}</td><td>{item.minimum}</td><td>{item.quantity <= item.minimum ? <span className="badge badge-pending">Low stock</span> : <span className="badge badge-confirmed">In stock</span>}</td></tr>)}
                </tbody></table></div>
            </section>
                </div>
            </div>
        </div></section>
    );
}
