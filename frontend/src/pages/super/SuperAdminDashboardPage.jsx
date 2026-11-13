import { useEffect, useState } from "react";
import {
    createBranch,
    createUser,
    getCsrfToken,
    getSuperDashboard,
    updateBranch,
    updateUser,
} from "../../api.js";
import { RoomStatusPanel } from "../staff/RoomStatusPanel.jsx";

export function SuperAdminDashboardPage() {
    const [data, setData] = useState(null);
    const [token, setToken] = useState("");
    const [error, setError] = useState("");
    const [branch, setBranch] = useState({
        name: "",
        city: "",
        address: "",
        phone: "",
        tagline: "",
        description: "",
    });
    const [user, setUser] = useState({
        name: "",
        email: "",
        password: "",
        role: "ROLE_STAFF",
        branchId: "",
    });
    const load = () =>
        getSuperDashboard()
            .then((result) => setData(result.data))
            .catch(() => setData(false));
    useEffect(() => {
        load();
        getCsrfToken("app").then((result) => setToken(result.token));
    }, []);
    const saveBranch = async (event) => {
        event.preventDefault();
        setError("");
        try {
            await createBranch({ ...branch, _token: token });
            setBranch({
                name: "",
                city: "",
                address: "",
                phone: "",
                tagline: "",
                description: "",
            });
            await load();
        } catch (e) {
            setError(e.message);
        }
    };
    const saveUser = async (event) => {
        event.preventDefault();
        setError("");
        try {
            await createUser({
                ...user,
                branchId: user.branchId ? Number(user.branchId) : null,
                _token: token,
            });
            setUser({
                name: "",
                email: "",
                password: "",
                role: "ROLE_STAFF",
                branchId: "",
            });
            await load();
        } catch (e) {
            setError(e.message);
        }
    };
    const editBranch = async (item) => {
        const name = window.prompt("Branch name", item.name);
        const city = window.prompt("Branch city", item.city);
        if (!name || !city) return;
        try {
            await updateBranch(item.id, { name, city, _token: token });
            await load();
        } catch (e) {
            setError(e.message);
        }
    };
    const editUser = async (item) => {
        const role = window.prompt(
            "Role: ROLE_STAFF, ROLE_ADMIN, or ROLE_SUPER_ADMIN",
            item.role === "Staff"
                ? "ROLE_STAFF"
                : item.role === "Branch admin"
                  ? "ROLE_ADMIN"
                  : "ROLE_SUPER_ADMIN",
        );
        if (!role) return;
        try {
            await updateUser(item.id, { role, _token: token });
            await load();
        } catch (e) {
            setError(e.message);
        }
    };
    if (data === false)
        return (
            <section className="page"><div className="wrap dashboard-page group-dashboard">
                <p className="error">Super admin access is required.</p>
            </div></section>
        );
    if (!data)
        return (
            <section className="page">
                <p>Loading administration…</p>
            </section>
        );
    const formatStatus = (status) => status.replaceAll("_", " ");
    const totalLabels = { revenue: "Recorded revenue · all branches", reservations: "Reservations · all branches", pending: "Pending reservations", users: "System accounts" };
    return (
        <section className="page"><div className="wrap dashboard-page group-dashboard">
            <div className="dash-head dashboard-heading" id="super-overview"><p className="eyebrow dark">System control · all branches</p><h1>Global dashboard</h1><p className="muted">Monitor organization-wide performance, manage branches and control staff access across Amor Palace Hotel.</p></div>
            <div className="dashboard-workspace">
                <nav className="dashboard-section-nav" aria-label="System dashboard sections">
                    <span>System administration</span>
                    <a href="#super-overview">Overview</a>
                    <a href="#super-branches">Branch management</a>
                    <a href="#super-people">User management</a>
                    <a href="#super-room-status">All room status</a>
                    <a href="#super-reservations">Global reservations</a>
                </nav>
                <div className="dashboard-content">
            {error && <p className="error">{error}</p>}
            <div className="stats">
                {Object.entries(data.totals).map(([key, value]) => (
                    <div className="stat" key={key}>
                        <b>
                            {key === "revenue"
                                ? `₱${value.toLocaleString()}`
                                : value}
                        </b>
                        <span>{totalLabels[key] || key}</span>
                    </div>
                ))}
            </div>
            <section className="panel dashboard-panel" id="super-branches"><div className="panel-heading"><div><span className="panel-kicker">Organization</span><h3>Branch management</h3></div></div>
                <div className="table-wrap"><table><thead><tr><th>Branch</th><th>Status</th><th>City</th><th>Rooms</th><th>In house</th><th>Pending</th><th>Revenue</th><th></th></tr></thead><tbody>
                {data.branches.map((item) => <tr key={item.id}><td><b>{item.name}</b><br /><span className="muted small">{item.phone}</span></td><td>{item.active ? "Open" : "Archived"}</td><td>{item.city}</td><td>{item.rooms}</td><td>{item.inhouse}</td><td>{item.pending}</td><td>₱{item.revenue.toLocaleString()}</td><td>{item.active && <button className="btn btn-ghost btn-sm" onClick={() => editBranch(item)}>Edit branch</button>}</td></tr>)}
                </tbody></table></div>
                <h3>Add a branch</h3><form className="form-grid" onSubmit={saveBranch}>
                    <input
                        placeholder="Branch name"
                        value={branch.name}
                        onChange={(e) =>
                            setBranch({ ...branch, name: e.target.value })
                        }
                        required
                    />
                    <input
                        placeholder="City"
                        value={branch.city}
                        onChange={(e) =>
                            setBranch({ ...branch, city: e.target.value })
                        }
                        required
                    />
                    <input
                        placeholder="Address"
                        value={branch.address}
                        onChange={(e) =>
                            setBranch({ ...branch, address: e.target.value })
                        }
                    />
                    <input
                        placeholder="Phone"
                        value={branch.phone}
                        onChange={(e) =>
                            setBranch({ ...branch, phone: e.target.value })
                        }
                    />
                    <button className="btn" type="submit" disabled={!token}>
                        Add branch
                    </button>
                </form>
            </section>
            <section className="panel dashboard-panel" id="super-people"><div className="panel-heading"><div><span className="panel-kicker">System access</span><h3>Users and roles</h3></div></div>
                <div className="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th></th></tr></thead><tbody>
                {data.users.map((item) => <tr key={item.id}><td>{item.name}</td><td>{item.email}</td><td>{item.role}</td><td>{item.branch || "No branch"}</td><td><button className="btn btn-ghost btn-sm" onClick={() => editUser(item)}>Edit role</button></td></tr>)}
                </tbody></table></div>
                <h3>Add a staff member or admin</h3>
                <form className="form-grid" onSubmit={saveUser}>
                    <input
                        placeholder="Full name"
                        value={user.name}
                        onChange={(e) =>
                            setUser({ ...user, name: e.target.value })
                        }
                        required
                    />
                    <input
                        type="email"
                        placeholder="Email"
                        value={user.email}
                        onChange={(e) =>
                            setUser({ ...user, email: e.target.value })
                        }
                        required
                    />
                    <input
                        type="password"
                        placeholder="Temporary password"
                        value={user.password}
                        onChange={(e) =>
                            setUser({ ...user, password: e.target.value })
                        }
                        minLength="8"
                        required
                    />
                    <select
                        value={user.role}
                        onChange={(e) =>
                            setUser({ ...user, role: e.target.value })
                        }
                    >
                        <option value="ROLE_STAFF">Staff</option>
                        <option value="ROLE_ADMIN">Branch admin</option>
                        <option value="ROLE_SUPER_ADMIN">Super admin</option>
                    </select>
                    <select
                        value={user.branchId}
                        onChange={(e) =>
                            setUser({ ...user, branchId: e.target.value })
                        }
                    >
                        <option value="">No branch</option>
                        {data.branches.filter((item) => item.active).map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <button className="btn" type="submit" disabled={!token}>
                        Create user
                    </button>
                </form>
            </section>
            <RoomStatusPanel
                id="super-room-status"
                rooms={data.rooms || []}
                branchNames={data.branches.map((branch) => branch.name)}
                token={token}
                showBranch
                onSaved={async () => {
                    const result = await getSuperDashboard();
                    setData(result.data);
                }}
            />
            <section className="panel dashboard-panel" id="super-reservations"><div className="panel-heading"><div><span className="panel-kicker">Hotel monitoring · all branches</span><h3>Latest global reservations</h3></div></div>
                <div className="table-wrap"><table><thead><tr><th>Reference</th><th>Guest</th><th>Branch</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th></tr></thead><tbody>
                {data.recent.length ? data.recent.map((reservation) => <tr key={reservation.id}><td><b>{reservation.reference}</b></td><td>{reservation.guest}<br /><span className="muted small">{reservation.email}</span></td><td>{reservation.branch}</td><td>{reservation.room}, {reservation.roomType}</td><td>{new Date(reservation.checkIn).toLocaleDateString("en-GB", { day: "2-digit", month: "short" })} to {new Date(reservation.checkOut).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })}</td><td>₱{reservation.total.toLocaleString()}</td><td><span className={`badge badge-${reservation.status}`}>{formatStatus(reservation.status)}</span></td></tr>) : <tr><td colSpan="7">No reservations yet.</td></tr>}
                </tbody></table></div>
            </section>
                </div>
            </div>
        </div></section>
    );
}
